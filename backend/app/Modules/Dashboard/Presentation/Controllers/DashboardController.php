<?php

namespace App\Modules\Dashboard\Presentation\Controllers;

use App\Modules\Dashboard\Application\Commands\CreateDashboardCommand;
use App\Modules\Dashboard\Application\Commands\CreateDashboardHandler;
use App\Modules\Dashboard\Application\Commands\DeleteDashboardCommand;
use App\Modules\Dashboard\Application\Commands\DeleteDashboardHandler;
use App\Modules\Dashboard\Application\Commands\UpdateDashboardCommand;
use App\Modules\Dashboard\Application\Commands\UpdateDashboardHandler;
use App\Modules\Dashboard\Application\Dtos\WidgetDto;
use App\Modules\Dashboard\Application\Dtos\WidgetGridPositionDto;
use App\Modules\Dashboard\Application\Dtos\WidgetQueryConfigDto;
use App\Modules\Dashboard\Application\Queries\GetDashboardByIdHandler;
use App\Modules\Dashboard\Application\Queries\GetDashboardByIdQuery;
use App\Modules\Dashboard\Application\Queries\GetDashboardsHandler;
use App\Modules\Dashboard\Application\Queries\GetDashboardsQuery;
use App\Modules\Dashboard\Domain\Exceptions\DashboardNotFoundException;
use App\Modules\Dashboard\Domain\Exceptions\InvalidGridPositionException;
use App\Modules\Dashboard\Presentation\Requests\CreateDashboardRequest;
use App\Modules\Dashboard\Presentation\Requests\UpdateDashboardRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

final class DashboardController
{
    public function index(
        Request $request,
        GetDashboardsHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $dashboards = $handler->handle(new GetDashboardsQuery($workspaceId));

            return response()->json([
                'items' => array_map(fn ($d) => [
                    'id' => $d->id,
                    'workspace_id' => $d->workspaceId,
                    'title' => $d->title,
                    'description' => $d->description,
                    'widget_count' => $d->widgetCount,
                    'created_at' => $d->createdAt,
                    'updated_at' => $d->updatedAt,
                ], $dashboards),
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

    public function store(
        CreateDashboardRequest $request,
        CreateDashboardHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $title = (string) $request->input('title');
            $description = $request->input('description') !== null ? (string) $request->input('description') : null;

            $dashboard = $handler->handle(new CreateDashboardCommand(
                workspaceId: $workspaceId,
                title: $title,
                description: $description,
            ));

            return response()->json([
                'dashboard' => [
                    'id' => $dashboard->id,
                    'workspace_id' => $dashboard->workspaceId,
                    'title' => $dashboard->title,
                    'description' => $dashboard->description,
                    'widgets' => [],
                    'created_at' => $dashboard->createdAt,
                    'updated_at' => $dashboard->updatedAt,
                ],
            ], 201);
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

    public function show(
        string $id,
        Request $request,
        GetDashboardByIdHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $dashboard = $handler->handle(new GetDashboardByIdQuery($workspaceId, $id));

            return response()->json([
                'dashboard' => [
                    'id' => $dashboard->id,
                    'workspace_id' => $dashboard->workspaceId,
                    'title' => $dashboard->title,
                    'description' => $dashboard->description,
                    'widgets' => array_map(fn ($w) => [
                        'id' => $w->id,
                        'title' => $w->title,
                        'type' => $w->type,
                        'query_config' => [
                            'dataset' => $w->queryConfig->dataset,
                            'metric' => $w->queryConfig->metric,
                            'dimension' => $w->queryConfig->dimension,
                            'date_range' => $w->queryConfig->dateRange,
                        ],
                        'position' => [
                            'x' => $w->position->x,
                            'y' => $w->position->y,
                            'w' => $w->position->w,
                            'h' => $w->position->h,
                        ],
                        'options' => $w->options,
                    ], $dashboard->widgets),
                    'created_at' => $dashboard->createdAt,
                    'updated_at' => $dashboard->updatedAt,
                ],
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

    public function update(
        string $id,
        UpdateDashboardRequest $request,
        UpdateDashboardHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $title = (string) $request->input('title');
            $description = $request->input('description') !== null ? (string) $request->input('description') : null;
            /** @var list<array<string, mixed>> $rawWidgets */
            $rawWidgets = (array) $request->input('widgets', []);

            $widgetDtos = [];
            foreach ($rawWidgets as $w) {
                /** @var array{dataset: string, metric: string, dimension?: string|null, date_range?: string|null} $q */
                $q = $w['query_config'];
                /** @var array{x: int, y: int, w: int, h: int} $pos */
                $pos = $w['position'];

                $widgetDtos[] = new WidgetDto(
                    id: isset($w['id']) && is_string($w['id']) ? $w['id'] : null,
                    title: (string) $w['title'],
                    type: (string) $w['type'],
                    queryConfig: new WidgetQueryConfigDto(
                        dataset: (string) $q['dataset'],
                        metric: (string) $q['metric'],
                        dimension: ! empty($q['dimension']) ? (string) $q['dimension'] : null,
                        dateRange: ! empty($q['date_range']) ? (string) $q['date_range'] : null,
                    ),
                    position: new WidgetGridPositionDto(
                        x: (int) $pos['x'],
                        y: (int) $pos['y'],
                        w: (int) $pos['w'],
                        h: (int) $pos['h'],
                    ),
                    options: isset($w['options']) && is_array($w['options']) ? $w['options'] : [],
                );
            }

            $dashboard = $handler->handle(new UpdateDashboardCommand(
                workspaceId: $workspaceId,
                dashboardId: $id,
                title: $title,
                description: $description,
                widgets: $widgetDtos,
            ));

            return response()->json([
                'dashboard' => [
                    'id' => $dashboard->id,
                    'workspace_id' => $dashboard->workspaceId,
                    'title' => $dashboard->title,
                    'description' => $dashboard->description,
                    'widgets' => array_map(fn ($w) => [
                        'id' => $w->id,
                        'title' => $w->title,
                        'type' => $w->type,
                        'query_config' => [
                            'dataset' => $w->queryConfig->dataset,
                            'metric' => $w->queryConfig->metric,
                            'dimension' => $w->queryConfig->dimension,
                            'date_range' => $w->queryConfig->dateRange,
                        ],
                        'position' => [
                            'x' => $w->position->x,
                            'y' => $w->position->y,
                            'w' => $w->position->w,
                            'h' => $w->position->h,
                        ],
                        'options' => $w->options,
                    ], $dashboard->widgets),
                    'created_at' => $dashboard->createdAt,
                    'updated_at' => $dashboard->updatedAt,
                ],
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
        } catch (InvalidGridPositionException|InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }
    }

    public function destroy(
        string $id,
        Request $request,
        DeleteDashboardHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse|Response {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $handler->handle(new DeleteDashboardCommand($workspaceId, $id));

            return response()->noContent();
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
}
