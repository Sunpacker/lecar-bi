<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Contracts;

use DateTimeImmutable;

interface ForecastRepositoryInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $points
     * @param  array<int, array<string, mixed>>  $qualityMetrics
     * @param  array<string, mixed>|null  $stockRisk
     */
    public function save(
        string $id,
        string $workspaceId,
        string $productId,
        string $warehouseId,
        string $asOfDate,
        int $horizonDays,
        string $modelMethod,
        string $modelVersion,
        string $status,
        int $salesDatasetVersion,
        int $inventoryDatasetVersion,
        int $supplierDatasetVersion,
        DateTimeImmutable $generatedAt,
        array $points,
        array $qualityMetrics,
        ?array $stockRisk
    ): void;

    public function findExisting(
        string $workspaceId,
        string $productId,
        string $warehouseId,
        string $asOfDate,
        int $horizonDays,
        string $modelMethod,
        string $modelVersion,
        int $salesV,
        int $invV,
        int $supV
    ): ?string;
}
