<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Ai;

use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModel;
use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModelRegistry;
use RuntimeException;

final readonly class ConfiguredEmbeddingModelRegistry implements EmbeddingModelRegistry
{
    /** @param iterable<EmbeddingModel> $models */
    public function __construct(private iterable $models) {}

    public function resolve(string $provider, string $model, int $dimensions): EmbeddingModel
    {
        foreach ($this->models as $candidate) {
            if ($candidate->provider() !== $provider) {
                continue;
            }
            if ($candidate->profile() !== $model) {
                continue;
            }
            if ($candidate->dimensions() !== $dimensions) {
                continue;
            }

            return $candidate;
        }

        throw new RuntimeException('Active knowledge embedding profile is not configured');
    }
}
