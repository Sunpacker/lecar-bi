<?php

namespace Tests\Feature\Modules\InventoryAnalytics;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class InventoryAnalyticsApiTest extends TestCase
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

    public function test_inventory_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/analytics/inventory/summary');
        $response->assertStatus(401);
    }

    public function test_inventory_summary_enforces_workspace_access_boundary(): void
    {
        // user-1 belongs to ws-1, forbidden to access ws-2
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/analytics/inventory/summary');

        $response->assertStatus(403);
    }

    public function test_inventory_summary_returns_aggregated_metrics(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'total_items',
                    'total_quantity_on_hand',
                    'total_quantity_reserved',
                    'total_quantity_available',
                    'total_inventory_value',
                    'critical_count',
                    'overstock_count',
                    'out_of_stock_count',
                    'optimal_count',
                    'average_days_of_stock',
                ],
                'health_breakdown' => [
                    '*' => ['status', 'label', 'items_count', 'total_value', 'share'],
                ],
                'warehouses' => [
                    '*' => [
                        'warehouse_id',
                        'warehouse_name',
                        'warehouse_code',
                        'total_quantity',
                        'total_value',
                        'items_count',
                        'critical_count',
                        'overstock_count',
                    ],
                ],
                'as_of_date',
            ]);
    }

    public function test_inventory_items_returns_paginated_records(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/items?page=1&per_page=10&sort_by=quantity_available&sort_direction=desc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'product_id',
                        'product_name',
                        'product_sku',
                        'category_id',
                        'category_name',
                        'warehouse_id',
                        'warehouse_name',
                        'warehouse_code',
                        'quantity_on_hand',
                        'quantity_reserved',
                        'quantity_available',
                        'unit_cost',
                        'inventory_value',
                        'sales_velocity',
                        'days_of_stock',
                        'stock_health',
                        'stock_health_label',
                        'safety_stock',
                        'reorder_point',
                    ],
                ],
                'pagination' => ['page', 'per_page', 'total', 'total_pages'],
            ]);
    }

    public function test_inventory_filters_returns_options(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/filters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'warehouses' => [
                    '*' => ['id', 'name', 'code'],
                ],
                'statuses' => [
                    '*' => ['value', 'label'],
                ],
                'latest_snapshot_date',
            ]);
    }
}
