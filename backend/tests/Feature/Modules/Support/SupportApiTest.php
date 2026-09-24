<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Support;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class SupportApiTest extends TestCase
{
    private const USER_ONE_ID = 'user-1';

    private const USER_TWO_ID = 'user-2';

    private const WORKSPACE_ONE_ID = 'ws-1';

    private const WORKSPACE_TWO_ID = 'ws-2';

    protected function setUp(): void
    {
        parent::setUp();

        $this->configureTestDatabase();
        config(['support.bff_shared_secret' => 'test-support-bff-secret-with-32-characters']);
        DB::purge();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $this->seedWorkspaceBoundary();
    }

    public function test_conversation_and_message_idempotency_preserve_lifecycle(): void
    {
        $key = 'create-conversation-0001';
        $created = $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, $key))
            ->postJson('/api/v1/support/conversations', ['title' => 'Импорт'])
            ->assertCreated();
        $conversationId = (string) $created->json('conversation.id');

        $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, $key))
            ->postJson('/api/v1/support/conversations', ['title' => 'Импорт'])
            ->assertCreated()
            ->assertJsonPath('conversation.id', $conversationId);

        $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, $key))
            ->postJson('/api/v1/support/conversations', ['title' => 'Другой payload'])
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        $messageKey = 'send-message-key-000001';
        $payload = ['client_message_id' => '00000000-0000-4000-8000-000000000001', 'content' => 'Что такое ABC/XYZ?'];
        $accepted = $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, $messageKey))
            ->postJson("/api/v1/support/conversations/{$conversationId}/messages", $payload)
            ->assertAccepted();
        $generationId = (string) $accepted->json('generation_id');

        $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, $messageKey))
            ->postJson("/api/v1/support/conversations/{$conversationId}/messages", $payload)
            ->assertAccepted()
            ->assertJsonPath('generation_id', $generationId);

        $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, 'different-idempotency-key'))
            ->postJson("/api/v1/support/conversations/{$conversationId}/messages", [
                ...$payload,
                'content' => 'Изменённый вопрос',
            ])
            ->assertConflict()
            ->assertJsonPath('code', 'IDEMPOTENCY_CONFLICT');

        $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID))
            ->getJson("/api/v1/support/generations/{$generationId}")
            ->assertOk()
            ->assertJsonPath('generation.status', 'completed')
            ->assertJsonPath('generation.outcome', 'no_context');

        self::assertSame(1, DB::table('support_generations')->count());
        self::assertSame(1, DB::table('support_budget_reservations')->count());
    }

    public function test_owner_workspace_and_feedback_boundaries_return_not_found(): void
    {
        $created = $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, 'create-conversation-0002'))
            ->postJson('/api/v1/support/conversations')
            ->assertCreated();
        $conversationId = (string) $created->json('conversation.id');

        $accepted = $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID, 'send-message-key-000002'))
            ->postJson("/api/v1/support/conversations/{$conversationId}/messages", [
                'client_message_id' => '00000000-0000-4000-8000-000000000002',
                'content' => 'Как работает импорт?',
            ])
            ->assertAccepted();

        $assistantMessageId = (string) $accepted->json('assistant_message_id');
        $this->withHeaders($this->headers(self::USER_TWO_ID, self::WORKSPACE_ONE_ID))
            ->getJson("/api/v1/support/conversations/{$conversationId}")
            ->assertNotFound();
        $this->withHeaders($this->headers(self::USER_TWO_ID, self::WORKSPACE_ONE_ID))
            ->postJson("/api/v1/support/messages/{$assistantMessageId}/feedback", ['rating' => 'helpful'])
            ->assertNotFound();
        $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_TWO_ID))
            ->getJson("/api/v1/support/conversations/{$conversationId}")
            ->assertForbidden();

        $this->withHeaders($this->headers(self::USER_ONE_ID, self::WORKSPACE_ONE_ID))
            ->postJson("/api/v1/support/messages/{$assistantMessageId}/feedback", ['rating' => 'helpful'])
            ->assertOk()
            ->assertJsonPath('rating', 'helpful');
    }

    /** @return array<string, string> */
    private function headers(string $userId, string $workspaceId, ?string $idempotencyKey = null): array
    {
        $headers = [
            'X-User-Id' => $userId,
            'X-Workspace-Id' => $workspaceId,
            'X-Support-BFF-Key' => 'test-support-bff-secret-with-32-characters',
        ];
        if ($idempotencyKey !== null) {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        return $headers;
    }

    private function seedWorkspaceBoundary(): void
    {
        $users = $this->app->make(UserRepositoryInterface::class);
        $workspaces = $this->app->make(WorkspaceRepositoryInterface::class);
        $users->save(new User(new UserId(self::USER_ONE_ID), 'one@example.test', 'User One'));
        $users->save(new User(new UserId(self::USER_TWO_ID), 'two@example.test', 'User Two'));

        $workspace = new Workspace(new WorkspaceId(self::WORKSPACE_ONE_ID), 'Workspace One', 'workspace-one');
        $workspace->addMember(new UserId(self::USER_ONE_ID), MembershipRole::OWNER);
        $workspace->addMember(new UserId(self::USER_TWO_ID), MembershipRole::VIEWER);
        $workspaces->save($workspace);

        $other = new Workspace(new WorkspaceId(self::WORKSPACE_TWO_ID), 'Workspace Two', 'workspace-two');
        $other->addMember(new UserId(self::USER_TWO_ID), MembershipRole::OWNER);
        $workspaces->save($other);
    }

    private function configureTestDatabase(): void
    {
        if (getenv('RUN_SUPPORT_POSTGRES_INTEGRATION') === '1' && extension_loaded('pdo_pgsql')) {
            config(['database.default' => 'pgsql', 'queue.default' => 'sync']);

            return;
        }

        if (extension_loaded('pdo_sqlite')) {
            config([
                'database.default' => 'sqlite',
                'database.connections.sqlite.database' => ':memory:',
                'queue.default' => 'sync',
            ]);

            return;
        }

        self::markTestSkipped('pdo_sqlite or the dedicated RUN_SUPPORT_POSTGRES_INTEGRATION=1 PostgreSQL environment is required');
    }
}
