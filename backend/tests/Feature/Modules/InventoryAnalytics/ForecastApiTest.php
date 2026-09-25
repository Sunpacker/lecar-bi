<?php

declare(strict_types=1);

namespace Tests\Feature\Modules\InventoryAnalytics;

use App\Modules\InventoryAnalytics\Application\Contracts\ForecastReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastPointDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastQualityDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastStockRiskDto;
use App\Modules\InventoryAnalytics\Infrastructure\Persistence\InMemoryForecastReadModel;
use App\Modules\Workspace\Domain\MembershipRole;
use App\Modules\Workspace\Domain\Repositories\UserRepositoryInterface;
use App\Modules\Workspace\Domain\Repositories\WorkspaceRepositoryInterface;
use App\Modules\Workspace\Domain\User;
use App\Modules\Workspace\Domain\UserId;
use App\Modules\Workspace\Domain\Workspace;
use App\Modules\Workspace\Domain\WorkspaceId;
use DateTimeImmutable;
use Tests\TestCase;

final class ForecastApiTest extends TestCase
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

    public function test_forecast_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/analytics/forecasts/prod-100/wh-100');
        $response->assertStatus(401);
    }

    public function test_forecast_enforces_workspace_access_boundary(): void
    {
        // user-1 has access to ws-1, not ws-2
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-2',
        ])->getJson('/api/v1/analytics/forecasts/prod-100/wh-100');

        $response->assertStatus(403);
    }

    public function test_forecast_returns_404_when_forecast_not_found(): void
    {
        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/forecasts/prod-non-existent/wh-non-existent');

        $response->assertStatus(404)
            ->assertJson([
                'code' => 'NOT_FOUND',
            ]);
    }

    public function test_forecast_returns_200_with_full_payload(): void
    {
        $readModel = $this->app->make(ForecastReadModelInterface::class);
        if ($readModel instanceof InMemoryForecastReadModel) {
            $dto = new ForecastDto(
                id: 'fc-test-1',
                workspaceId: 'ws-1',
                productId: 'prod-100',
                productName: 'Тормозные колодки Front',
                productSku: 'BP-100',
                warehouseId: 'wh-100',
                warehouseName: 'Центральный склад МСК',
                asOfDate: '2025-12-31',
                generatedAt: new DateTimeImmutable('2025-12-31 23:59:59'),
                horizonDays: 28,
                modelMethod: 'seasonal_naive_dow',
                modelVersion: '1.0.0',
                status: 'ready',
                statusReason: null,
                points: [
                    new ForecastPointDto('2026-01-01', 12.5, 8.0, 17.0, 0.80, null, false),
                    new ForecastPointDto('2026-01-02', 14.0, 9.5, 18.5, 0.80, null, false),
                ],
                measuredHistory: [
                    new ForecastPointDto('2025-12-30', 10.0, null, null, null, 10.0, false),
                ],
                qualityMetrics: [
                    new ForecastQualityDto('wape', 0.185, 28, 'all', 4),
                    new ForecastQualityDto('mae', 2.3, 28, 'all', 4),
                ],
                stockRisk: new ForecastStockRiskDto(
                    currentQuantityAvailable: 150,
                    currentSafetyStock: 40,
                    currentReorderPoint: 70,
                    estimatedDepletionDate: '2026-01-12',
                    estimatedReorderThresholdDate: '2026-01-06',
                    estimatedOrderPlacementDate: '2026-01-02',
                    medianLeadTimeDays: 4,
                    leadTimeSource: 'historical',
                    assumptions: '{"no_future_deliveries":true}'
                ),
                salesDate: '2025-12-31',
                inventoryDate: '2025-12-31',
                supplierDate: '2025-12-31',
                assumptions: ['По умолчанию предполагается отсутствие будущих неподтвержденных поставок']
            );
            $readModel->setForecast($dto);
        }

        $response = $this->withHeaders([
            'X-User-Id' => 'user-1',
            'X-Workspace-Id' => 'ws-1',
        ])->getJson('/api/v1/analytics/forecasts/prod-100/wh-100?horizon_days=28');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'workspace_id',
                'product_id',
                'product_name',
                'product_sku',
                'warehouse_id',
                'warehouse_name',
                'as_of_date',
                'generated_at',
                'horizon_days',
                'model_method',
                'model_version',
                'status',
                'status_reason',
                'data_freshness' => ['sales_date', 'inventory_date', 'supplier_date'],
                'points' => [
                    '*' => [
                        'date',
                        'point_estimate',
                        'lower_bound',
                        'upper_bound',
                        'interval_level',
                        'actual_value',
                        'is_stockout_day',
                    ],
                ],
                'measured_history',
                'quality_metrics' => [
                    '*' => ['metric_name', 'metric_value', 'horizon_days', 'segment', 'evaluation_windows'],
                ],
                'stock_risk' => [
                    'current_quantity_available',
                    'current_safety_stock',
                    'current_reorder_point',
                    'estimated_depletion_date',
                    'estimated_reorder_threshold_date',
                    'estimated_order_placement_date',
                    'median_lead_time_days',
                    'lead_time_source',
                    'assumptions',
                ],
                'assumptions',
            ])
            ->assertJson([
                'workspace_id' => 'ws-1',
                'product_id' => 'prod-100',
                'product_name' => 'Тормозные колодки Front',
                'product_sku' => 'BP-100',
                'warehouse_id' => 'wh-100',
                'warehouse_name' => 'Центральный склад МСК',
                'horizon_days' => 28,
                'status' => 'ready',
                'stock_risk' => [
                    'current_quantity_available' => 150,
                    'current_safety_stock' => 40,
                    'current_reorder_point' => 70,
                    'estimated_depletion_date' => '2026-01-12',
                    'estimated_reorder_threshold_date' => '2026-01-06',
                    'estimated_order_placement_date' => '2026-01-02',
                    'median_lead_time_days' => 4,
                ],
            ]);
    }
}
