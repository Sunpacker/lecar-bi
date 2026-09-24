<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Application\Contracts;

interface EmbeddingModel
{
    /** @return list<float> */
    public function embedDocument(string $text): array;

    /** @return list<float> */
    public function embedQuery(string $text): array;

    public function provider(): string;

    public function profile(): string;

    public function dimensions(): int;
}
