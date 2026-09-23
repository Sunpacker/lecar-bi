<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\Workspace;

use App\Modules\Alerting\Domain\AlertRule;
use App\Modules\Alerting\Domain\AlertRuleId;
use App\Modules\Alerting\Domain\AlertSeverity;
use App\Modules\Alerting\Domain\Repositories\AlertRuleRepositoryInterface;
use App\Modules\Alerting\Domain\RuleComparator;
use App\Modules\Alerting\Domain\RuleCondition;
use App\Modules\Alerting\Domain\RuleMetric;
use App\Modules\Alerting\Domain\RuleScope;
use App\Modules\Alerting\Domain\RuleType;
use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\DataIngestion\Domain\DatasetType;
use App\Modules\DataIngestion\Domain\ImportBatch;
use App\Modules\DataIngestion\Domain\ImportBatchId;
use App\Modules\DataIngestion\Domain\ImportStatus;
use App\Modules\DataIngestion\Domain\Repositories\ImportBatchRepositoryInterface;
use App\Modules\DataIngestion\Domain\SourceFormat;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use DateTimeImmutable;
use Tests\TestCase;

final class WorkspaceCapabilityMatrixTest extends TestCase
{
    private WorkspaceRepositoryInterface $wsRepo;

    private UserRepositoryInterface $userRepo;

    private string $dashId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->userRepo = $this->app->make(UserRepositoryInterface::class);

        // Users
        $owner = new User(new UserId('u-owner'), 'owner@autobi.local', 'Owner User');
        $member = new User(new UserId('u-member'), 'member@autobi.local', 'Member User');
        $viewer = new User(new UserId('u-viewer'), 'viewer@autobi.local', 'Viewer User');
        $stranger = new User(new UserId('u-stranger'), 'stranger@autobi.local', 'Stranger User');

        $this->userRepo->save($owner);
        $this->userRepo->save($member);
        $this->userRepo->save($viewer);
        $this->userRepo->save($stranger);

        // Main workspace
        $ws1 = new Workspace(new WorkspaceId('ws-matrix'), 'Matrix WS', 'matrix-ws');
        $ws1->addMember(new UserId('u-owner'), MembershipRole::OWNER);
        $ws1->addMember(new UserId('u-member'), MembershipRole::MEMBER);
        $ws1->addMember(new UserId('u-viewer'), MembershipRole::VIEWER);
        $this->wsRepo->save($ws1);

        // Other workspace
        $ws2 = new Workspace(new WorkspaceId('ws-other'), 'Other WS', 'other-ws');
        $ws2->addMember(new UserId('u-stranger'), MembershipRole::OWNER);
        $this->wsRepo->save($ws2);

        // Seed a dashboard
        $this->dashId = DashboardId::generate()->value();
        $dashRepo = $this->app->make(DashboardRepositoryInterface::class);
        $dash = new Dashboard(
            id: new DashboardId($this->dashId),
            workspaceId: 'ws-matrix',
            title: 'Test Dashboard',
            description: null,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
        $dashRepo->save($dash);

        // Seed an alert rule
        $ruleRepo = $this->app->make(AlertRuleRepositoryInterface::class);
        $rule = new AlertRule(
            id: new AlertRuleId('rule-1'),
            workspaceId: 'ws-matrix',
            name: 'Low Stock Alert',
            description: null,
            ruleType: RuleType::CRITICAL_STOCK,
            severity: AlertSeverity::WARNING,
            condition: new RuleCondition(RuleMetric::QUANTITY_AVAILABLE, RuleComparator::LESS_THAN, 10.0),
            scope: new RuleScope,
            isEnabled: true,
            createdAt: new DateTimeImmutable,
            updatedAt: new DateTimeImmutable,
        );
        $ruleRepo->save($rule);

        // Seed an import batch
        $batchRepo = $this->app->make(ImportBatchRepositoryInterface::class);
        $batch = new ImportBatch(
            id: ImportBatchId::fromString('batch-1'),
            workspaceId: 'ws-matrix',
            datasetType: DatasetType::SALES,
            sourceFormat: SourceFormat::CSV,
            originalFilename: 'test.csv',
            storedFilePath: '/tmp/test.csv',
            status: ImportStatus::FAILED,
            totalRows: 10,
            processedRows: 10,
            successfulRows: 0,
            failedRows: 10,
            errorMessage: 'Test error',
            createdAt: new DateTimeImmutable,
            completedAt: new DateTimeImmutable,
        );
        $batchRepo->save($batch);
    }

