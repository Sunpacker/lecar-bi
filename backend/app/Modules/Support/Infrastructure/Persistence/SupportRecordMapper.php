<?php

declare(strict_types=1);

namespace App\Modules\Support\Infrastructure\Persistence;

use Carbon\CarbonImmutable;
use Illuminate\Database\Connection;

final readonly class SupportRecordMapper
{
    public function __construct(private Connection $connection) {}

    /** @return array<string, mixed> */
    public function conversation(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'workspace_id' => (string) $row->workspace_id,
            'title' => (string) $row->title,
            'created_at' => CarbonImmutable::parse($row->created_at)->toIso8601String(),
            'updated_at' => CarbonImmutable::parse($row->updated_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function message(object $row): array
    {
        $generation = $this->connection->table('support_generations')->where('assistant_message_id', $row->id)->orderByDesc('attempt')->first();

        return [
            'id' => (string) $row->id,
            'conversation_id' => (string) $row->conversation_id,
            'role' => (string) $row->role,
            'content' => (string) $row->content,
            'position' => (int) $row->position,
            'generation_id' => $generation?->id,
            'generation_status' => $generation?->status,
            'outcome' => $generation?->outcome,
            'citations' => $generation === null ? [] : $this->citations((string) $generation->id),
            'created_at' => CarbonImmutable::parse($row->created_at)->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function generation(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'conversation_id' => (string) $row->conversation_id,
            'user_message_id' => (string) $row->user_message_id,
            'assistant_message_id' => (string) $row->assistant_message_id,
            'attempt' => (int) $row->attempt,
            'status' => (string) $row->status,
            'outcome' => $row->outcome,
            'sequence' => (int) $row->sequence,
            'text' => (string) $row->text,
            'error_code' => $row->error_code,
            'retryable' => (bool) $row->retryable,
            'citations' => $this->citations((string) $row->id),
            'created_at' => CarbonImmutable::parse($row->created_at)->toIso8601String(),
            'updated_at' => CarbonImmutable::parse($row->updated_at)->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function citations(string $generationId): array
    {
        $rows = $this->connection->table('support_citations as c')
            ->leftJoin('knowledge_document_versions as d', function ($join): void {
                $join->on('d.document_id', '=', 'c.document_id')->on('d.revision', '=', 'c.document_revision');
            })
            ->leftJoin('knowledge_sources as s', 's.id', '=', 'd.source_id')
            ->where('c.generation_id', $generationId)->orderBy('c.ordinal')
            ->select('c.*', 'd.status as document_status', 'd.revoked_at as document_revoked_at', 's.revoked_at as source_revoked_at')->get();

        return $rows->map(static function (object $row): array {
            $available = $row->document_status === 'published' && $row->document_revoked_at === null && $row->source_revoked_at === null;

            return [
                'document_id' => (string) $row->document_id,
                'revision' => (string) $row->document_revision,
                'chunk_id' => (string) $row->chunk_id,
                'title' => $available ? (string) $row->title : 'Источник недоступен',
                'url' => $available ? (string) $row->url : null,
                'anchor' => $available ? $row->anchor : null,
                'available' => $available,
            ];
        })->all();
    }
}
