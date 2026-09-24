<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Retrieval;

use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModelRegistry;
use App\Modules\KnowledgeBase\Application\Contracts\KnowledgeRetriever;
use Illuminate\Database\Connection;

final readonly class PostgresKnowledgeRetriever implements KnowledgeRetriever
{
    public function __construct(
        private Connection $connection,
        private EmbeddingModelRegistry $embeddingModels,
        private int $candidateLimit,
        private int $contextLimit,
        private int $rrfK,
        private float $minimumScore,
        private float $minimumHybridVectorScore = -1.0,
        private float $minimumSemanticVectorScore = PHP_FLOAT_MAX,
    ) {}

    public function retrieve(string $workspaceId, string $question, int $tokenBudget): array
    {
        if ($this->connection->getDriverName() !== 'pgsql') {
            return ['build_id' => null, 'chunks' => [], 'trace' => ['reason' => 'postgres_required']];
        }

        $profile = $this->activeProfile();
        if ($profile === null) {
            return ['build_id' => null, 'chunks' => [], 'trace' => ['reason' => 'no_active_build']];
        }

        $embeddingModel = $this->embeddingModels->resolve($profile['provider'], $profile['model'], $profile['dimensions']);
        $values = $embeddingModel->embedQuery($question);
        if (count($values) !== $profile['dimensions']) {
            throw new \RuntimeException('Embedding response dimensions do not match the active knowledge profile');
        }

        $embedding = '['.implode(',', $values).']';
        $rows = $this->connection->select($this->hybridSql(), [
            $profile['build_id'],
            $embedding,
            $embedding,
            $embedding,
            $this->minimumHybridVectorScore,
            $embedding,
            $this->candidateLimit,
            $question,
            $this->candidateLimit,
            $this->rrfK,
            $this->rrfK,
            $this->contextLimit,
        ]);

        $chunks = [];
        $usedTokens = 0;

        foreach ($rows as $row) {
            $score = (float) $row->rrf_score;
            $vectorScore = $row->vector_score === null ? null : (float) $row->vector_score;
            $tokens = $this->estimateTokens((string) $row->content);
            $hybridEvidence = $score >= $this->minimumScore;
            $semanticEvidence = $vectorScore !== null && $vectorScore >= $this->minimumSemanticVectorScore;
            if ((! $hybridEvidence && ! $semanticEvidence) || $usedTokens + $tokens > $tokenBudget) {
                continue;
            }

            $usedTokens += $tokens;
            $chunks[] = [
                'source_key' => (string) $row->id,
                'chunk_id' => (string) $row->id,
                'document_id' => (string) $row->document_id,
                'revision' => (string) $row->revision,
                'title' => (string) $row->title,
                'url' => (string) $row->canonical_url,
                'anchor' => $row->anchor === null ? null : (string) $row->anchor,
                'content' => (string) $row->content,
                'rrf_score' => $score,
                'vector_score' => $vectorScore,
                'vector_rank' => $row->vector_rank === null ? null : (int) $row->vector_rank,
                'fts_rank' => $row->fts_rank === null ? null : (int) $row->fts_rank,
            ];
        }

        return [
            'build_id' => $profile['build_id'],
            'chunks' => $chunks,
            'trace' => [
                'profile' => [
                    'provider' => $profile['provider'],
                    'model' => $profile['model'],
                    'dimensions' => $profile['dimensions'],
                ],
                'candidate_limit' => $this->candidateLimit,
                'context_limit' => $this->contextLimit,
                'rrf_k' => $this->rrfK,
                'minimum_score' => $this->minimumScore,
                'minimum_hybrid_vector_score' => $this->minimumHybridVectorScore,
                'minimum_semantic_vector_score' => $this->minimumSemanticVectorScore,
                'selected_chunk_ids' => array_column($chunks, 'chunk_id'),
            ],
        ];
    }

    /** @return array{build_id: string, provider: string, model: string, dimensions: int}|null */
    private function activeProfile(): ?array
    {
        $profile = $this->connection->table('knowledge_publications as p')
            ->join('knowledge_index_builds as b', 'b.id', '=', 'p.active_build_id')
            ->join('knowledge_embedding_profiles as ep', 'ep.id', '=', 'b.embedding_profile_id')
            ->where('p.scope', 'global')
            ->where('b.status', 'ready')
            ->select('b.id as build_id', 'ep.provider', 'ep.model', 'ep.dimensions')
            ->first();
        if ($profile === null) {
            return null;
        }

        return [
            'build_id' => (string) $profile->build_id,
            'provider' => (string) $profile->provider,
            'model' => (string) $profile->model,
            'dimensions' => (int) $profile->dimensions,
        ];
    }

    public function areChunksAvailable(string $workspaceId, string $buildId, array $chunkIds): bool
    {
        $uniqueChunkIds = array_values(array_unique($chunkIds));
        if ($uniqueChunkIds === [] || $this->connection->getDriverName() !== 'pgsql') {
            return false;
        }

        $available = $this->connection->table('knowledge_chunks as c')
            ->join('knowledge_publications as p', function ($join) use ($buildId): void {
                $join->on('p.active_build_id', '=', 'c.build_id')
                    ->where('p.scope', 'global')
                    ->where('p.active_build_id', $buildId);
            })
            ->join('knowledge_document_versions as d', 'd.id', '=', 'c.document_version_id')
            ->join('knowledge_sources as s', 's.id', '=', 'd.source_id')
            ->where('c.build_id', $buildId)
            ->where('c.scope', 'global')
            ->whereNull('c.workspace_id')
            ->where('d.status', 'published')
            ->whereNull('d.revoked_at')
            ->whereNull('s.revoked_at')
            ->whereIn('c.id', $uniqueChunkIds)
            ->distinct()
            ->count('c.id');

        return $available === count($uniqueChunkIds);
    }

    private function hybridSql(): string
    {
        return <<<'SQL'
WITH eligible AS MATERIALIZED (
    SELECT c.*, d.document_id, d.revision, d.title, d.canonical_url
    FROM knowledge_chunks c
    JOIN knowledge_document_versions d ON d.id = c.document_version_id
    JOIN knowledge_sources s ON s.id = d.source_id
    WHERE c.scope = 'global'
      AND c.build_id = ?
      AND c.workspace_id IS NULL
      AND d.status = 'published'
      AND d.revoked_at IS NULL
      AND s.revoked_at IS NULL
), vector_candidates AS (
    SELECT id, 1 - (embedding <=> CAST(? AS vector)) AS vector_score,
           row_number() OVER (ORDER BY embedding <=> CAST(? AS vector), id) AS vector_rank
    FROM eligible
    WHERE embedding IS NOT NULL
      AND 1 - (embedding <=> CAST(? AS vector)) >= ?
    ORDER BY embedding <=> CAST(? AS vector), id
    LIMIT ?
), lexical_query AS MATERIALIZED (
    SELECT to_tsquery('russian', replace(plainto_tsquery('russian', ?)::text, ' & ', ' | ')) AS value
), fts_candidates AS (
    SELECT id, ts_rank_cd(search_vector, lexical_query.value) AS fts_score,
           row_number() OVER (ORDER BY ts_rank_cd(search_vector, lexical_query.value) DESC, id) AS fts_rank
    FROM eligible CROSS JOIN lexical_query
    WHERE search_vector @@ lexical_query.value
    ORDER BY fts_score DESC, id
    LIMIT ?
), fused AS (
    SELECT ids.id,
           COALESCE(1.0 / (? + v.vector_rank), 0) + COALESCE(1.0 / (? + f.fts_rank), 0) AS rrf_score,
           v.vector_score, v.vector_rank, f.fts_score, f.fts_rank
    FROM (SELECT id FROM vector_candidates UNION SELECT id FROM fts_candidates) ids
    LEFT JOIN vector_candidates v ON v.id = ids.id
    LEFT JOIN fts_candidates f ON f.id = ids.id
)
SELECT e.*, fused.rrf_score, fused.vector_score, fused.vector_rank, fused.fts_score, fused.fts_rank
FROM fused JOIN eligible e ON e.id = fused.id
ORDER BY fused.rrf_score DESC, e.id
LIMIT ?
SQL;
    }

    private function estimateTokens(string $content): int
    {
        return max(1, (int) ceil(mb_strlen($content) / 4));
    }
}
