<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Alerting;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class AlertRulesApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

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
        $response = $this->getJson('/api/v1/alert-rules');
        $response->assertStatus(401)
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_create_and_list_alert_rules_with_tenant_isolation(): void
    {
        // 1. Create rule in ws-1
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules', [
            'name' => 'Критический дефицит (DOS < 7)',
            'description' => 'Уведомлять при остатке менее чем на неделю продаж',
            'rule_type' => 'critical_stock',
            'severity' => 'critical',
            'metric' => 'days_of_stock',
            'comparator' => 'lt',
            'threshold_value' => 7.0,
            'warehouse_id' => 'wh-1',
            'is_enabled' => true,
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('rule.name', 'Критический дефицит (DOS < 7)')
            ->assertJsonPath('rule.workspace_id', 'ws-1')
            ->assertJsonPath('rule.severity', 'critical')
            ->assertJsonPath('rule.is_enabled', true);

        $ruleId = $createResponse->json('rule.id');
        $this->assertNotEmpty($ruleId);

        // 2. List in ws-1 should contain this rule
        $listResponse1 = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/alert-rules');

        $listResponse1->assertStatus(200)
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $ruleId);

        // 3. List in ws-2 should be empty (tenant isolation)
        $listResponse2 = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/alert-rules');

        $listResponse2->assertStatus(200)
            ->assertJsonCount(0, 'items');
    }

    public function test_create_alert_rule_validation_error(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules', [
            'name' => '',
            'rule_type' => 'invalid_type',
            'severity' => 'invalid_severity',
            'metric' => 'invalid_metric',
            'comparator' => 'invalid_comparator',
            'threshold_value' => 'not_a_number',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'rule_type', 'severity', 'metric', 'comparator', 'threshold_value']);
    }

    public function test_get_alert_rule_by_id_and_not_found(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules', [
            'name' => 'Залежалый товар',
            'rule_type' => 'overstock',
            'severity' => 'warning',
            'metric' => 'days_of_stock',
            'comparator' => 'gt',
            'threshold_value' => 60.0,
        ]);

        $createResponse->assertStatus(201);
        $ruleId = $createResponse->json('rule.id');

        // Success get
        $getResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/alert-rules/{$ruleId}");

        $getResponse->assertStatus(200)
            ->assertJsonPath('rule.id', $ruleId)
            ->assertJsonPath('rule.name', 'Залежалый товар');

        // Not found in another workspace
        $notFoundResponse = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson("/api/v1/alert-rules/{$ruleId}");

        $notFoundResponse->assertStatus(404);
    }

    public function test_update_alert_rule(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules', [
            'name' => 'Правило до обновления',
            'rule_type' => 'out_of_stock',
            'severity' => 'warning',
            'metric' => 'quantity_available',
            'comparator' => 'lte',
            'threshold_value' => 5.0,
        ]);

        $createResponse->assertStatus(201);
        $ruleId = $createResponse->json('rule.id');

        $updateResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->putJson("/api/v1/alert-rules/{$ruleId}", [
            'name' => 'Обновленное правило',
            'description' => 'Новое описание',
            'severity' => 'critical',
            'threshold_value' => 10.0,
            'is_enabled' => false,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('rule.id', $ruleId)
            ->assertJsonPath('rule.name', 'Обновленное правило')
            ->assertJsonPath('rule.description', 'Новое описание')
            ->assertJsonPath('rule.severity', 'critical')
            ->assertJsonPath('rule.is_enabled', false);

        self::assertEquals(10.0, (float) $updateResponse->json('rule.threshold_value'));
    }

    public function test_toggle_alert_rule(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules', [
            'name' => 'Правило для переключения',
            'rule_type' => 'out_of_stock',
            'severity' => 'warning',
            'metric' => 'quantity_available',
            'comparator' => 'lte',
            'threshold_value' => 1.0,
            'is_enabled' => true,
        ]);

        $createResponse->assertStatus(201);
        $ruleId = $createResponse->json('rule.id');

        // Toggle (inverts status)
        $toggleResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/alert-rules/{$ruleId}/toggle");

        $toggleResponse->assertStatus(200)
            ->assertJsonPath('rule.is_enabled', false);

        // Toggle again
        $toggleAgainResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/alert-rules/{$ruleId}/toggle");

        $toggleAgainResponse->assertStatus(200)
            ->assertJsonPath('rule.is_enabled', true);
    }

    public function test_delete_alert_rule(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules', [
            'name' => 'Правило для удаления',
            'rule_type' => 'out_of_stock',
            'severity' => 'info',
            'metric' => 'quantity_available',
            'comparator' => 'lte',
            'threshold_value' => 0.0,
        ]);

        $createResponse->assertStatus(201);
        $ruleId = $createResponse->json('rule.id');

        $deleteResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->deleteJson("/api/v1/alert-rules/{$ruleId}");

        $deleteResponse->assertStatus(204);

        // Should return 404 now
        $getResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/alert-rules/{$ruleId}");

        $getResponse->assertStatus(404);
    }

    public function test_evaluate_alert_rules_endpoint(): void
    {
        // 1. Create rule in ws-1
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules', [
            'name' => 'Нулевой остаток',
            'rule_type' => 'out_of_stock',
            'severity' => 'critical',
            'metric' => 'quantity_available',
            'comparator' => 'lte',
            'threshold_value' => 0.0,
        ]);

        // 2. Trigger evaluate
        $evalResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/alert-rules/evaluate');

        $evalResponse->assertStatus(200)
            ->assertJsonStructure([
                'rules_evaluated',
                'alerts_triggered',
                'alerts_created',
                'alerts_updated',
            ]);
    }
}
