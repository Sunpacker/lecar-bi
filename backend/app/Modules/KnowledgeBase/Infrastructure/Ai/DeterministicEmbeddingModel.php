<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Ai;

use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModel;

final class DeterministicEmbeddingModel implements EmbeddingModel
{
    private const DIMENSIONS = 8;

    public function embedDocument(string $text): array
    {
        return $this->embed($text);
    }

    public function embedQuery(string $text): array
    {
        return $this->embed($text);
    }

    /** @return list<float> */
    private function embed(string $text): array
    {
        $bytes = unpack('C*', hash('sha256', $this->normalize($text), true));
        $vector = [];

        for ($index = 1; $index <= self::DIMENSIONS; $index++) {
            $vector[] = (($bytes[$index] ?? 128) - 127.5) / 127.5;
        }

        $length = sqrt(array_sum(array_map(static fn (float $value): float => $value ** 2, $vector)));

        return array_map(static fn (float $value): float => $value / max($length, 0.000001), $vector);
    }

    public function profile(): string
    {
        return 'deterministic-v1';
    }

    public function provider(): string
    {
        return 'deterministic';
    }

    public function dimensions(): int
    {
        return self::DIMENSIONS;
    }

    private function normalize(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text) ?? $text));
    }
}
