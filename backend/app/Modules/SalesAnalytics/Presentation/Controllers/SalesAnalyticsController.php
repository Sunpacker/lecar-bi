<?php

namespace App\Modules\SalesAnalytics\Presentation\Controllers;

use App\Modules\SalesAnalytics\Application\Queries\GetSalesFilterOptionsHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesFilterOptionsQuery;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesOverviewHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesOverviewQuery;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesRecordsHandler;
use App\Modules\SalesAnalytics\Application\Queries\GetSalesRecordsQuery;
use App\Modules\SalesAnalytics\Domain\Exceptions\InvalidDateRangeException;
use App\Modules\SalesAnalytics\Presentation\Requests\GetSalesOverviewRequest;
use App\Modules\SalesAnalytics\Presentation\Requests\GetSalesRecordsRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SalesAnalyticsController
{
    public function overview(
        GetSalesOverviewRequest $request,
        GetSalesOverviewHandler $overviewHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $categoryId = $request->query('category_id');
            $regionId = $request->query('region_id');

            $overview = $overviewHandler->handle(new GetSalesOverviewQuery(
                workspaceId: $workspaceId,
                dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
                dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
                categoryId: is_string($categoryId) && $categoryId !== '' ? $categoryId : null,
                regionId: is_string($regionId) && $regionId !== '' ? $regionId : null,
            ));

            return response()->json([
                'summary' => [
                    'total_revenue' => $overview->summary->totalRevenue,
                    'order_count' => $overview->summary->orderCount,
                    'average_order_value' => $overview->summary->averageOrderValue,
                    'gross_profit' => $overview->summary->grossProfit,
                    'margin_rate' => $overview->summary->marginRate,
                ],
                'trend' => array_map(fn ($point) => [
                    'date' => $point->date,
                    'revenue' => $point->revenue,
                    'order_count' => $point->orderCount,
                ], $overview->trend),
                'categories' => array_map(fn ($cat) => [
                    'category_id' => $cat->categoryId,
                    'category_name' => $cat->categoryName,
                    'revenue' => $cat->revenue,
                    'order_count' => $cat->orderCount,
                    'revenue_share' => $cat->revenueShare,
                ], $overview->categories),
                'regions' => array_map(fn ($reg) => [
                    'region_id' => $reg->regionId,
                    'region_name' => $reg->regionName,
                    'region_code' => $reg->regionCode,
                    'revenue' => $reg->revenue,
                    'order_count' => $reg->orderCount,
                    'revenue_share' => $reg->revenueShare,
                ], $overview->regions),
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
        } catch (InvalidDateRangeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }
    }

    public function filters(
        Request $request,
        GetSalesFilterOptionsHandler $filterHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $filters = $filterHandler->handle(new GetSalesFilterOptionsQuery($workspaceId));

            return response()->json([
                'categories' => $filters->categories,
                'regions' => $filters->regions,
                'min_date' => $filters->minDate,
                'max_date' => $filters->maxDate,
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

    public function records(
        GetSalesRecordsRequest $request,
        GetSalesRecordsHandler $recordsHandler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $categoryId = $request->query('category_id');
            $regionId = $request->query('region_id');
            $page = (int) ($request->query('page') ?? 1);
            $perPage = (int) ($request->query('per_page') ?? 20);
            $sortBy = (string) ($request->query('sort_by') ?? 'order_date');
            $sortDirection = (string) ($request->query('sort_direction') ?? 'desc');

            $result = $recordsHandler->handle(new GetSalesRecordsQuery(
                workspaceId: $workspaceId,
                dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
                dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
                categoryId: is_string($categoryId) && $categoryId !== '' ? $categoryId : null,
                regionId: is_string($regionId) && $regionId !== '' ? $regionId : null,
                page: $page,
                perPage: $perPage,
                sortBy: $sortBy,
                sortDirection: $sortDirection,
            ));

            return response()->json([
                'items' => array_map(fn ($item) => [
                    'id' => $item->id,
                    'order_id' => $item->orderId,
                    'order_number' => $item->orderNumber,
                    'order_date' => $item->orderDate,
                    'product_id' => $item->productId,
                    'product_name' => $item->productName,
                    'product_sku' => $item->productSku,
                    'category_id' => $item->categoryId,
                    'category_name' => $item->categoryName,
                    'region_id' => $item->regionId,
                    'region_name' => $item->regionName,
                    'brand_name' => $item->brandName,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unitPrice,
                    'total_price' => $item->totalPrice,
                    'gross_profit' => $item->grossProfit,
                    'status' => $item->status,
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
        } catch (InvalidDateRangeException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }
    }
}
