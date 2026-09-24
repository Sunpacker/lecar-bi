<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Support;

use App\Modules\KnowledgeBase\Application\Contracts\KnowledgeRetriever;
use App\Modules\Support\Application\Contracts\ChatModel;
use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use App\Modules\Support\Application\GenerationProcessor;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryWorkspaceRepository;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class GenerationProcessorTest extends TestCase
{
    public function test_no_context_is_persisted_without_chat_model_invocation(): void
    {
        $repository = $this->createMock(SupportRepositoryInterface::class);
        $repository->expects(self::once())->method('claim')->willReturn(true);
        $repository->expects(self::once())->method('generationContext')->willReturn([
            'workspace_id' => 'workspace-1',
            'owner_user_id' => 'user-1',
            'question' => 'Вопрос вне корпуса',
        ]);
        $repository->expects(self::once())->method('recordRetrieval')->willReturn(true);
        $repository->expects(self::once())->method('completeNoContext')->with(
            'generation-1',
            self::isType('string'),
            self::stringContains('недостаточно данных'),
            self::greaterThan(0),
        )->willReturn(true);
        $repository->expects(self::never())->method('appendText');

        $retriever = $this->createMock(KnowledgeRetriever::class);
        $retriever->expects(self::once())->method('retrieve')->willReturn([
            'build_id' => null,
            'chunks' => [],
            'trace' => ['selected_chunk_ids' => []],
        ]);
        $retriever->expects(self::never())->method('areChunksAvailable');

        $chatModel = $this->createMock(ChatModel::class);
        $chatModel->method('provider')->willReturn('fake');
        $chatModel->method('model')->willReturn('fake-v1');
        $chatModel->expects(self::never())->method('stream');
        $chatModel->expects(self::never())->method('citedSourceIds');

        $this->processor($repository, $retriever, $chatModel)->process('generation-1');
    }

    public function test_revoked_retrieval_fails_before_chat_model_invocation(): void
    {
        $repository = $this->createMock(SupportRepositoryInterface::class);
        $repository->method('claim')->willReturn(true);
        $repository->method('generationContext')->willReturn([
            'workspace_id' => 'workspace-1',
            'owner_user_id' => 'user-1',
            'question' => 'Как загрузить CSV?',
        ]);
        $repository->expects(self::once())->method('recordRetrieval')->willReturn(true);
        $repository->expects(self::once())->method('fail')->with(
            'generation-1',
            self::isType('string'),
            'SOURCE_REVOKED',
            true,
            false,
        )->willReturn(true);

        $retriever = $this->createMock(KnowledgeRetriever::class);
        $retriever->method('retrieve')->willReturn($this->retrieval());
        $retriever->expects(self::once())->method('areChunksAvailable')->willReturn(false);

        $chatModel = $this->createMock(ChatModel::class);
        $chatModel->method('provider')->willReturn('fake');
        $chatModel->method('model')->willReturn('fake-v1');
        $chatModel->expects(self::never())->method('stream');

        $this->processor($repository, $retriever, $chatModel)->process('generation-1');
    }

    public function test_answer_is_completed_with_only_a_citation_from_selected_context(): void
    {
        $repository = $this->createMock(SupportRepositoryInterface::class);
        $repository->method('claim')->willReturn(true);
        $repository->method('generationContext')->willReturn([
            'workspace_id' => 'workspace-1',
            'owner_user_id' => 'user-1',
            'question' => 'Как загрузить CSV?',
        ]);
        $repository->method('recordRetrieval')->willReturn(true);
        $repository->expects(self::atLeastOnce())->method('appendText')->willReturn(true);
        $repository->expects(self::once())->method('completeAnswered')->with(
            'generation-1',
            self::isType('string'),
            'Разрешённый ответ',
            self::callback(static fn (array $citations): bool => count($citations) === 1 && $citations[0]['chunk_id'] === 'chunk-1'),
            123,
            51,
        )->willReturn(true);

        $retriever = $this->createMock(KnowledgeRetriever::class);
        $retriever->method('retrieve')->willReturn($this->retrieval());
        $retriever->expects(self::exactly(2))->method('areChunksAvailable')->willReturn(true);

        $chatModel = $this->createMock(ChatModel::class);
        $chatModel->method('provider')->willReturn('fake');
        $chatModel->method('model')->willReturn('fake-v1');
        $chatModel->method('stream')->willReturn(['Разрешённый ответ']);
        $chatModel->method('citedSourceIds')->willReturn(['chunk-1']);
        $chatModel->method('usage')->willReturn(['input_tokens' => 123, 'output_tokens' => 45, 'reasoning_tokens' => 6]);

        $this->processor($repository, $retriever, $chatModel)->process('generation-1');
    }

    private function processor(
        SupportRepositoryInterface $repository,
        KnowledgeRetriever $retriever,
        ChatModel $chatModel,
    ): GenerationProcessor {
        return new GenerationProcessor(
            $repository,
            $retriever,
            $chatModel,
            $this->accessGuard(),
            new NullLogger,
            [
                'queue_deadline_seconds' => 30,
                'generation_deadline_seconds' => 90,
                'lease_seconds' => 30,
                'evidence_token_limit' => 4000,
                'answer_token_limit' => 1000,
            ],
        );
    }

    /** @return array{build_id: string, chunks: list<array<string, mixed>>, trace: array<string, mixed>} */
    private function retrieval(): array
    {
        return [
            'build_id' => 'build-1',
            'chunks' => [[
                'source_key' => 'chunk-1',
                'chunk_id' => 'chunk-1',
                'document_id' => 'doc-1',
                'revision' => 'r1',
                'title' => 'Импорт',
                'url' => '/support/imports',
                'anchor' => 'csv',
                'content' => 'Разрешённый фрагмент',
            ]],
            'trace' => ['selected_chunk_ids' => ['chunk-1']],
        ];
    }

    private function accessGuard(): WorkspaceAccessGuard
    {
        $workspace = new Workspace(new WorkspaceId('workspace-1'), 'Workspace', 'workspace');
        $workspace->addMember(new UserId('user-1'), MembershipRole::VIEWER);
        $repository = new InMemoryWorkspaceRepository;
        $repository->save($workspace);

        return new WorkspaceAccessGuard($repository);
    }
}
