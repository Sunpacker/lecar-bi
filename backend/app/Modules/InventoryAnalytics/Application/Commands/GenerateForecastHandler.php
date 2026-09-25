<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Application\Commands;

use App\Modules\InventoryAnalytics\Application\Contracts\ForecastRepositoryInterface;
use App\Modules\InventoryAnalytics\Application\Contracts\SalesTimeSeriesReaderInterface;
use App\Modules\InventoryAnalytics\Domain\Forecasting\ForecastEngine;
use App\Modules\InventoryAnalytics\Domain\Forecasting\ForecastHorizon;
use App\Modules\InventoryAnalytics\Domain\Forecasting\StockRiskCalculator;
use App\Shared\Infrastructure\Cache\AnalyticsDatasetVersionStore;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class GenerateForecastHandler
{
    public function __construct(
        private SalesTimeSeriesReaderInterface $salesReader,
        private ForecastRepositoryInterface $repository,
        private ForecastEngine $engine,
        private StockRiskCalculator $riskCalculator,
        private AnalyticsDatasetVersionStore $versionStore
    ) {}

    public function handle(GenerateForecastCommand $command): ?string
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $asOfDate = $now->format('Y-m-d');

        $salesV = $this->versionStore->getVersion($command->workspaceId, 'sales');
        $invV = $this->versionStore->getVersion($command->workspaceId, 'inventory');
        $supV = $this->versionStore->getVersion($command->workspaceId, 'supplier');

        $horizon = ForecastHorizon::fromDays($command->horizonDays);

        $timeSeries = $this->salesReader->getDailySalesTimeSeries(
            $command->workspaceId,
            $command->productId,
            $command->warehouseId,
            $asOfDate
        );

        $inventorySnapshot = DB::table('fact_inventory_daily')
            ->where('workspace_id', $command->workspaceId)
            ->where('product_id', $command->productId)
            ->where('warehouse_id', $command->warehouseId)
            ->orderBy('snapshot_date', 'desc')
            ->first();

        $qtyAvailable = $inventorySnapshot ? (int) $inventorySnapshot->quantity_available : 0;
        $safetyStock = $inventorySnapshot ? (int) $inventorySnapshot->safety_stock : 0;
        $reorderPoint = $inventorySnapshot ? (int) $inventorySnapshot->reorder_point : 0;

        $medianLeadTime = DB::table('fact_supplier_deliveries')
            ->where('workspace_id', $command->workspaceId)
            ->where('product_id', $command->productId)
            ->whereNotNull('lead_time_days')
            ->avg('lead_time_days');

        $result = $this->engine->generate($timeSeries, $horizon);

        $existingId = $this->repository->findExisting(
            $command->workspaceId,
            $command->productId,
            $command->warehouseId,
            $asOfDate,
            $command->horizonDays,
            $result->selectedMethod->value,
            $result->modelVersion,
            $salesV,
            $invV,
            $supV
        );

        if ($existingId !== null) {
            return null;
        }

        $stockRiskArray = null;
        $statusValue = $result->status->value;

        if ($statusValue !== 'failed') {
            $risk = $this->riskCalculator->calculate(
                $result->points,
                $qtyAvailable,
                $safetyStock,
                $reorderPoint,
                $medianLeadTime !== null ? (int) round((float) $medianLeadTime) : null
            );

            $stockRiskArray = [
                'current_quantity_available' => $risk->currentQuantityAvailable,
                'current_safety_stock' => $risk->currentSafetyStock,
                'current_reorder_point' => $risk->currentReorderPoint,
                'estimated_depletion_date' => $risk->estimatedDepletionDate?->format('Y-m-d'),
                'estimated_reorder_threshold_date' => $risk->estimatedReorderThresholdDate?->format('Y-m-d'),
                'estimated_order_placement_date' => $risk->estimatedOrderPlacementDate?->format('Y-m-d'),
                'median_lead_time_days' => $risk->medianLeadTimeDays,
                'lead_time_source' => $risk->leadTimeSource,
                'assumptions' => $risk->assumptions,
            ];
        }

        $forecastId = Str::uuid()->toString();

        $pointsArray = [];
        foreach ($result->points as $p) {
            $pointsArray[] = [
                'forecast_date' => $p->date->format('Y-m-d'),
                'point_estimate' => $p->pointEstimate,
                'lower_bound' => $p->lowerBound,
                'upper_bound' => $p->upperBound,
                'interval_level' => $p->intervalLevel,
                'actual_value' => null,
                'is_stockout_day' => false,
            ];
        }

        $metricsArray = [];
        foreach ($result->backtestResult->metrics as $metricName => $metricValue) {
            $metricsArray[] = [
                'metric_name' => $metricName,
                'metric_value' => $metricValue,
                'horizon_days' => $result->backtestResult->horizon->value,
                'segment' => 'all',
                'evaluation_windows' => $result->backtestResult->evaluationWindows,
            ];
        }

        $this->repository->save(
            $forecastId,
            $command->workspaceId,
            $command->productId,
            $command->warehouseId,
            $asOfDate,
            $command->horizonDays,
            $result->selectedMethod->value,
            $result->modelVersion,
            $statusValue,
            $salesV,
            $invV,
            $supV,
            $now,
            $pointsArray,
            $metricsArray,
            $stockRiskArray
        );

        return $forecastId;
    }
}
