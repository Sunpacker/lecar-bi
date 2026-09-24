<?php

declare(strict_types=1);

namespace App\Modules\KnowledgeBase\Infrastructure\Ai;

use App\Modules\KnowledgeBase\Application\Contracts\EmbeddingModel;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use RuntimeException;

final readonly class NeuronGeminiEmbeddingModel implements EmbeddingModel
{
    public function __construct(
        private EmbeddingsProviderInterface $providerAdapter,
        private string $modelName,
        private int $vectorDimensions,
    ) {}

    public function embedDocument(string $text): array
    {
        return $this->embed('title: AutoBI support | text: '.$text);
    }

    public function embedQuery(string $text): array
    {
        return $this->embed('task: question answering | query: '.$text);
    }

    public function provider(): string
    {
        return 'google-gemini';
    }

    public function profile(): string
    {
        return $this->modelName.'-qa-'.$this->vectorDimensions.'-v1';
    }

    public function dimensions(): int
    {
        return $this->vectorDimensions;
    }

    /** @return list<float> */
    private function embed(string $text): array
    {
        $values = array_map(static fn (mixed $value): float => (float) $value, $this->providerAdapter->embedText($text));
        if (count($values) !== $this->vectorDimensions) {
            throw new RuntimeException('Embedding response dimensions do not match the configured profile');
        }
        foreach ($values as $value) {
            if (! is_finite($value)) {
                throw new RuntimeException('Embedding response contains an invalid value');
            }
        }

        $length = sqrt(array_sum(array_map(static fn (float $value): float => $value ** 2, $values)));
        if ($length <= 0.0) {
            throw new RuntimeException('Embedding response has zero magnitude');
        }

        return array_values(array_map(static fn (float $value): float => $value / $length, $values));
    }
}
