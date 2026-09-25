<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Presentation\Controllers;

use App\Modules\InventoryAnalytics\Application\Queries\GetForecastHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetForecastQuery;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ForecastController
{
    public function show(
        Request $request,
        string $productId,
        string $warehouseId,
        GetForecastHandler $forecastHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        try {
            $workspaceId = $this->resolveWorkspaceId($request, $workspaceHandler);
            $horizonDays = (int) ($request->query('horizon_days') ?? 28);
            $asOfDate = $request->query('as_of_date');

            $forecast = $forecastHandler->handle(new GetForecastQuery(
                workspaceId: $workspaceId,
                productId: $productId,
                warehouseId: $warehouseId,
                horizonDays: $horizonDays,
                asOfDate: is_string($asOfDate) && $asOfDate !== '' ? $asOfDate : null,
            ));

            if ($forecast === null) {
                return response()->json([
                    'message' => 'Forecast not found for the specified product and warehouse.',
                    'code' => 'NOT_FOUND',
                ], 404);
            }

            return response()->json([
                'workspace_id' => $forecast->workspaceId,
                'product_id' => $forecast->productId,
                'product_name' => $forecast->productName,
                'product_sku' => $forecast->productSku,
                'warehouse_id' => $forecast->warehouseId,
                'warehouse_name' => $forecast->warehouseName,
                'as_of_date' => $forecast->asOfDate,
                'generated_at' => $forecast->generatedAt->format('Y-m-d\TH:i:sP'),
                'horizon_days' => $forecast->horizonDays,
                'model_method' => $forecast->modelMethod,
                'model_version' => $forecast->modelVersion,
                'status' => $forecast->status,
                'status_reason' => $forecast->statusReason,
                'data_freshness' => [
                    'sales_date' => $forecast->salesDate,
                    'inventory_date' => $forecast->inventoryDate,
                    'supplier_date' => $forecast->supplierDate,
                ],
                'points' => array_map(fn ($p) => [
                    'date' => $p->date,
                    'point_estimate' => $p->pointEstimate,
                    'lower_bound' => $p->lowerBound,
                    'upper_bound' => $p->upperBound,
                    'interval_level' => $p->intervalLevel,
                    'actual_value' => $p->actualValue,
                    'is_stockout_day' => $p->isStockoutDay,
                ], $forecast->points),
                'measured_history' => array_map(fn ($p) => [
                    'date' => $p->date,
                    'point_estimate' => $p->pointEstimate,
                    'lower_bound' => $p->lowerBound,
                    'upper_bound' => $p->upperBound,
                    'interval_level' => $p->intervalLevel,
                    'actual_value' => $p->actualValue,
                    'is_stockout_day' => $p->isStockoutDay,
                ], $forecast->measuredHistory),
                'quality_metrics' => array_map(fn ($m) => [
                    'metric_name' => $m->metricName,
                    'metric_value' => $m->metricValue,
                    'horizon_days' => $m->horizonDays,
                    'segment' => $m->segment,
                    'evaluation_windows' => $m->evaluationWindows,
                ], $forecast->qualityMetrics),
                'stock_risk' => $forecast->stockRisk ? [
                    'current_quantity_available' => $forecast->stockRisk->currentQuantityAvailable,
                    'current_safety_stock' => $forecast->stockRisk->currentSafetyStock,
                    'current_reorder_point' => $forecast->stockRisk->currentReorderPoint,
                    'estimated_depletion_date' => $forecast->stockRisk->estimatedDepletionDate,
                    'estimated_reorder_threshold_date' => $forecast->stockRisk->estimatedReorderThresholdDate,
                    'estimated_order_placement_date' => $forecast->stockRisk->estimatedOrderPlacementDate,
                    'median_lead_time_days' => $forecast->stockRisk->medianLeadTimeDays,
                    'lead_time_source' => $forecast->stockRisk->leadTimeSource,
                    'assumptions' => $forecast->stockRisk->assumptions,
                ] : null,
                'assumptions' => $forecast->assumptions,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    private function resolveWorkspaceId(Request $request, GetCurrentWorkspaceHandler $workspaceHandler): string
    {
        $workspaceId = (string) $request->attributes->get('current_workspace_id');
        if ($workspaceId !== '') {
            return $workspaceId;
        }
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');
        $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));

        return $currentWorkspace->workspace->id;
    }
}
