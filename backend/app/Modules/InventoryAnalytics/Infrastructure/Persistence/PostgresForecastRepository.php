<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Infrastructure\Persistence;

use App\Modules\InventoryAnalytics\Application\Contracts\ForecastRepositoryInterface;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PostgresForecastRepository implements ForecastRepositoryInterface
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
    ): void {
        DB::transaction(function () use (
            $id, $workspaceId, $productId, $warehouseId, $asOfDate, $horizonDays,
            $modelMethod, $modelVersion, $status, $salesDatasetVersion,
            $inventoryDatasetVersion, $supplierDatasetVersion, $generatedAt,
            $points, $qualityMetrics, $stockRisk
        ) {
            DB::table('forecasts')->insertOrIgnore([
                'id' => $id,
                'workspace_id' => $workspaceId,
                'product_id' => $productId,
                'warehouse_id' => $warehouseId,
                'as_of_date' => $asOfDate,
                'horizon_days' => $horizonDays,
                'model_method' => $modelMethod,
                'model_version' => $modelVersion,
                'status' => $status,
                'sales_dataset_version' => $salesDatasetVersion,
                'inventory_dataset_version' => $inventoryDatasetVersion,
                'supplier_dataset_version' => $supplierDatasetVersion,
                'generated_at' => $generatedAt->format('Y-m-d H:i:s'),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if (! empty($points)) {
                $pointsData = array_map(fn ($p) => array_merge($p, [
                    'id' => Str::uuid()->toString(),
                    'forecast_id' => $id,
                    'created_at' => now(),
                ]), $points);
                DB::table('forecast_points')->insert($pointsData);
            }

            if (! empty($qualityMetrics)) {
                $metricsData = array_map(fn ($m) => array_merge($m, [
                    'id' => Str::uuid()->toString(),
                    'forecast_id' => $id,
                    'created_at' => now(),
                ]), $qualityMetrics);
                DB::table('forecast_quality_metrics')->insert($metricsData);
            }

            if ($stockRisk !== null) {
                $stockRiskData = array_merge($stockRisk, [
                    'id' => Str::uuid()->toString(),
                    'forecast_id' => $id,
                    'created_at' => now(),
                ]);
                DB::table('forecast_stock_risk')->insert($stockRiskData);
            }
        });
    }

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
    ): ?string {
        $result = DB::table('forecasts')
            ->where('workspace_id', $workspaceId)
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->where('as_of_date', $asOfDate)
            ->where('horizon_days', $horizonDays)
            ->where('model_method', $modelMethod)
            ->where('model_version', $modelVersion)
            ->where('sales_dataset_version', $salesV)
            ->where('inventory_dataset_version', $invV)
            ->where('supplier_dataset_version', $supV)
            ->value('id');

        return $result ? (string) $result : null;
    }
}
