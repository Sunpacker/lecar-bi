<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Ai;

use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModel;
use RuntimeException;

final class UnconfiguredEmbeddingModel implements EmbeddingModel
{
    public function embedDocument(string $text): array
    {
        throw new RuntimeException('Support embedding provider is not configured');
    }

    public function embedQuery(string $text): array
    {
        throw new RuntimeException('Support embedding provider is not configured');
    }

    public function profile(): string
    {
        return 'unconfigured';
    }

    public function provider(): string
    {
        return 'unconfigured';
    }

    public function dimensions(): int
    {
        return 0;
    }
}
