<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\SupplierAnalytics;

use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class SupplierAnalyticsApiTest extends TestCase
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

    public function test_overview_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/analytics/suppliers/overview');
        $response->assertStatus(401);
    }

    public function test_overview_enforces_workspace_access_boundary(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/analytics/suppliers/overview');

        $response->assertStatus(403);
    }

    public function test_overview_returns_aggregated_metrics(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/suppliers/overview?date_from=2025-01-01&date_to=2025-12-31');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'summary' => [
                    'total_deliveries',
                    'on_time_deliveries',
                    'delayed_deliveries',
                    'partial_deliveries',
                    'total_spend',
                    'total_ordered_quantity',
                    'total_received_quantity',
                    'total_defect_quantity',
                    'on_time_rate',
                    'delay_rate',
                    'fulfillment_rate',
                    'defect_rate',
                    'average_lead_time_days',
                    'average_delay_days',
                ],
                'status_breakdown' => [
                    '*' => [
                        'status',
                        'count',
                        'share_percentage',
                        'quantity',
                    ],
                ],
                'trends' => [
                    '*' => [
                        'period',
                        'deliveries_count',
                        'on_time_deliveries',
                        'total_spend',
                        'on_time_rate',
                        'fulfillment_rate',
                        'avg_lead_time_days',
                    ],
                ],
                'top_suppliers' => [
                    '*' => [
                        'supplier_id',
                        'supplier_name',
                        'total_deliveries',
                        'on_time_deliveries',
                        'delayed_deliveries',
                        'partial_deliveries',
                        'total_spend',
                        'ordered_quantity',
                        'received_quantity',
                        'defect_quantity',
                        'on_time_rate',
                        'delay_rate',
                        'fulfillment_rate',
                        'defect_rate',
                        'avg_lead_time_days',
                        'avg_delay_days',
                        'reliability_score',
                        'reliability_tier',
                    ],
                ],
            ]);
    }

    public function test_performance_returns_paginated_and_sorted_suppliers(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/suppliers/performance?page=1&per_page=5&sort_by=total_spend&sort_direction=desc');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'supplier_id',
                        'supplier_name',
                        'total_deliveries',
                        'total_spend',
                        'on_time_rate',
                        'fulfillment_rate',
                        'defect_rate',
                        'reliability_score',
                        'reliability_tier',
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

    public function test_deliveries_returns_paginated_delivery_records_with_filters(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/suppliers/deliveries?page=1&per_page=10&status=on_time');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'order_date',
                        'expected_delivery_date',
                        'actual_delivery_date',
                        'supplier_id',
                        'supplier_name',
                        'product_id',
                        'product_name',
                        'product_sku',
                        'warehouse_id',
                        'warehouse_name',
                        'ordered_quantity',
                        'received_quantity',
                        'defect_quantity',
                        'unit_purchase_cost',
                        'total_purchase_cost',
                        'delivery_status',
                        'lead_time_days',
                        'delay_days',
                    ],
                ],
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'total_pages',
                ],
            ]);

        $items = $response->json('items');
        foreach ($items as $item) {
            self::assertSame('on_time', $item['delivery_status']);
        }
    }

    public function test_filters_returns_available_options(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/suppliers/filters');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'suppliers' => [
                    '*' => ['id', 'name'],
                ],
                'warehouses' => [
                    '*' => ['id', 'name'],
                ],
                'statuses' => [
                    '*' => ['value', 'label'],
                ],
                'min_date',
                'max_date',
            ]);
    }
}
