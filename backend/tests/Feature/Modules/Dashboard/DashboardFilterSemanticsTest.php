<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Modules\Dashboard\Domain\Dashboard;
use App\Modules\Dashboard\Domain\DashboardFilters;
use App\Modules\Dashboard\Domain\DashboardId;
use App\Modules\Dashboard\Domain\Repositories\DashboardRepositoryInterface;
use App\Modules\Dashboard\Domain\Repositories\SavedViewRepositoryInterface;
use App\Modules\Dashboard\Domain\SavedView;
use App\Modules\Dashboard\Domain\SavedViewId;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class DashboardFilterSemanticsTest extends TestCase
{
    private DashboardRepositoryInterface $dashboardRepo;

    private SavedViewRepositoryInterface $savedViewRepo;

    private Dashboard $d1;

    private Dashboard $d2;

    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);
        $this->dashboardRepo = $this->app->make(DashboardRepositoryInterface::class);
        $this->savedViewRepo = $this->app->make(SavedViewRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $userRepo->save($user1);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $this->d1 = new Dashboard(id: DashboardId::generate(), workspaceId: 'ws-1', title: 'Dashboard One');
        $this->d2 = new Dashboard(id: DashboardId::generate(), workspaceId: 'ws-1', title: 'Dashboard Two');
        $this->dashboardRepo->save($this->d1);
        $this->dashboardRepo->save($this->d2);
    }

    public function test_cannot_update_or_delete_view_belonging_to_another_dashboard(): void
    {
        // Create view on D1
        $res = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->d1->id()->value()}/views", [
            'name' => 'View On D1',
            'filters' => ['date_range' => '30d'],
        ]);
        $viewId = $res->json('view.id');
        self::assertIsString($viewId);

        // Try to access view 1 via D2 URL -> must return 404
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$this->d2->id()->value()}/views/{$viewId}")
            ->assertStatus(404);

        // Try to update view 1 via D2 URL -> must return 404
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->putJson("/api/v1/dashboards/{$this->d2->id()->value()}/views/{$viewId}", [
            'name' => 'Hacked Name',
            'filters' => ['date_range' => '30d'],
        ])->assertStatus(404);

        // Try to delete view 1 via D2 URL -> must return 404
        $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->deleteJson("/api/v1/dashboards/{$this->d2->id()->value()}/views/{$viewId}")
            ->assertStatus(404);
    }

    public function test_invalid_date_span_validation(): void
    {
        $res = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->d1->id()->value()}/views", [
            'name' => 'Invalid Span View',
            'filters' => [
                'date_from' => '2026-05-10',
                'date_to' => '2026-05-01',
            ],
        ]);

        $res->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_default_flag_scoped_per_dashboard(): void
    {
        // Create default view on D1
        $view1 = new SavedView(
            id: SavedViewId::generate(),
            dashboardId: $this->d1->id(),
            name: 'D1 Default',
            filters: new DashboardFilters(dateRange: '30d'),
            isDefault: true,
        );
        $this->savedViewRepo->save($view1);

        // Create default view on D2 via API
        $res = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson("/api/v1/dashboards/{$this->d2->id()->value()}/views", [
            'name' => 'D2 Default',
            'filters' => ['date_range' => '90d'],
            'is_default' => true,
        ]);
        $res->assertStatus(201);

        // Verify D1's view is STILL default (not affected by D2)
        $d1View = $this->savedViewRepo->findById($view1->id());
        self::assertNotNull($d1View);
        self::assertTrue($d1View->isDefault());
    }
}
