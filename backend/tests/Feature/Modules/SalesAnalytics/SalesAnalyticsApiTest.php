<?php

namespace Tests\Feature\Modules\SalesAnalytics;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class SalesAnalyticsApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $wsRepo = $this->app->make(WorkspaceRepositoryInterface::class);

        $user1 = new User(new UserId('user-1'), 'elena@autobi.internal', 'Elena Rostova');
        $userRepo->save($user1);

        $ws1 = new Workspace(new WorkspaceId('ws-1'), 'AutoParts Retail', 'autoparts-retail');
        $ws1->addMember(new UserId('user-1'), MembershipRole::OWNER);
        $wsRepo->save($ws1);

        $ws2 = new Workspace(new WorkspaceId('ws-2'), 'Lecar Wholesale', 'lecar-wholesale');
        $wsRepo->save($ws2);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/analytics/sales/overview')->assertStatus(401);
    }

    public function test_forbidden_workspace_access_returns_403(): void
    {
        $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-2')
            ->getJson('/api/v1/analytics/sales/overview')
            ->assertStatus(403);
    }

    public function test_invalid_date_parameters_return_422(): void
    {
        $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/overview?date_from=invalid-date')
            ->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_sales_overview(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/overview');

        $response->assertOk()
            ->assertJsonStructure([
                'summary' => ['total_revenue', 'order_count', 'average_order_value', 'gross_profit', 'margin_rate'],
                'trend' => [['date', 'revenue', 'order_count']],
                'categories' => [['category_id', 'category_name', 'revenue', 'order_count', 'revenue_share']],
                'regions' => [['region_id', 'region_name', 'region_code', 'revenue', 'order_count', 'revenue_share']],
            ]);
    }

    public function test_authenticated_user_can_fetch_sales_filter_options(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/filters');

        $response->assertOk()
            ->assertJsonStructure([
                'categories' => [['id', 'name']],
                'regions' => [['id', 'name', 'code']],
                'min_date',
                'max_date',
            ]);
    }
}
