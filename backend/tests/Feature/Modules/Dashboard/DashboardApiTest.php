<?php

namespace Tests\Feature\Modules\Dashboard;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class DashboardApiTest extends TestCase
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
        $response = $this->getJson('/api/v1/dashboards');
        $response->assertStatus(401)
            ->assertJson([
                'code' => 'UNAUTHENTICATED',
            ]);
    }

    public function test_create_and_list_dashboards_with_tenant_isolation(): void
    {
        // Create dashboard in ws-1
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Дашборд продаж',
            'description' => 'Анализ выручки и заказов',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('dashboard.title', 'Дашборд продаж')
            ->assertJsonPath('dashboard.workspace_id', 'ws-1')
            ->assertJsonPath('dashboard.widgets', []);

        $dashboardId = (string) $createResponse->json('dashboard.id');

        // List dashboards in ws-1
        $listResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/dashboards');

        $listResponse->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.id', $dashboardId)
            ->assertJsonPath('items.0.title', 'Дашборд продаж');

        // User 2 in ws-2 should see 0 dashboards
        $listWs2Response = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/dashboards');

        $listWs2Response->assertOk()
            ->assertJsonCount(0, 'items');
    }

    public function test_get_dashboard_by_id_and_cross_workspace_protection(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Оперативный дашборд',
        ]);

        $dashboardId = (string) $createResponse->json('dashboard.id');

        // User 1 in ws-1 can view dashboard
        $showResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$dashboardId}");

        $showResponse->assertOk()
            ->assertJsonPath('dashboard.id', $dashboardId)
            ->assertJsonPath('dashboard.title', 'Оперативный дашборд');

        // User 2 in ws-2 gets 403 Forbidden when trying to access ws-1's dashboard
        $forbiddenResponse = $this->withHeaders([
            'X-User-Id' => 'user-2',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson("/api/v1/dashboards/{$dashboardId}");

        $forbiddenResponse->assertStatus(403)
            ->assertJson([
                'code' => 'FORBIDDEN',
            ]);
    }

    public function test_update_dashboard_with_semantic_widgets(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Исходный',
        ]);

        $dashboardId = (string) $createResponse->json('dashboard.id');

        $updateResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->putJson("/api/v1/dashboards/{$dashboardId}", [
            'title' => 'Обновленный дашборд',
            'description' => 'Новое описание',
            'widgets' => [
                [
                    'title' => 'Выручка за месяц',
                    'type' => 'kpi_card',
                    'query_config' => [
                        'dataset' => 'sales',
                        'metric' => 'revenue',
                        'date_range' => '30d',
                    ],
                    'position' => [
                        'x' => 0,
                        'y' => 0,
                        'w' => 4,
                        'h' => 2,
                    ],
                    'options' => [
                        'color_scheme' => 'emerald',
                    ],
                ],
                [
                    'title' => 'Динамика заказов',
                    'type' => 'line_chart',
                    'query_config' => [
                        'dataset' => 'sales',
                        'metric' => 'order_count',
                        'dimension' => 'date',
                        'date_range' => '90d',
                    ],
                    'position' => [
                        'x' => 4,
                        'y' => 0,
                        'w' => 8,
                        'h' => 4,
                    ],
                    'options' => [
                        'show_legend' => true,
                    ],
                ],
            ],
        ]);

        $updateResponse->assertOk()
            ->assertJsonPath('dashboard.title', 'Обновленный дашборд')
            ->assertJsonPath('dashboard.description', 'Новое описание')
            ->assertJsonCount(2, 'dashboard.widgets')
            ->assertJsonPath('dashboard.widgets.0.title', 'Выручка за месяц')
            ->assertJsonPath('dashboard.widgets.0.type', 'kpi_card')
            ->assertJsonPath('dashboard.widgets.0.position.w', 4)
            ->assertJsonPath('dashboard.widgets.1.title', 'Динамика заказов')
            ->assertJsonPath('dashboard.widgets.1.type', 'line_chart')
            ->assertJsonPath('dashboard.widgets.1.position.w', 8);
    }

    public function test_update_dashboard_with_invalid_grid_position_returns_422(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Тест сетки',
        ]);

        $dashboardId = (string) $createResponse->json('dashboard.id');

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->putJson("/api/v1/dashboards/{$dashboardId}", [
            'title' => 'Тест сетки',
            'widgets' => [
                [
                    'title' => 'Слишком широкий виджет',
                    'type' => 'kpi_card',
                    'query_config' => [
                        'dataset' => 'sales',
                        'metric' => 'revenue',
                    ],
                    'position' => [
                        'x' => 10,
                        'y' => 0,
                        'w' => 4, // 10 + 4 = 14 > 12
                        'h' => 2,
                    ],
                ],
            ],
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'code' => 'VALIDATION_ERROR',
            ]);
    }

    public function test_delete_dashboard(): void
    {
        $createResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->postJson('/api/v1/dashboards', [
            'title' => 'Будет удален',
        ]);

        $dashboardId = (string) $createResponse->json('dashboard.id');

        $deleteResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->deleteJson("/api/v1/dashboards/{$dashboardId}");

        $deleteResponse->assertNoContent();

        $getDeletedResponse = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson("/api/v1/dashboards/{$dashboardId}");

        $getDeletedResponse->assertStatus(404);
    }

    public function test_non_existent_dashboard_returns_404(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/dashboards/00000000-0000-4000-8000-000000000000');

        $response->assertStatus(404)
            ->assertJson([
                'code' => 'NOT_FOUND',
            ]);
    }
}
