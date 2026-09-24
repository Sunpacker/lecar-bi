<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\KnowledgeBase;

use App\Modules\KnowledgeBase\Infrastructure\Ai\NeuronGeminiEmbeddingModel;
use NeuronAI\RAG\Embeddings\EmbeddingsProviderInterface;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class NeuronGeminiEmbeddingModelTest extends TestCase
{
    public function test_it_uses_distinct_embedding_two_prefixes_and_normalizes_vectors(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->expects(self::exactly(2))->method('embedText')->willReturnCallback(
            static function (string $text): array {
                self::assertTrue(
                    str_starts_with($text, 'title: AutoBI support | text: ')
                    || str_starts_with($text, 'task: question answering | query: '),
                );

                return [3.0, 4.0];
            },
        );

        $model = new NeuronGeminiEmbeddingModel($provider, 'gemini-embedding-2', 2);

        self::assertSame([0.6, 0.8], $model->embedDocument('Документ'));
        self::assertSame([0.6, 0.8], $model->embedQuery('Вопрос'));
        self::assertSame('gemini-embedding-2-qa-2-v1', $model->profile());
    }

    public function test_it_rejects_an_unexpected_vector_size(): void
    {
        $provider = $this->createMock(EmbeddingsProviderInterface::class);
        $provider->method('embedText')->willReturn([1.0]);

        $this->expectException(RuntimeException::class);
        (new NeuronGeminiEmbeddingModel($provider, 'gemini-embedding-2', 2))->embedQuery('Вопрос');
    }
}