    public function test_viewer_can_read_resources_but_cannot_mutate(): void
    {
        $headers = ['X-User-Id' => 'u-viewer', 'X-Workspace-Id' => 'ws-matrix'];

        // Read routes return 200
        $this->withHeaders($headers)->getJson('/api/v1/analytics/sales/overview')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/analytics/inventory/summary')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/analytics/suppliers/overview')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/dashboards')->assertOk();
        $this->withHeaders($headers)->getJson("/api/v1/dashboards/{$this->dashId}")->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/imports')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/imports/batch-1')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/alert-rules')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/alerts')->assertOk();

        // Mutation routes return 403 INSUFFICIENT_CAPABILITY
        $this->withHeaders($headers)->postJson('/api/v1/dashboards', [
            'title' => 'Forbidden Dashboard',
        ])->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->putJson("/api/v1/dashboards/{$this->dashId}", [
            'title' => 'Updated Dashboard',
        ])->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->deleteJson("/api/v1/dashboards/{$this->dashId}")
            ->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->postJson('/api/v1/alert-rules', [
            'name' => 'Forbidden Rule',
            'rule_type' => 'critical_stock',
            'severity' => 'critical',
            'metric' => 'quantity_available',
            'comparator' => 'less_than',
            'threshold_value' => 5,
        ])->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->postJson('/api/v1/alert-rules/rule-1/toggle')
            ->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->postJson('/api/v1/alert-rules/evaluate')
            ->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->postJson('/api/v1/imports/batch-1/retry')
            ->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->getJson('/api/v1/workspaces/ws-matrix/members')
            ->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);
    }

    public function test_member_can_read_and_mutate_resources_but_cannot_manage_members(): void
    {
        $headers = ['X-User-Id' => 'u-member', 'X-Workspace-Id' => 'ws-matrix'];

        // Read works
        $this->withHeaders($headers)->getJson('/api/v1/dashboards')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/alert-rules')->assertOk();

        // Dashboard mutation works
        $createDashResponse = $this->withHeaders($headers)->postJson('/api/v1/dashboards', [
            'title' => 'Member Dashboard',
        ]);
        $createDashResponse->assertStatus(201);

        // Alert rule toggle works
        $this->withHeaders($headers)->postJson('/api/v1/alert-rules/rule-1/toggle')
            ->assertOk();

        // Workspace member management is forbidden for member
        $this->withHeaders($headers)->getJson('/api/v1/workspaces/ws-matrix/members')
            ->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);

        $this->withHeaders($headers)->patchJson('/api/v1/workspaces/ws-matrix/members/u-viewer/role', [
            'role' => 'member',
        ])->assertStatus(403)->assertJson(['code' => 'INSUFFICIENT_CAPABILITY']);
    }

    public function test_owner_can_perform_all_operations_including_members(): void
    {
        $headers = ['X-User-Id' => 'u-owner', 'X-Workspace-Id' => 'ws-matrix'];

        $this->withHeaders($headers)->getJson('/api/v1/dashboards')->assertOk();
        $this->withHeaders($headers)->getJson('/api/v1/workspaces/ws-matrix/members')->assertOk();

        $this->withHeaders($headers)->patchJson('/api/v1/workspaces/ws-matrix/members/u-viewer/role', [
            'role' => 'member',
        ])->assertOk();
    }

    public function test_cross_workspace_requests_are_forbidden_for_all_roles(): void
    {
        $headers = ['X-User-Id' => 'u-stranger', 'X-Workspace-Id' => 'ws-matrix'];

        $this->withHeaders($headers)->getJson('/api/v1/analytics/sales/overview')
            ->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);

        $this->withHeaders($headers)->getJson('/api/v1/dashboards')
            ->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);

        $this->withHeaders($headers)->postJson('/api/v1/dashboards', ['title' => 'Sneak'])
            ->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);

        $this->withHeaders($headers)->getJson('/api/v1/imports')
            ->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);

        $this->withHeaders($headers)->getJson('/api/v1/alert-rules')
            ->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);

        $this->withHeaders($headers)->getJson('/api/v1/workspaces/ws-matrix/members')
            ->assertStatus(403)->assertJson(['code' => 'FORBIDDEN']);
    }
}
