<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Alerting;

use App\Modules\Alerting\Domain\Alert;
use App\Modules\Alerting\Domain\AlertContext;
use App\Modules\Alerting\Domain\AlertId;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\AlertStatus;
use App\Modules\Alerting\Domain\DedupFingerprint;
use App\Modules\Alerting\Domain\Repositories\AlertRepositoryInterface;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use DateTimeImmutable;
use Tests\TestCase;

final class AlertsApiTest extends TestCase
{
    private AlertRepositoryInterface $alertRepo;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->alertRepo = $this->app->make(AlertRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $user2 = new User(new UserId('user-2'), 'dmitry@autobi.internal', 'Dmitry Smirnov');
        $userRepo->save($user1);
        $userRepo->save($user2);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $ws2->addMember(new UserId('user-2'), MembershipRole::OWNER);
        $wsRepo->save($ws2);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/alerts');
        $response->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);

        $summaryResponse = $this->getJson('/api/v1/alerts/summary');
        $summaryResponse->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_list_alerts_and_summary_with_tenant_isolation(): void
    {
        // 1. Add alert for ws-1
        $alert1 = new Alert(
            id: new AlertId('alt-1'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Аут-оф-сток: Масляный фильтр',
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-1', 'wh-1'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-1',
                warehouseName: 'Центральный склад',
                productId: 'prod-1',
                productName: 'Масляный фильтр BOSCH',
                productSku: 'FILT-001',
                currentValue: 0.0,
                thresholdValue: 0.0,
            ),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );
        $this->alertRepo->save($alert1);

        // 2. Add alert for ws-2
        $alert2 = new Alert(
            id: new AlertId('alt-2'),
            workspaceId: 'ws-2',
            ruleId: new AlertRuleId('rule-2'),
            ruleName: 'Избыточный запас: Тормозные колодки',
            severity: AlertSeverity::WARNING,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-2', 'rule-2', 'prod-2', 'wh-2'),
            context: new AlertContext(
                target: 'inventory',
                warehouseId: 'wh-2',
                warehouseName: 'Северный склад',
                productId: 'prod-2',
                productName: 'Колодки тормозные передние',
                productSku: 'BRK-002',
                currentValue: 95.0,
                thresholdValue: 60.0,
            ),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );
        $this->alertRepo->save($alert2);

        // 3. Request alerts for ws-1
        $res1 = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/alerts');

        $res1->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', 'alt-1')
            ->assertJsonPath('items.0.workspace_id', 'ws-1')
            ->assertJsonPath('items.0.severity', 'critical')
            ->assertJsonPath('items.0.status', 'open')
            ->assertJsonPath('items.0.product_sku', 'FILT-001');

        // 4. Request summary for ws-1
        $summaryRes1 = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/alerts/summary');

        $summaryRes1->assertStatus(200)
            ->assertJson([
                'total_active' => 1,
                'critical_count' => 1,
                'warning_count' => 0,
                'info_count' => 0,
                'acknowledged_count' => 0,
            ]);

        // 5. Request alerts for ws-2
        $res2 = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/alerts');

        $res2->assertStatus(200)
            ->assertJsonPath('pagination.total', 1)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', 'alt-2')
            ->assertJsonPath('items.0.workspace_id', 'ws-2')
            ->assertJsonPath('items.0.severity', 'warning');
    }

    public function test_get_alert_by_id(): void
    {
        $alert = new Alert(
            id: new AlertId('alt-show'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Предупреждение по запасам',
            severity: AlertSeverity::WARNING,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-123', 'wh-1'),
            context: new AlertContext('inventory', 'wh-1', 'Центральный склад', 'prod-123', 'Товар 123', 'SKU-123', 5.0, 7.0),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );
        $this->alertRepo->save($alert);

        $res = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/alerts/alt-show');

        $res->assertStatus(200)
            ->assertJsonPath('alert.id', 'alt-show')
            ->assertJsonPath('alert.status', 'open');

        // Not found in other workspace
        $res404 = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/alerts/alt-show');

        $res404->assertStatus(404);
    }

    public function test_acknowledge_and_resolve_alert_lifecycle(): void
    {
        $alert = new Alert(
            id: new AlertId('alt-lifecycle'),
            workspaceId: 'ws-1',
            ruleId: new AlertRuleId('rule-1'),
            ruleName: 'Критический дефицит',
            severity: AlertSeverity::CRITICAL,
            status: AlertStatus::OPEN,
            dedupFingerprint: DedupFingerprint::generate('ws-1', 'rule-1', 'prod-lc', 'wh-1'),
            context: new AlertContext('inventory', 'wh-1', 'Центральный склад', 'prod-lc', 'Товар LC', 'SKU-LC', 2.0, 7.0),
            triggeredAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            createdAt: new DateTimeImmutable('2026-09-23 10:00:00'),
            updatedAt: new DateTimeImmutable('2026-09-23 10:00:00'),
        );
        $this->alertRepo->save($alert);

        // 1. Acknowledge
        $ackRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alerts/alt-lifecycle/acknowledge');

        $ackRes->assertStatus(200)
            ->assertJsonPath('alert.status', 'acknowledged')
            ->assertJsonPath('alert.acknowledged_by', 'user-1');
        $this->assertNotNull($ackRes->json('alert.acknowledged_at'));

        // 2. Resolve
        $resolveRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alerts/alt-lifecycle/resolve', [
            'resolution_note' => 'Заказ оформлен у поставщика №12, поставка завтра',
        ]);

        $resolveRes->assertStatus(200)
            ->assertJsonPath('alert.status', 'resolved')
            ->assertJsonPath('alert.resolved_by', 'user-1')
            ->assertJsonPath('alert.resolution_note', 'Заказ оформлен у поставщика №12, поставка завтра');
        $this->assertNotNull($resolveRes->json('alert.resolved_at'));

        // 3. Trying to acknowledge already resolved alert -> 400 INVALID_STATE_TRANSITION
        $badAckRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alerts/alt-lifecycle/acknowledge');

        $badAckRes->assertStatus(400)
            ->assertJsonPath('code', 'INVALID_STATE_TRANSITION');
    }
}
