<?php

declare(strict_types=1);

namespace App\Modules\SupplierAnalytics\Presentation\Controllers;

use App\Modules\SupplierAnalytics\Application\Dtos\SupplierDeliveriesCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierOverviewCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Dtos\SupplierPerformanceCriteriaDto;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierDeliveriesHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierDeliveriesQuery;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierFilterOptionsHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierFilterOptionsQuery;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierOverviewHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierOverviewQuery;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierPerformanceHandler;
use App\Modules\SupplierAnalytics\Application\Queries\GetSupplierPerformanceQuery;
use App\Modules\SupplierAnalytics\Presentation\Requests\GetSupplierDeliveriesRequest;
use App\Modules\SupplierAnalytics\Presentation\Requests\GetSupplierOverviewRequest;
use App\Modules\SupplierAnalytics\Presentation\Requests\GetSupplierPerformanceRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SupplierAnalyticsController
{
    public function overview(
        GetSupplierOverviewRequest $request,
        GetSupplierOverviewHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $supplierId = $request->query('supplier_id');
            $warehouseId = $request->query('warehouse_id');

            $criteria = new SupplierOverviewCriteriaDto(
                dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
                dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
                supplierId: is_string($supplierId) && $supplierId !== '' ? $supplierId : null,
                warehouseId: is_string($warehouseId) && $warehouseId !== '' ? $warehouseId : null,
            );

            $result = $handler->handle(new GetSupplierOverviewQuery($workspaceId, $criteria));

            return response()->json($result->toArray());
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function performance(
        GetSupplierPerformanceRequest $request,
        GetSupplierPerformanceHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $warehouseId = $request->query('warehouse_id');
            $search = $request->query('search');
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 20);
            $sortBy = (string) $request->query('sort_by', 'total_spend');
            $sortDirection = (string) $request->query('sort_direction', 'desc');

            $criteria = new SupplierPerformanceCriteriaDto(
                dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
                dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
                warehouseId: is_string($warehouseId) && $warehouseId !== '' ? $warehouseId : null,
                search: is_string($search) && $search !== '' ? $search : null,
                page: max(1, $page),
                perPage: max(1, min(100, $perPage)),
                sortBy: $sortBy !== '' ? $sortBy : 'total_spend',
                sortDirection: $sortDirection !== '' ? $sortDirection : 'desc',
            );

            $result = $handler->handle(new GetSupplierPerformanceQuery($workspaceId, $criteria));

            return response()->json($result->toArray());
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function deliveries(
        GetSupplierDeliveriesRequest $request,
        GetSupplierDeliveriesHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $supplierId = $request->query('supplier_id');
            $warehouseId = $request->query('warehouse_id');
            $status = $request->query('status');
            $dateFrom = $request->query('date_from');
            $dateTo = $request->query('date_to');
            $search = $request->query('search');
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('per_page', 20);
            $sortBy = (string) $request->query('sort_by', 'order_date');
            $sortDirection = (string) $request->query('sort_direction', 'desc');

            $criteria = new SupplierDeliveriesCriteriaDto(
                supplierId: is_string($supplierId) && $supplierId !== '' ? $supplierId : null,
                warehouseId: is_string($warehouseId) && $warehouseId !== '' ? $warehouseId : null,
                status: is_string($status) && $status !== '' ? $status : null,
                dateFrom: is_string($dateFrom) && $dateFrom !== '' ? $dateFrom : null,
                dateTo: is_string($dateTo) && $dateTo !== '' ? $dateTo : null,
                search: is_string($search) && $search !== '' ? $search : null,
                page: max(1, $page),
                perPage: max(1, min(100, $perPage)),
                sortBy: $sortBy !== '' ? $sortBy : 'order_date',
                sortDirection: $sortDirection !== '' ? $sortDirection : 'desc',
            );

            $result = $handler->handle(new GetSupplierDeliveriesQuery($workspaceId, $criteria));

            return response()->json($result->toArray());
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    public function filters(
        Request $request,
        GetSupplierFilterOptionsHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $result = $handler->handle(new GetSupplierFilterOptionsQuery($workspaceId));

            return response()->json($result->toArray());
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }
}
