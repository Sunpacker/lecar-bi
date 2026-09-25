<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\ForecastReadModelInterface;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastPointDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastQualityDto;
use App\Modules\InventoryAnalytics\Application\Dtos\ForecastStockRiskDto;
use DateTimeImmutable;

final class InMemoryForecastReadModel implements ForecastReadModelInterface
{
    /**
     * @var array<string, ForecastDto>
     */
    private array $forecasts = [];

    public function __construct()
    {
        $this->seedDefault();
    }

    public function getLatestForecast(
        string $workspaceId,
        string $productId,
        string $warehouseId,
        int $horizonDays
    ): ?ForecastDto {
        $key = "{$workspaceId}:{$productId}:{$warehouseId}:{$horizonDays}";

        return $this->forecasts[$key] ?? null;
    }

    public function setForecast(ForecastDto $forecast): void
    {
        $key = "{$forecast->workspaceId}:{$forecast->productId}:{$forecast->warehouseId}:{$forecast->horizonDays}";
        $this->forecasts[$key] = $forecast;
    }

    private function seedDefault(): void
    {
        $points = [];
        $history = [];
        $start = new DateTimeImmutable('2025-12-03');

        for ($i = 0; $i < 28; $i++) {
            $date = $start->modify("+{$i} days")->format('Y-m-d');
            $history[] = new ForecastPointDto($date, 10.0 + ($i % 5), null, null, null, 10.0 + ($i % 5), false);
        }

        $futureStart = new DateTimeImmutable('2026-01-01');
        for ($i = 0; $i < 28; $i++) {
            $date = $futureStart->modify("+{$i} days")->format('Y-m-d');
            $points[] = new ForecastPointDto(
                $date,
                12.5 + ($i % 4),
                8.0,
                17.0,
                0.80,
                null,
                false
            );
        }

        $dto = new ForecastDto(
            id: 'fc-demo-1',
            workspaceId: 'ws-1',
            productId: 'prod-demo-1',
            productName: 'Тормозные диски вентилируемые',
            productSku: 'BD-2045',
            warehouseId: 'wh-1',
            warehouseName: 'Центральный склад МСК',
            asOfDate: '2025-12-31',
            generatedAt: new DateTimeImmutable('2025-12-31 23:59:59'),
            horizonDays: 28,
            modelMethod: 'seasonal_naive_dow',
            modelVersion: '1.0.0',
            status: 'ready',
            statusReason: null,
            points: $points,
            measuredHistory: $history,
            qualityMetrics: [
                new ForecastQualityDto('wape', 0.145, 28, 'all', 4),
                new ForecastQualityDto('mae', 1.82, 28, 'all', 4),
                new ForecastQualityDto('signed_bias', -0.02, 28, 'all', 4),
                new ForecastQualityDto('coverage', 0.82, 28, 'all', 4),
                new ForecastQualityDto('interval_width', 4.5, 28, 'all', 4),
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
            assumptions: [
                'По умолчанию предполагается отсутствие будущих неподтвержденных поставок при расчете дефицита',
                'Дни нулевых остатков исключены из расчета базового спроса (децензурирование)',
            ]
        );

        $this->setForecast($dto);
    }
}
