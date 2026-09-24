<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Support;

use App\Modules\Support\Infrastructure\Ai\NeuronGemmaChatModel;
use Generator;
use NeuronAI\Chat\Messages\AssistantMessage;
use NeuronAI\Chat\Messages\Message;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Usage;
use NeuronAI\Providers\AIProviderInterface;
use PHPUnit\Framework\TestCase;

final class NeuronGemmaChatModelTest extends TestCase
{
    private const SOURCE_ID = '11111111-1111-4111-8111-111111111111';

    public function test_it_extracts_selected_citations_and_actual_usage_from_neuron_stream(): void
    {
        $provider = $this->createMock(AIProviderInterface::class);
        $provider->expects(self::once())->method('systemPrompt')->with(self::stringContains('только по фактам'))->willReturnSelf();
        $provider->expects(self::once())->method('stream')->with(self::callback(
            static fn (Message $message): bool => str_contains((string) json_encode($message, JSON_UNESCAPED_UNICODE), self::SOURCE_ID),
        ))->willReturn($this->responseStream());

        $model = new NeuronGemmaChatModel($provider, 'gemma-4-26b-a4b-it');
        $answer = implode('', iterator_to_array($model->stream('Как загрузить CSV?', [[
            'source_key' => self::SOURCE_ID,
            'title' => 'Импорт',
            'content' => 'Откройте раздел импорта.',
        ]])));

        self::assertSame('Откройте раздел импорта.', $answer);
        self::assertSame([self::SOURCE_ID], $model->citedSourceIds());
        self::assertSame(['input_tokens' => 30, 'output_tokens' => 8, 'reasoning_tokens' => 2], $model->usage());
    }

    public function test_it_yields_text_before_the_provider_stream_completes(): void
    {
        $probe = (object) ['completed' => false];
        $provider = $this->createMock(AIProviderInterface::class);
        $provider->method('systemPrompt')->willReturnSelf();
        $provider->method('stream')->willReturn($this->incrementalResponseStream($probe));
        $stream = (new NeuronGemmaChatModel($provider, 'gemma-4-26b-a4b-it'))->stream('Вопрос', [[
            'source_key' => self::SOURCE_ID,
            'title' => 'Документ',
            'content' => 'Разрешённый контекст.',
        ]]);

        $stream->rewind();

        self::assertNotSame('', $stream->current());
        self::assertFalse($probe->completed);
    }

    /** @return Generator<int, TextChunk, mixed, AssistantMessage> */
    private function responseStream(): Generator
    {
        yield new TextChunk('message-1', 'Откройте раздел ');
        yield new TextChunk('message-1', 'импорта.'."\n[[sources:");
        yield new TextChunk('message-1', self::SOURCE_ID.']]');

        return (new AssistantMessage('complete'))->setUsage(new Usage(30, 8, 0, 2));
    }

    /** @return Generator<int, TextChunk, mixed, AssistantMessage> */
    private function incrementalResponseStream(object $probe): Generator
    {
        yield new TextChunk('message-1', str_repeat('Длинный ответ. ', 6));
        yield new TextChunk('message-1', '[[sources:'.self::SOURCE_ID.']]');
        $probe->completed = true;

        return (new AssistantMessage('complete'))->setUsage(new Usage(30, 8));
    }
}
