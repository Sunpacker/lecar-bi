<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InventoryAnalytics;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class InventoryAbcXyzApiTest extends TestCase
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

    public function test_abc_xyz_summary_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/analytics/inventory/abc-xyz/summary');
        $response->assertStatus(401);
    }

    public function test_abc_xyz_summary_enforces_workspace_access_boundary(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/analytics/inventory/abc-xyz/summary');

        $response->assertStatus(403);
    }

    public function test_abc_xyz_summary_returns_successful_matrix_response(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/abc-xyz/summary?period_days=90');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_products',
                    'total_revenue',
                    'total_inventory_value',
                    'matrix' => [
                        '*' => [
                            'code',
                            'label',
                            'description',
                            'recommendation',
                            'count',
                            'count_share',
                            'revenue',
                            'revenue_share',
                            'inventory_value',
                            'inventory_value_share',
                        ],
                    ],
                    'abc_distribution' => [
                        '*' => [
                            'class',
                            'label',
                            'count',
                            'count_share',
                            'revenue',
                            'revenue_share',
                        ],
                    ],
                    'xyz_distribution' => [
                        '*' => [
                            'class',
                            'label',
                            'count',
                            'count_share',
                            'revenue',
                            'revenue_share',
                        ],
                    ],
                    'period_days',
                    'start_date',
                    'end_date',
                ],
            ]);

        $payload = $response->json('data');
        self::assertCount(9, $payload['matrix']);
        self::assertCount(3, $payload['abc_distribution']);
        self::assertCount(3, $payload['xyz_distribution']);
    }

    public function test_abc_xyz_summary_validates_period_days(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/abc-xyz/summary?period_days=999');

        $response->assertStatus(422);
    }

    public function test_abc_xyz_items_returns_paginated_product_items(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/abc-xyz/items?period_days=90&page=1&per_page=10');

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
                        'brand_name',
                        'supplier_id',
                        'supplier_name',
                        'total_revenue',
                        'total_units_sold',
                        'revenue_share',
                        'cumulative_revenue_share',
                        'abc_class',
                        'period_sales',
                        'average_sales',
                        'standard_deviation',
                        'coefficient_of_variation',
                        'xyz_class',
                        'abc_xyz_group',
                        'current_stock',
                        'inventory_value',
                    ],
                ],
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'total_pages',
                ],
            ]);
    }

    public function test_abc_xyz_items_filters_by_group(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/abc-xyz/items?group=AX');

        $response->assertStatus(200);
        $items = $response->json('items');
        foreach ($items as $item) {
            self::assertSame('AX', $item['abc_xyz_group']);
        }
    }

    public function test_inventory_filters_includes_categories_and_suppliers(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/inventory/filters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'warehouses',
                'statuses',
                'latest_snapshot_date',
                'categories' => [
                    '*' => ['id', 'name', 'code'],
                ],
                'suppliers' => [
                    '*' => ['id', 'name'],
                ],
            ]);
    }
}
