<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Dtos;

use DateTimeImmutable;

readonly class ForecastDto
{
    /**
     * @param  ForecastPointDto[]  $points
     * @param  ForecastPointDto[]  $measuredHistory
     * @param  ForecastQualityDto[]  $qualityMetrics
     * @param  string[]  $assumptions
     */
    public function __construct(
        public string $id,
        public string $workspaceId,
        public string $productId,
        public string $productName,
        public string $productSku,
        public string $warehouseId,
        public string $warehouseName,
        public string $asOfDate,
        public DateTimeImmutable $generatedAt,
        public int $horizonDays,
        public string $modelMethod,
        public string $modelVersion,
        public string $status,
        public ?string $statusReason,
        public array $points,
        public array $measuredHistory,
        public array $qualityMetrics,
        public ?ForecastStockRiskDto $stockRisk,
        public ?string $salesDate,
        public ?string $inventoryDate,
        public ?string $supplierDate,
        public array $assumptions
    ) {}
}
