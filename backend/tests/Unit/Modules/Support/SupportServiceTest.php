<?php

declare(strict_types=1);

namespace Tests\Unit\Modules\Support;

use App\Modules\Support\Application\Contracts\GenerationDispatcherInterface;
use App\Modules\Support\Application\Contracts\SupportRepositoryInterface;
use App\Modules\Support\Application\SupportService;
use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use App\Modules\Workspace\Infrastructure\Persistence\InMemory\InMemoryWorkspaceRepository;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SupportServiceTest extends TestCase
{
    public function test_access_is_checked_before_the_repository(): void
    {
        $repository = $this->createMock(SupportRepositoryInterface::class);
        $repository->expects(self::never())->method('listConversations');

        $service = $this->service($repository, $this->createMock(GenerationDispatcherInterface::class));

        $this->expectException(UnauthorizedWorkspaceAccessException::class);
        $service->listConversations('workspace-1', 'intruder', null, 20);
    }

    public function test_committed_admission_survives_initial_queue_dispatch_failure(): void
    {
        $repository = $this->createMock(SupportRepositoryInterface::class);
        $repository->expects(self::once())->method('admitMessage')->willReturn([
            'message_id' => 'message-1',
            'assistant_message_id' => 'message-2',
            'generation_id' => 'generation-1',
        ]);
        $dispatcher = $this->createMock(GenerationDispatcherInterface::class);
        $dispatcher->expects(self::once())->method('dispatch')->with('generation-1')->willThrowException(new RuntimeException('queue unavailable'));

        $result = $this->service($repository, $dispatcher)->sendMessage(
            'workspace-1',
            'user-1',
            'conversation-1',
            '00000000-0000-4000-8000-000000000001',
            'Как загрузить CSV?',
            'idempotency-key-0001',
        );

        self::assertSame('generation-1', $result['generation_id']);
    }

    private function service(
        SupportRepositoryInterface $repository,
        GenerationDispatcherInterface $dispatcher,
    ): SupportService {
        $workspace = new Workspace(new WorkspaceId('workspace-1'), 'Workspace', 'workspace');
        $workspace->addMember(new UserId('user-1'), MembershipRole::VIEWER);
        $workspaces = new InMemoryWorkspaceRepository;
        $workspaces->save($workspace);

        return new SupportService(
            new WorkspaceAccessGuard($workspaces),
            $repository,
            $dispatcher,
            [
                'question_character_limit' => 4000,
                'question_token_limit' => 2000,
            ],
        );
    }
}
