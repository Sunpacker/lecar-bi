<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\KnowledgeBase;

use App\Modules\KnowledgeBase\Infrastructure\Ai\ConfiguredEmbeddingModelRegistry;
use App\Modules\KnowledgeBase\Infrastructure\Ai\DeterministicEmbeddingModel;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ConfiguredEmbeddingModelRegistryTest extends TestCase
{
    public function test_it_resolves_only_an_exact_persisted_profile(): void
    {
        $model = new DeterministicEmbeddingModel;
        $registry = new ConfiguredEmbeddingModelRegistry([$model]);

        self::assertSame($model, $registry->resolve('deterministic', 'deterministic-v1', 8));

        $this->expectException(RuntimeException::class);
        $registry->resolve('deterministic', 'deterministic-v1', 1536);
    }
}
