<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Ingestion;

use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModel;
use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;
use RuntimeException;
use Throwable;

final readonly class KnowledgeIngestionService
{
    public function __construct(
        private Connection $connection,
        private EmbeddingModel $embeddingModel,
        private int $dailyTokenBudget,
    ) {}

    /** @return array{build_id: string, status: string, chunks: int} */
    public function ingest(string $manifestPath, string $repositoryRoot): array
    {
        $manifest = $this->readManifest($manifestPath);
        $profileId = $this->ensureProfile();
        $publication = $this->connection->table('knowledge_publications')->where('scope', 'global')->first();
        $buildId = $this->stableUuid('build:'.$manifest['revision'].':'.$profileId);
        $existing = $this->connection->table('knowledge_index_builds')->where('id', $buildId)->first();
        if ($existing?->status === 'ready' && $publication?->active_build_id === $buildId) {
            return ['build_id' => $buildId, 'status' => 'ready', 'chunks' => $this->chunkCount($buildId)];
        }

        $now = CarbonImmutable::now('UTC');
        $this->connection->table('knowledge_index_builds')->upsert([[
            'id' => $buildId, 'manifest_revision' => $manifest['revision'], 'embedding_profile_id' => $profileId,
            'status' => 'pending', 'error_code' => null, 'created_at' => $now, 'updated_at' => $now,
        ]], ['id'], ['status', 'error_code', 'updated_at']);

        try {
            return $this->build($manifest, $repositoryRoot, $buildId, (int) ($publication->version ?? 0));
        } catch (Throwable $exception) {
            $this->markFailed($buildId, $exception);
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{build_id: string, status: string, chunks: int}
     */
    private function build(array $manifest, string $repositoryRoot, string $buildId, int $expectedPublicationVersion): array
    {
        $this->connection->table('knowledge_index_builds')->where('id', $buildId)->update(['status' => 'indexing', 'updated_at' => CarbonImmutable::now('UTC')]);
        $allowedSourceIds = array_map(static fn (array $document): string => (string) $document['id'], $manifest['documents']);
        $chunkCount = 0;

        foreach ($manifest['documents'] as $document) {
            $chunkCount += $this->ingestDocument($manifest, $document, $repositoryRoot, $buildId);
        }
        if ($chunkCount === 0) {
            throw new RuntimeException('Knowledge build contains no chunks');
        }
        if (! $this->publish($buildId, $expectedPublicationVersion, $allowedSourceIds)) {
            throw new RuntimeException('Knowledge build publication was superseded');
        }

        return ['build_id' => $buildId, 'status' => 'ready', 'chunks' => $chunkCount];
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @param  array<string, mixed>  $document
     */
    private function ingestDocument(array $manifest, array $document, string $repositoryRoot, string $buildId): int
    {
        $path = (string) $document['path'];
        if (! str_starts_with($path, 'docs/support/') || str_contains($path, '..')) {
            throw new RuntimeException('Manifest contains a forbidden source path');
        }
        $content = $this->readFile(rtrim($repositoryRoot, '/').'/'.$path, 'Manifest source cannot be read');

        $sourceId = (string) $document['id'];
        $documentVersionId = $this->stableUuid("document:{$sourceId}:{$document['revision']}");
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('knowledge_sources')->upsert([[
            'id' => $sourceId, 'source_type' => 'markdown', 'manifest_revision' => $manifest['revision'], 'allowed_path' => $path,
            'canonical_url' => $document['url'], 'scope' => 'global', 'workspace_id' => null, 'revoked_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ]], ['id'], ['manifest_revision', 'allowed_path', 'canonical_url', 'updated_at']);

        $existingDocument = $this->connection->table('knowledge_document_versions')
            ->where('document_id', $sourceId)
            ->where('revision', $document['revision'])
            ->first();
        if ($existingDocument !== null && ! hash_equals((string) $existingDocument->content_hash, hash('sha256', $content))) {
            throw new RuntimeException('Published document revision is immutable');
        }

        $this->connection->table('knowledge_document_versions')->upsert([[
            'id' => $documentVersionId, 'document_id' => $sourceId, 'source_id' => $sourceId, 'revision' => $document['revision'],
            'content_hash' => hash('sha256', $content), 'title' => $document['title'], 'language' => $manifest['language'],
            'canonical_url' => $document['url'], 'status' => 'published', 'revoked_at' => null, 'created_at' => $now, 'updated_at' => $now,
        ]], ['document_id', 'revision'], ['content_hash', 'title', 'canonical_url', 'status', 'revoked_at', 'updated_at']);

        $chunks = $this->chunks($content);
        foreach ($chunks as $index => $chunk) {
            $this->storeChunk($buildId, $documentVersionId, $index, $chunk);
        }

        return count($chunks);
    }

    /** @param array{heading: ?string, anchor: ?string, content: string} $chunk */
    private function storeChunk(string $buildId, string $documentVersionId, int $index, array $chunk): void
    {
        $chunkId = $this->stableUuid("chunk:{$buildId}:{$documentVersionId}:{$index}");
        if ($this->connection->table('knowledge_chunks')->where('id', $chunkId)->whereNotNull('embedding')->exists()) {
            return;
        }

        $tokens = max(1, (int) ceil(mb_strlen($chunk['content']) / 4));
        $this->reserveEmbeddingTokens($tokens);
        $vector = '['.implode(',', $this->embeddingModel->embedDocument($chunk['content'])).']';
        $now = CarbonImmutable::now('UTC');

        if ($this->connection->getDriverName() === 'pgsql') {
            $this->storePostgresChunk($chunkId, $buildId, $documentVersionId, $index, $chunk, $vector, $now);

            return;
        }

        $this->connection->table('knowledge_chunks')->upsert([[
            'id' => $chunkId, 'build_id' => $buildId, 'document_version_id' => $documentVersionId, 'chunk_index' => $index,
            'heading' => $chunk['heading'], 'anchor' => $chunk['anchor'], 'content' => $chunk['content'], 'content_hash' => hash('sha256', $chunk['content']),
            'scope' => 'global', 'workspace_id' => null, 'fts_configuration' => 'russian', 'embedding' => $vector, 'search_vector' => $chunk['content'],
            'created_at' => $now, 'updated_at' => $now,
        ]], ['build_id', 'document_version_id', 'chunk_index'], ['embedding', 'updated_at']);
    }

    /** @param array{heading: ?string, anchor: ?string, content: string} $chunk */
    private function storePostgresChunk(string $chunkId, string $buildId, string $documentVersionId, int $index, array $chunk, string $vector, CarbonImmutable $now): void
    {
        $this->connection->statement(
            <<<'SQL'
INSERT INTO knowledge_chunks
(id, build_id, document_version_id, chunk_index, heading, anchor, content, content_hash, scope, workspace_id, fts_configuration, embedding, created_at, updated_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'global', NULL, 'russian', CAST(? AS vector), ?, ?)
ON CONFLICT (build_id, document_version_id, chunk_index) DO UPDATE
SET embedding = COALESCE(knowledge_chunks.embedding, EXCLUDED.embedding), updated_at = EXCLUDED.updated_at
SQL,
            [$chunkId, $buildId, $documentVersionId, $index, $chunk['heading'], $chunk['anchor'], $chunk['content'], hash('sha256', $chunk['content']), $vector, $now, $now],
        );
    }

    /** @return list<array{heading: ?string, anchor: ?string, content: string}> */
    private function chunks(string $markdown): array
    {
        $blocks = preg_split('/\n(?=#{1,3}\s)|\n{2,}/u', trim($markdown)) ?: [];
        $chunks = [];
        $heading = null;

        foreach ($blocks as $block) {
            $trimmed = trim($block);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^#{1,3}\s+(.+)$/u', $trimmed, $matches) === 1) {
                $heading = trim($matches[1]);

                continue;
            }

            foreach ($this->splitLongBlock($trimmed) as $content) {
                $chunks[] = ['heading' => $heading, 'anchor' => $heading === null ? null : $this->anchor($heading), 'content' => $content];
            }
        }

        return $chunks;
    }

    /** @return list<string> */
    private function splitLongBlock(string $content): array
    {
        if (mb_strlen($content) <= 1800) {
            return [$content];
        }

        return array_values(array_filter(explode("\n", wordwrap($content, 1800, "\n", true))));
    }

    /** @param list<string> $allowedSourceIds */
    private function publish(string $buildId, int $expectedVersion, array $allowedSourceIds): bool
    {
        return $this->connection->transaction(function () use ($buildId, $expectedVersion, $allowedSourceIds): bool {
            $publication = $this->connection->table('knowledge_publications')->where('scope', 'global')->lockForUpdate()->first();
            if ((int) $publication->version !== $expectedVersion) {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            $this->connection->table('knowledge_sources')->where('scope', 'global')->whereIn('id', $allowedSourceIds)->update(['revoked_at' => null, 'updated_at' => $now]);
            $this->revokeMissingSources($allowedSourceIds, $now);
            $this->connection->table('knowledge_index_builds')->where('id', $buildId)->update(['status' => 'ready', 'published_at' => $now, 'updated_at' => $now]);

            return $this->connection->table('knowledge_publications')->where('scope', 'global')->where('version', $expectedVersion)->update(['active_build_id' => $buildId, 'version' => $expectedVersion + 1, 'updated_at' => $now]) === 1;
        });
    }

    /** @param list<string> $allowedSourceIds */
    private function revokeMissingSources(array $allowedSourceIds, ?CarbonImmutable $revokedAt = null): void
    {
        $query = $this->connection->table('knowledge_sources')->where('scope', 'global');
        if ($allowedSourceIds !== []) {
            $query->whereNotIn('id', $allowedSourceIds);
        }
        $now = $revokedAt ?? CarbonImmutable::now('UTC');
        $query->update(['revoked_at' => $now, 'updated_at' => $now]);
    }

    private function reserveEmbeddingTokens(int $tokens): void
    {
        $this->connection->transaction(function () use ($tokens): void {
            $date = CarbonImmutable::now('UTC')->toDateString();
            $this->lockScope('knowledge-ingestion-budget:'.$date);
            $usage = $this->connection->table('knowledge_ingestion_usage')->where('usage_date', $date)->lockForUpdate()->first();
            $used = (int) ($usage->embedded_tokens ?? 0);
            if ($used + $tokens > $this->dailyTokenBudget) {
                throw new RuntimeException('Knowledge ingestion budget exceeded');
            }

            $now = CarbonImmutable::now('UTC');
            $this->connection->table('knowledge_ingestion_usage')->upsert([[
                'usage_date' => $date, 'embedded_tokens' => $used + $tokens, 'created_at' => $now, 'updated_at' => $now,
            ]], ['usage_date'], ['embedded_tokens', 'updated_at']);
        });
    }

    private function ensureProfile(): string
    {
        $profileId = $this->stableUuid('profile:'.$this->embeddingModel->provider().':'.$this->embeddingModel->profile().':'.$this->embeddingModel->dimensions());
        $now = CarbonImmutable::now('UTC');
        $this->connection->table('knowledge_embedding_profiles')->upsert([[
            'id' => $profileId, 'provider' => $this->embeddingModel->provider(), 'model' => $this->embeddingModel->profile(),
            'dimensions' => $this->embeddingModel->dimensions(), 'distance_metric' => 'cosine', 'normalized' => true,
            'chunking_version' => 'markdown-headings-v1', 'chunk_tokens' => 500, 'overlap_tokens' => 75, 'created_at' => $now, 'updated_at' => $now,
        ]], ['id'], ['updated_at']);

        return $profileId;
    }

    /** @return array<string, mixed> */
    private function readManifest(string $path): array
    {
        $content = $this->readFile($path, 'Knowledge manifest cannot be read');
        $manifest = json_decode($content, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($manifest) || ($manifest['scope'] ?? null) !== 'global' || ($manifest['language'] ?? null) !== 'ru' || ! is_array($manifest['documents'] ?? null)) {
            throw new RuntimeException('Knowledge manifest is invalid');
        }

        return $manifest;
    }

    private function readFile(string $path, string $errorMessage): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException($errorMessage);
        }

        try {
            $content = file_get_contents($path);
        } catch (Throwable) {
            throw new RuntimeException($errorMessage);
        }
        if ($content === false) {
            throw new RuntimeException($errorMessage);
        }

        return $content;
    }

    private function markFailed(string $buildId, Throwable $exception): void
    {
        $code = $exception->getMessage() === 'Knowledge build publication was superseded' ? 'PUBLICATION_SUPERSEDED' : 'INGESTION_FAILED';
        $this->connection->table('knowledge_index_builds')->where('id', $buildId)->update(['status' => 'failed', 'error_code' => $code, 'updated_at' => CarbonImmutable::now('UTC')]);
    }

    private function chunkCount(string $buildId): int
    {
        return $this->connection->table('knowledge_chunks')->where('build_id', $buildId)->count();
    }

    private function stableUuid(string $value): string
    {
        $hex = substr(hash('sha256', $value), 0, 32);
        $hex[12] = '5';
        $hex[16] = dechex((hexdec($hex[16]) & 0x3) | 0x8);

        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20);
    }

    private function anchor(string $heading): string
    {
        return trim(preg_replace('/[^\pL\pN]+/u', '-', mb_strtolower($heading)) ?? '', '-');
    }

    private function lockScope(string $scope): void
    {
        if ($this->connection->getDriverName() === 'pgsql') {
            $this->connection->select('SELECT pg_advisory_xact_lock(hashtext(?))', [$scope]);
        }
    }
}
