<?php

declare(strict_types=1);

namespace App\Modules\InventoryAnalytics\Presentation\Controllers;

use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzItemsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzItemsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzSummaryHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetAbcXyzSummaryQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryFilterOptionsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryFilterOptionsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryItemsHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventoryItemsQuery;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventorySummaryHandler;
use App\Modules\InventoryAnalytics\Application\Queries\GetInventorySummaryQuery;
use App\Modules\InventoryAnalytics\Presentation\Requests\GetAbcXyzItemsRequest;
use App\Modules\InventoryAnalytics\Presentation\Requests\GetAbcXyzSummaryRequest;
use App\Modules\InventoryAnalytics\Presentation\Requests\GetInventoryItemsRequest;
use App\Modules\InventoryAnalytics\Presentation\Requests\GetInventorySummaryRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class InventoryAnalyticsController
{
    public function summary(
        GetInventorySummaryRequest $request,
        GetInventorySummaryHandler $summaryHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $warehouseId = $request->query('warehouse_id');
            $asOfDate = $request->query('as_of_date');

            $summary = $summaryHandler->handle(new GetInventorySummaryQuery(
                workspaceId: $workspaceId,
                warehouseId: is_string($warehouseId) && $warehouseId !== '' ? $warehouseId : null,
                asOfDate: is_string($asOfDate) && $asOfDate !== '' ? $asOfDate : null,
            ));

            return response()->json([
                'summary' => [
                    'total_items' => $summary->totalItems,
                    'total_quantity_on_hand' => $summary->totalQuantityOnHand,
                    'total_quantity_reserved' => $summary->totalQuantityReserved,
                    'total_quantity_available' => $summary->totalQuantityAvailable,
                    'total_inventory_value' => $summary->totalInventoryValue,
                    'critical_count' => $summary->criticalCount,
                    'overstock_count' => $summary->overstockCount,
                    'out_of_stock_count' => $summary->outOfStockCount,
                    'optimal_count' => $summary->optimalCount,
                    'average_days_of_stock' => $summary->averageDaysOfStock,
                ],
                'health_breakdown' => array_map(fn ($item) => [
                    'status' => $item->status,
                    'label' => $item->label,
                    'items_count' => $item->itemsCount,
                    'total_value' => $item->totalValue,
                    'share' => $item->share,
                ], $summary->healthBreakdown),
                'warehouses' => array_map(fn ($wh) => [
                    'warehouse_id' => $wh->warehouseId,
                    'warehouse_name' => $wh->warehouseName,
                    'warehouse_code' => $wh->warehouseCode,
                    'total_quantity' => $wh->totalQuantity,
                    'total_value' => $wh->totalValue,
                    'items_count' => $wh->itemsCount,
                    'critical_count' => $wh->criticalCount,
                    'overstock_count' => $wh->overstockCount,
                ], $summary->warehouses),
                'as_of_date' => $summary->asOfDate,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function items(
        GetInventoryItemsRequest $request,
        GetInventoryItemsHandler $itemsHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $warehouseId = $request->query('warehouse_id');
            $stockHealth = $request->query('stock_health');
            $search = $request->query('search');
            $page = (int) ($request->query('page') ?? 1);
            $perPage = (int) ($request->query('per_page') ?? 20);
            $sortBy = (string) ($request->query('sort_by') ?? 'quantity_available');
            $sortDirection = (string) ($request->query('sort_direction') ?? 'asc');

            $result = $itemsHandler->handle(new GetInventoryItemsQuery(
                workspaceId: $workspaceId,
                warehouseId: is_string($warehouseId) && $warehouseId !== '' ? $warehouseId : null,
                stockHealth: is_string($stockHealth) && $stockHealth !== '' ? $stockHealth : null,
                search: is_string($search) && $search !== '' ? $search : null,
                page: $page,
                perPage: $perPage,
                sortBy: $sortBy,
                sortDirection: $sortDirection,
            ));

            return response()->json([
                'items' => array_map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->productId,
                    'product_name' => $item->productName,
                    'product_sku' => $item->productSku,
                    'category_id' => $item->categoryId,
                    'category_name' => $item->categoryName,
                    'warehouse_id' => $item->warehouseId,
                    'warehouse_name' => $item->warehouseName,
                    'warehouse_code' => $item->warehouseCode,
                    'quantity_on_hand' => $item->quantityOnHand,
                    'quantity_reserved' => $item->quantityReserved,
                    'quantity_available' => $item->quantityAvailable,
                    'unit_cost' => $item->unitCost,
                    'inventory_value' => $item->inventoryValue,
                    'sales_velocity' => $item->salesVelocity,
                    'days_of_stock' => $item->daysOfStock,
                    'stock_health' => $item->stockHealth,
                    'stock_health_label' => $item->stockHealthLabel,
                    'safety_stock' => $item->safetyStock,
                    'reorder_point' => $item->reorderPoint,
                ], $result->items),
                'pagination' => [
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'total' => $result->total,
                    'total_pages' => $result->totalPages,
                ],
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function filters(
        Request $request,
        GetInventoryFilterOptionsHandler $filterHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $filters = $filterHandler->handle(new GetInventoryFilterOptionsQuery($workspaceId));

            return response()->json([
                'warehouses' => $filters->warehouses,
                'statuses' => $filters->statuses,
                'latest_snapshot_date' => $filters->latestSnapshotDate,
                'categories' => $filters->categories,
                'suppliers' => $filters->suppliers,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function abcXyzSummary(
        GetAbcXyzSummaryRequest $request,
        GetAbcXyzSummaryHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $criteria = $request->toCriteria();
            $summary = $handler->handle(new GetAbcXyzSummaryQuery($workspaceId, $criteria));

            return response()->json([
                'data' => [
                    'total_products' => $summary->totalProducts,
                    'total_revenue' => $summary->totalRevenue,
                    'total_inventory_value' => $summary->totalInventoryValue,
                    'matrix' => array_map(fn ($cell) => [
                        'code' => $cell->code,
                        'label' => $cell->label,
                        'description' => $cell->description,
                        'recommendation' => $cell->recommendation,
                        'count' => $cell->count,
                        'count_share' => $cell->countShare,
                        'revenue' => $cell->revenue,
                        'revenue_share' => $cell->revenueShare,
                        'inventory_value' => $cell->inventoryValue,
                        'inventory_value_share' => $cell->inventoryValueShare,
                    ], $summary->matrix),
                    'abc_distribution' => array_map(fn ($item) => [
                        'class' => $item->class,
                        'label' => $item->label,
                        'count' => $item->count,
                        'count_share' => $item->countShare,
                        'revenue' => $item->revenue,
                        'revenue_share' => $item->revenueShare,
                    ], $summary->abcDistribution),
                    'xyz_distribution' => array_map(fn ($item) => [
                        'class' => $item->class,
                        'label' => $item->label,
                        'count' => $item->count,
                        'count_share' => $item->countShare,
                        'revenue' => $item->revenue,
                        'revenue_share' => $item->revenueShare,
                    ], $summary->xyzDistribution),
                    'period_days' => $summary->periodDays,
                    'start_date' => $summary->startDate,
                    'end_date' => $summary->endDate,
                ],
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function abcXyzItems(
        GetAbcXyzItemsRequest $request,
        GetAbcXyzItemsHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $criteria = $request->toCriteria();
            $result = $handler->handle(new GetAbcXyzItemsQuery($workspaceId, $criteria));

            return response()->json([
                'items' => array_map(fn ($item) => [
                    'id' => $item->id,
                    'product_id' => $item->productId,
                    'product_name' => $item->productName,
                    'product_sku' => $item->productSku,
                    'category_id' => $item->categoryId,
                    'category_name' => $item->categoryName,
                    'brand_name' => $item->brandName,
                    'supplier_id' => $item->supplierId,
                    'supplier_name' => $item->supplierName,
                    'total_revenue' => $item->totalRevenue,
                    'total_units_sold' => $item->totalUnitsSold,
                    'revenue_share' => $item->revenueShare,
                    'cumulative_revenue_share' => $item->cumulativeRevenueShare,
                    'abc_class' => $item->abcClass,
                    'period_sales' => $item->periodSales,
                    'average_sales' => $item->averageSales,
                    'standard_deviation' => $item->standardDeviation,
                    'coefficient_of_variation' => $item->coefficientOfVariation,
                    'xyz_class' => $item->xyzClass,
                    'abc_xyz_group' => $item->abcXyzGroup,
                    'current_stock' => $item->currentStock,
                    'inventory_value' => $item->inventoryValue,
                ], $result->items),
                'pagination' => [
                    'page' => $result->page,
                    'per_page' => $result->perPage,
                    'total' => $result->total,
                    'total_pages' => $result->totalPages,
                ],
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }
}
