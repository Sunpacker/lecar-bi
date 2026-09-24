<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Application\Contracts;

interface KnowledgeRetriever
{
    /** @return array{build_id: ?string, chunks: list<array<string, mixed>>, trace: array<string, mixed>} */
    public function retrieve(string $workspaceId, string $question, int $tokenBudget): array;

    /** @param list<string> $chunkIds */
    public function areChunksAvailable(string $workspaceId, string $buildId, array $chunkIds): bool;
}
