<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class DashboardSavedViewApiTest extends TestCase
{
    private DashboardRepositoryInterface $dashboardRepo;

    private Dashboard $dashboardWs1;

    private Dashboard $dashboardWs2;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->dashboardRepo = $this->app->make(DashboardRepositoryInterface::class);

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

        $this->dashboardWs1 = new Dashboard(
            id: DashboardId::generate(),
            workspaceId: 'ws-1',
            title: 'WS 1 Dashboard',
        );
        $this->dashboardRepo->save($this->dashboardWs1);

        $this->dashboardWs2 = new Dashboard(
            id: DashboardId::generate(),
            workspaceId: 'ws-2',
            title: 'WS 2 Dashboard',
        );
        $this->dashboardRepo->save($this->dashboardWs2);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views");
        $response->assertStatus(401);
    }

    public function test_saved_view_crud_flow_and_default_toggle(): void
    {
        // 1. Create View 1 (default)
        $createRes1 = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views", [
            'name' => 'Monthly Report View',
            'filters' => [
                'date_range' => '30d',
                'category_id' => 'cat-1',
            ],
            'is_default' => true,
        ]);

        $createRes1->assertStatus(201)
            ->assertJsonPath('view.name', 'Monthly Report View')
            ->assertJsonPath('view.is_default', true)
            ->assertJsonPath('view.filters.date_range', '30d')
            ->assertJsonPath('view.filters.category_id', 'cat-1');

        $viewId1 = $createRes1->json('view.id');
        self::assertIsString($viewId1);

        // 2. Create View 2 (also set as default -> should unset View 1 default)
        $createRes2 = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views", [
            'name' => 'Quarterly Warehouse View',
            'filters' => [
                'date_range' => '90d',
                'warehouse_id' => 'wh-1',
            ],
            'is_default' => true,
        ]);
        $createRes2->assertStatus(201)
            ->assertJsonPath('view.name', 'Quarterly Warehouse View')
            ->assertJsonPath('view.is_default', true);

        $viewId2 = $createRes2->json('view.id');

        // 3. List Views
        $listRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views");

        $listRes->assertStatus(200)
            ->assertJsonCount(2, 'items');

        // Verify View 1 default was cleared
        $getRes1 = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views/{$viewId1}");

        $getRes1->assertStatus(200)
            ->assertJsonPath('view.is_default', false);

        // 4. Update View 1
        $updateRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->putJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views/{$viewId1}", [
            'name' => 'Updated Monthly View',
            'filters' => [
                'date_range' => '180d',
                'region_id' => 'reg-north',
            ],
            'is_default' => false,
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('view.name', 'Updated Monthly View')
            ->assertJsonPath('view.filters.date_range', '180d')
            ->assertJsonPath('view.filters.region_id', 'reg-north');

        // 5. Delete View 2
        $delRes = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->deleteJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views/{$viewId2}");

        $delRes->assertStatus(204);

        // 6. Verify View 2 is 404
        $getRes2 = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views/{$viewId2}");

        $getRes2->assertStatus(404);
    }

    public function test_workspace_isolation_and_authorization(): void
    {
        // user-2 cannot list views from ws-1 dashboard (even if specifying ws-1) -> 403
        $response = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views");

        $response->assertStatus(403);

        // user-2 with ws-2 cannot access ws-1 dashboard -> 404 (not found in ws-2)
        $response404 = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views");

        $response404->assertStatus(404);
    }

    public function test_validation_errors_on_create_view(): void
    {
        // Missing name and filters
        $res = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views", []);

        $res->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // Invalid date span (date_from > date_to)
        $resInvalidDates = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->dashboardWs1->id()->value()}/views", [
            'name' => 'Invalid Dates',
            'filters' => [
                'date_from' => '2026-06-15',
                'date_to' => '2026-06-01',
            ],
        ]);

        $resInvalidDates->assertStatus(422);
    }
}
