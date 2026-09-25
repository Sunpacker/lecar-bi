<?php

declare(strict_types=1);

namespace NotificationService\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use NotificationService\Notification\Infrastructure\Persistence\NotificationModel;
use NotificationService\Tests\TestCase;

final class NotificationApiTest extends TestCase
{
    use RefreshDatabase {
        refreshDatabase as traitRefreshDatabase;
    }

    public function refreshDatabase(): void
    {
        if (! extension_loaded('pdo_sqlite')) {
            return;
        }

        $this->traitRefreshDatabase();
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('PDO sqlite driver not available on host CLI; verified via Docker container');
        }
    }

    private function authHeaders(string $userId = 'user-1', string $workspaceId = 'ws-1'): array
    {
        return [
            'X-Server-Secret' => 'test-notification-secret-key-12345',
            'X-User-Id' => $userId,
            'X-Workspace-Id' => $workspaceId,
        ];
    }

    public function test_unauthorized_without_secret_returns_401(): void
    {
        $response = $this->getJson('/api/v1/notifications');
        $response->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHORIZED');
    }

    public function test_missing_user_or_workspace_context_returns_400(): void
    {
        $response = $this->withHeader('X-Server-Secret', 'test-notification-secret-key-12345')
            ->getJson('/api/v1/notifications');
        $response->assertStatus(400)
            ->assertJsonPath('code', 'BAD_REQUEST');
    }

    public function test_list_notifications_and_unread_count(): void
    {
        $now = CarbonImmutable::now();

        // Notification 1: info
        NotificationModel::create([
            'id' => '00000000-0000-0000-0000-000000000001',
            'source_event_id' => 'evt-1',
            'workspace_id' => 'ws-1',
            'alert_id' => 'alert-1',
            'severity' => 'info',
            'title' => 'Инфо уведомление',
            'body' => 'Текст инфо',
            'analytical_context' => [],
            'occurred_at' => $now->subMinutes(10),
            'created_at' => $now->subMinutes(10),
        ]);

        // Notification 2: critical
        NotificationModel::create([
            'id' => '00000000-0000-0000-0000-000000000002',
            'source_event_id' => 'evt-2',
            'workspace_id' => 'ws-1',
            'alert_id' => 'alert-2',
            'severity' => 'critical',
            'title' => 'Критический остаток',
            'body' => 'Товар закончился',
            'analytical_context' => [],
            'occurred_at' => $now->subMinutes(5),
            'created_at' => $now->subMinutes(5),
        ]);

        // Notification 3: other workspace
        NotificationModel::create([
            'id' => '00000000-0000-0000-0000-000000000003',
            'source_event_id' => 'evt-3',
            'workspace_id' => 'ws-2',
            'alert_id' => 'alert-3',
            'severity' => 'critical',
            'title' => 'Другое пространство',
            'body' => 'Не должно быть видно',
            'analytical_context' => [],
            'occurred_at' => $now->subMinutes(2),
            'created_at' => $now->subMinutes(2),
        ]);

        // List for ws-1
        $response = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/notifications');

        $response->assertStatus(200)
            ->assertJsonPath('total', 2)
            ->assertJsonPath('unread_count', 2)
            ->assertJsonCount(2, 'items');

        // Unread count endpoint
        $countResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertStatus(200)
            ->assertJsonPath('unread_count', 2);
    }

    public function test_mark_read_and_unread_filtering(): void
    {
        $now = CarbonImmutable::now();
        $notif = NotificationModel::create([
            'id' => '00000000-0000-0000-0000-000000000010',
            'source_event_id' => 'evt-10',
            'workspace_id' => 'ws-1',
            'alert_id' => 'alert-10',
            'severity' => 'warning',
            'title' => 'Предупреждение',
            'body' => 'Текст',
            'analytical_context' => [],
            'occurred_at' => $now,
            'created_at' => $now,
        ]);

        // Mark single read
        $readResponse = $this->withHeaders($this->authHeaders())
            ->postJson("/api/v1/notifications/{$notif->id}/read");
        $readResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        // Check unread count is 0
        $countResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertStatus(200)
            ->assertJsonPath('unread_count', 0);

        // Unread only filter returns 0 items
        $filterResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/notifications?unread_only=true');
        $filterResponse->assertStatus(200)
            ->assertJsonCount(0, 'items');

        // Another user still sees it as unread
        $otherUserResponse = $this->withHeaders($this->authHeaders(userId: 'user-2'))
            ->getJson('/api/v1/notifications/unread-count');
        $otherUserResponse->assertStatus(200)
            ->assertJsonPath('unread_count', 1);
    }

    public function test_mark_all_read(): void
    {
        $now = CarbonImmutable::now();
        NotificationModel::create([
            'id' => '00000000-0000-0000-0000-000000000021',
            'source_event_id' => 'evt-21',
            'workspace_id' => 'ws-1',
            'alert_id' => 'alert-21',
            'severity' => 'info',
            'title' => '1',
            'body' => '1',
            'analytical_context' => [],
            'occurred_at' => $now->subMinutes(2),
            'created_at' => $now->subMinutes(2),
        ]);
        NotificationModel::create([
            'id' => '00000000-0000-0000-0000-000000000022',
            'source_event_id' => 'evt-22',
            'workspace_id' => 'ws-1',
            'alert_id' => 'alert-22',
            'severity' => 'warning',
            'title' => '2',
            'body' => '2',
            'analytical_context' => [],
            'occurred_at' => $now->subMinutes(1),
            'created_at' => $now->subMinutes(1),
        ]);

        $response = $this->withHeaders($this->authHeaders())
            ->postJson('/api/v1/notifications/read-all');

        $response->assertStatus(200)
            ->assertJsonPath('updated_count', 2);

        $countResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/notifications/unread-count');
        $countResponse->assertStatus(200)
            ->assertJsonPath('unread_count', 0);
    }

    public function test_notification_preferences_exclude_levels(): void
    {
        // 1. Get default preferences
        $prefResponse = $this->withHeaders($this->authHeaders())
            ->getJson('/api/v1/notification-preferences');

        $prefResponse->assertStatus(200)
            ->assertJsonPath('preferences.info', true)
            ->assertJsonPath('preferences.warning', true)
            ->assertJsonPath('preferences.critical', true);

        // 2. Disable info and warning
        $updateResponse = $this->withHeaders($this->authHeaders())
            ->putJson('/api/v1/notification-preferences', [
                'preferences' => [
                    'info' => false,
                    'warning' => false,
                    'critical' => true,
                ],
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('preferences.info', false)
            ->assertJsonPath('preferences.critical', true);
    }
}
