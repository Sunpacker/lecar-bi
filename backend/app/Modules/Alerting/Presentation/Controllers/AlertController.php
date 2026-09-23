<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Presentation\Controllers;

use App\Modules\Alerting\Application\Commands\AcknowledgeAlertCommand;
use App\Modules\Alerting\Application\Commands\AcknowledgeAlertHandler;
use App\Modules\Alerting\Application\Commands\ResolveAlertCommand;
use App\Modules\Alerting\Application\Commands\ResolveAlertHandler;
use App\Modules\Alerting\Application\Queries\GetAlertByIdHandler;
use App\Modules\Alerting\Application\Queries\GetAlertByIdQuery;
use App\Modules\Alerting\Application\Queries\GetAlertsHandler;
use App\Modules\Alerting\Application\Queries\GetAlertsQuery;
use App\Modules\Alerting\Application\Queries\GetAlertSummaryHandler;
use App\Modules\Alerting\Application\Queries\GetAlertSummaryQuery;
use App\Modules\Alerting\Domain\Exceptions\AlertNotFoundException;
use App\Modules\Alerting\Domain\Exceptions\InvalidAlertStateTransitionException;
use App\Modules\Alerting\Presentation\Requests\GetAlertsRequest;
use App\Modules\Alerting\Presentation\Requests\ResolveAlertRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AlertController
{
    public function index(
        GetAlertsRequest $request,
        GetAlertsHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $status = $request->input('status', 'active');
            $severity = $request->input('severity');
            $warehouseId = $request->input('warehouse_id');
            $ruleId = $request->input('rule_id');
            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('per_page', 20);

            $result = $handler->handle(new GetAlertsQuery(
                workspaceId: $workspaceId,
                status: $status,
                severity: $severity,
                warehouseId: $warehouseId,
                ruleId: $ruleId,
                page: $page,
                perPage: $perPage,
            ));

            return response()->json($result->toArray());
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function summary(
        Request $request,
        GetAlertSummaryHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $summary = $handler->handle(new GetAlertSummaryQuery($workspaceId));

            return response()->json($summary->toArray());
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function show(
        Request $request,
        string $id,
        GetAlertByIdHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $alert = $handler->handle(new GetAlertByIdQuery($workspaceId, $id));

            return response()->json(['alert' => $alert->toArray()]);
        } catch (AlertNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function acknowledge(
        Request $request,
        string $id,
        AcknowledgeAlertHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $alert = $handler->handle(new AcknowledgeAlertCommand($workspaceId, $id, $userId));

            return response()->json(['alert' => $alert->toArray()]);
        } catch (AlertNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        } catch (InvalidAlertStateTransitionException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'INVALID_STATE_TRANSITION'], 400);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function resolve(
        ResolveAlertRequest $request,
        string $id,
        ResolveAlertHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $alert = $handler->handle(new ResolveAlertCommand(
                workspaceId: $workspaceId,
                alertId: $id,
                userId: $userId,
                resolutionNote: $request->input('resolution_note'),
            ));

            return response()->json(['alert' => $alert->toArray()]);
        } catch (AlertNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        } catch (InvalidAlertStateTransitionException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'INVALID_STATE_TRANSITION'], 400);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }
}
