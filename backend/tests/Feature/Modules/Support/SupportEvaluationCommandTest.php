<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Support;

use App\Modules\KnowledgeBase\Application\Contracts\KnowledgeRetriever;
use App\Modules\Support\Application\Contracts\ChatModel;
use Tests\TestCase;

final class SupportEvaluationCommandTest extends TestCase
{
    public function test_citation_validity_covers_every_model_invocation(): void
    {
        $datasetPath = tempnam(sys_get_temp_dir(), 'support-evaluation-dataset-');
        $outputPath = tempnam(sys_get_temp_dir(), 'support-evaluation-result-');
        self::assertIsString($datasetPath);
        self::assertIsString($outputPath);

        try {
            file_put_contents($datasetPath, json_encode([
                'revision' => 'test-v1',
                'questions' => [
                    ['id' => 'answered', 'kind' => 'answerable', 'question' => 'Known?', 'sources' => ['guide']],
                    ['id' => 'empty', 'kind' => 'no_context', 'question' => 'Unknown?', 'sources' => []],
                ],
            ], JSON_THROW_ON_ERROR));
            $this->app->instance(KnowledgeRetriever::class, $this->retriever());
            $this->app->instance(ChatModel::class, $this->chatModelWithInvalidCitation());

            $this->artisan('support:evaluate', [
                '--dataset' => $datasetPath,
                '--output' => $outputPath,
                '--delay-ms' => 0,
                '--max-questions' => 2,
            ])->assertSuccessful();

            $report = json_decode((string) file_get_contents($outputPath), true, flags: JSON_THROW_ON_ERROR);
            self::assertSame(1, $report['summary']['citation_attempts']);
            self::assertSame(0, $report['summary']['citation_validity']);
            self::assertSame('failed', $report['results'][0]['outcome']);
            self::assertFalse($report['results'][0]['citations_valid']);
            self::assertTrue($report['results'][0]['model_invoked']);
            self::assertSame('no_context', $report['results'][1]['outcome']);
            self::assertFalse($report['results'][1]['model_invoked']);
        } finally {
            @unlink($datasetPath);
            @unlink($outputPath);
        }
    }

    private function retriever(): KnowledgeRetriever
    {
        return new class implements KnowledgeRetriever
        {
            public function retrieve(string $workspaceId, string $question, int $tokenBudget): array
            {
                if ($question === 'Unknown?') {
                    return ['build_id' => 'build-1', 'chunks' => [], 'trace' => []];
                }

                return [
                    'build_id' => 'build-1',
                    'chunks' => [[
                        'document_id' => 'guide',
                        'source_key' => '11111111-1111-4111-8111-111111111111',
                        'title' => 'Guide',
                        'content' => 'Known answer.',
                    ]],
                    'trace' => [],
                ];
            }

            public function areChunksAvailable(string $workspaceId, string $buildId, array $chunkIds): bool
            {
                return true;
            }
        };
    }

    private function chatModelWithInvalidCitation(): ChatModel
    {
        return new class implements ChatModel
        {
            public function stream(string $question, array $context): iterable
            {
                yield 'Known answer.';
            }

            public function citedSourceIds(): array
            {
                return ['22222222-2222-4222-8222-222222222222'];
            }

            public function usage(): ?array
            {
                return ['input_tokens' => 8, 'output_tokens' => 3, 'reasoning_tokens' => 0];
            }

            public function provider(): string
            {
                return 'google-gemini';
            }

            public function model(): string
            {
                return 'gemma-test';
            }
        };
    }
}
