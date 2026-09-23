<?php

namespace App\Modules\Dashboard\Presentation\Controllers;

use App\Modules\Dashboard\Application\Commands\CreateSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\CreateSavedViewHandler;
use App\Modules\Dashboard\Application\Commands\DeleteSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\DeleteSavedViewHandler;
use App\Modules\Dashboard\Application\Commands\UpdateSavedViewCommand;
use App\Modules\Dashboard\Application\Commands\UpdateSavedViewHandler;
use App\Modules\Dashboard\Application\Dtos\DashboardFiltersDto;
use App\Modules\Dashboard\Application\Dtos\SavedViewDto;
use App\Modules\Dashboard\Application\Queries\GetSavedViewByIdHandler;
use App\Modules\Dashboard\Application\Queries\GetSavedViewByIdQuery;
use App\Modules\Dashboard\Application\Queries\GetSavedViewsByDashboardHandler;
use App\Modules\Dashboard\Application\Queries\GetSavedViewsByDashboardQuery;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Exceptions\InvalidFilterException;
use App\Modules\Dashboard\Domain\Exceptions\SavedViewNotFoundException;
use App\Modules\Dashboard\Presentation\Requests\CreateSavedViewRequest;
use App\Modules\Dashboard\Presentation\Requests\UpdateSavedViewRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class DashboardSavedViewController
{
    public function index(
        string $dashboardId,
        Request $request,
        GetSavedViewsByDashboardHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $views = $handler->handle(new GetSavedViewsByDashboardQuery($workspaceId, $dashboardId));

            return response()->json([
                'items' => array_map(fn (SavedViewDto $v) => $this->formatView($v), $views),
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException|DashboardNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function store(
        string $dashboardId,
        CreateSavedViewRequest $request,
        CreateSavedViewHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            /** @var array<string, mixed> $rawFilters */
            $rawFilters = (array) $request->input('filters', []);

            $view = $handler->handle(new CreateSavedViewCommand(
                workspaceId: $workspaceId,
                dashboardId: $dashboardId,
                name: (string) $request->input('name'),
                filters: $this->parseFilters($rawFilters),
                isDefault: (bool) $request->input('is_default', false),
            ));

            return response()->json([
                'view' => $this->formatView($view),
            ], 201);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException|DashboardNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        } catch (InvalidFilterException|InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }
    }

    public function show(
        string $dashboardId,
        string $viewId,
        Request $request,
        GetSavedViewByIdHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $view = $handler->handle(new GetSavedViewByIdQuery($workspaceId, $dashboardId, $viewId));

            return response()->json([
                'view' => $this->formatView($view),
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException|DashboardNotFoundException|SavedViewNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function update(
        string $dashboardId,
        string $viewId,
        UpdateSavedViewRequest $request,
        UpdateSavedViewHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            /** @var array<string, mixed> $rawFilters */
            $rawFilters = (array) $request->input('filters', []);

            $view = $handler->handle(new UpdateSavedViewCommand(
                workspaceId: $workspaceId,
                dashboardId: $dashboardId,
                viewId: $viewId,
                name: (string) $request->input('name'),
                filters: $this->parseFilters($rawFilters),
                isDefault: (bool) $request->input('is_default', false),
            ));

            return response()->json([
                'view' => $this->formatView($view),
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException|DashboardNotFoundException|SavedViewNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        } catch (InvalidFilterException|InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }
    }

    public function destroy(
        string $dashboardId,
        string $viewId,
        Request $request,
        DeleteSavedViewHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse|Response {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $handler->handle(new DeleteSavedViewCommand($workspaceId, $dashboardId, $viewId));

            return response()->noContent();
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (WorkspaceNotFoundException|DashboardNotFoundException|SavedViewNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    /**
     * @param  array<string, mixed>  $rawFilters
     */
    private function parseFilters(array $rawFilters): DashboardFiltersDto
    {
        return new DashboardFiltersDto(
            dateRange: isset($rawFilters['date_range']) && is_string($rawFilters['date_range']) ? $rawFilters['date_range'] : null,
            dateFrom: isset($rawFilters['date_from']) && is_string($rawFilters['date_from']) ? $rawFilters['date_from'] : null,
            dateTo: isset($rawFilters['date_to']) && is_string($rawFilters['date_to']) ? $rawFilters['date_to'] : null,
            categoryId: isset($rawFilters['category_id']) && is_string($rawFilters['category_id']) ? $rawFilters['category_id'] : null,
            regionId: isset($rawFilters['region_id']) && is_string($rawFilters['region_id']) ? $rawFilters['region_id'] : null,
            warehouseId: isset($rawFilters['warehouse_id']) && is_string($rawFilters['warehouse_id']) ? $rawFilters['warehouse_id'] : null,
            stockHealth: isset($rawFilters['stock_health']) && is_string($rawFilters['stock_health']) ? $rawFilters['stock_health'] : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function formatView(SavedViewDto $view): array
    {
        return [
            'id' => $view->id,
            'dashboard_id' => $view->dashboardId,
            'name' => $view->name,
            'filters' => [
                'date_range' => $view->filters->dateRange,
                'date_from' => $view->filters->dateFrom,
                'date_to' => $view->filters->dateTo,
                'category_id' => $view->filters->categoryId,
                'region_id' => $view->filters->regionId,
                'warehouse_id' => $view->filters->warehouseId,
                'stock_health' => $view->filters->stockHealth,
            ],
            'is_default' => $view->isDefault,
            'created_at' => $view->createdAt,
            'updated_at' => $view->updatedAt,
        ];
    }
}
