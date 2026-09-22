<?php

namespace Tests\Feature\Modules\SalesAnalytics;

use App\Modules\SalesAnalytics\Application\Contracts\SalesAnalyticsReadModelInterface;
use App\Modules\SalesAnalytics\Application\Dtos\SalesRecordDto;
use App\Modules\SalesAnalytics\Infrastructure\Persistence\InMemorySalesAnalyticsReadModel;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use Tests\TestCase;

final class SalesAnalyticsRecordsApiTest extends TestCase
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

        $readModel = $this->app->make(SalesAnalyticsReadModelInterface::class);
        if ($readModel instanceof InMemorySalesAnalyticsReadModel) {
            $readModel->seedRecords([
                new SalesRecordDto(
                    id: 'item-1',
                    orderId: 'ord-1',
                    orderNumber: 'ORD-1001',
                    orderDate: '2026-01-15',
                    productId: 'prod-1',
                    productName: 'Тормозные колодки',
                    productSku: 'BRK-001',
                    categoryId: 'cat-1',
                    categoryName: 'Тормозная система',
                    regionId: 'reg-1',
                    regionName: 'Москва',
                    brandName: 'Brembo',
                    quantity: 2,
                    unitPrice: 3500.0,
                    totalPrice: 7000.0,
                    grossProfit: 2500.0,
                    status: 'completed',
                ),
            ]);
        }
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/analytics/sales/records')->assertStatus(401);
    }

    public function test_forbidden_workspace_access_returns_403(): void
    {
        $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-2')
            ->getJson('/api/v1/analytics/sales/records')
            ->assertStatus(403);
    }

    public function test_invalid_date_parameters_return_422(): void
    {
        $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/records?date_from=invalid-date')
            ->assertStatus(422);
    }

    public function test_authenticated_user_can_fetch_paginated_sales_records(): void
    {
        $response = $this->withHeader('X-User-Id', 'user-1')
            ->withHeader('X-Workspace-Id', 'ws-1')
            ->getJson('/api/v1/analytics/sales/records?page=1&per_page=10&sort_by=total_price&sort_direction=desc');

        $response->assertOk()
            ->assertJsonStructure([
                'items' => [
                    '*' => [
                        'id',
                        'order_id',
                        'order_number',
                        'order_date',
                        'product_id',
                        'product_name',
                        'product_sku',
                        'category_id',
                        'category_name',
                        'region_id',
                        'region_name',
                        'brand_name',
                        'quantity',
                        'unit_price',
                        'total_price',
                        'gross_profit',
                        'status',
                    ],
                ],
                'pagination' => [
                    'page',
                    'per_page',
                    'total',
                    'total_pages',
                ],
            ]);

        $this->assertSame(1, $response->json('pagination.page'));
        $this->assertSame(10, $response->json('pagination.per_page'));
        $this->assertSame(1, $response->json('pagination.total'));
        $this->assertCount(1, $response->json('items'));
        $this->assertSame('ORD-1001', $response->json('items.0.order_number'));
    }
}
