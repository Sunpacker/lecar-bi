<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Application\Contracts;

interface EmbeddingModelRegistry
{
    public function resolve(string $provider, string $model, int $dimensions): EmbeddingModel;
}
