<?php

declare(strict_types=1);

namespace App\Modules\Alerting\Presentation\Controllers;

use App\Modules\Alerting\Application\Commands\CreateAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\CreateAlertRuleHandler;
use App\Modules\Alerting\Application\Commands\DeleteAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\DeleteAlertRuleHandler;
use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesCommand;
use App\Modules\Alerting\Application\Commands\EvaluateAlertRulesHandler;
use App\Modules\Alerting\Application\Commands\ToggleAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\ToggleAlertRuleHandler;
use App\Modules\Alerting\Application\Commands\UpdateAlertRuleCommand;
use App\Modules\Alerting\Application\Commands\UpdateAlertRuleHandler;
use App\Modules\Alerting\Application\Queries\GetAlertRuleByIdHandler;
use App\Modules\Alerting\Application\Queries\GetAlertRuleByIdQuery;
use App\Modules\Alerting\Application\Queries\GetAlertRulesHandler;
use App\Modules\Alerting\Application\Queries\GetAlertRulesQuery;
use App\Modules\Alerting\Domain\Exceptions\AlertRuleNotFoundException;
use App\Modules\Alerting\Presentation\Requests\CreateAlertRuleRequest;
use App\Modules\Alerting\Presentation\Requests\UpdateAlertRuleRequest;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AlertRuleController
{
    public function index(
        Request $request,
        GetAlertRulesHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $isEnabled = $request->has('is_enabled') ? $request->boolean('is_enabled') : null;
            $rules = $handler->handle(new GetAlertRulesQuery($workspaceId, $isEnabled));

            return response()->json([
                'items' => array_map(fn ($r) => $r->toArray(), $rules),
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function store(
        CreateAlertRuleRequest $request,
        CreateAlertRuleHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $rule = $handler->handle(new CreateAlertRuleCommand(
                workspaceId: $workspaceId,
                name: (string) $request->input('name'),
                description: $request->input('description'),
                ruleType: (string) $request->input('rule_type'),
                severity: (string) $request->input('severity'),
                metric: (string) $request->input('metric'),
                comparator: (string) $request->input('comparator'),
                thresholdValue: (float) $request->input('threshold_value'),
                warehouseId: $request->input('warehouse_id'),
                categoryId: $request->input('category_id'),
                productId: $request->input('product_id'),
                isEnabled: $request->boolean('is_enabled', true),
            ));

            return response()->json(['rule' => $rule->toArray()], 201);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function show(
        Request $request,
        string $id,
        GetAlertRuleByIdHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $rule = $handler->handle(new GetAlertRuleByIdQuery($workspaceId, $id));

            return response()->json(['rule' => $rule->toArray()]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|AlertRuleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function update(
        UpdateAlertRuleRequest $request,
        string $id,
        UpdateAlertRuleHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $rule = $handler->handle(new UpdateAlertRuleCommand(
                workspaceId: $workspaceId,
                id: $id,
                name: (string) $request->input('name'),
                description: $request->input('description'),
                severity: (string) $request->input('severity'),
                metric: (string) ($request->input('metric') ?? 'quantity_available'),
                comparator: (string) ($request->input('comparator') ?? 'lte'),
                thresholdValue: (float) $request->input('threshold_value'),
                warehouseId: $request->input('warehouse_id'),
                categoryId: $request->input('category_id'),
                productId: $request->input('product_id'),
                isEnabled: $request->has('is_enabled') ? $request->boolean('is_enabled') : null,
            ));

            return response()->json(['rule' => $rule->toArray()]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|AlertRuleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function destroy(
        Request $request,
        string $id,
        DeleteAlertRuleHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): Response|JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $handler->handle(new DeleteAlertRuleCommand($workspaceId, $id));

            return response()->noContent();
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|AlertRuleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function toggle(
        Request $request,
        string $id,
        ToggleAlertRuleHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $rule = $handler->handle(new ToggleAlertRuleCommand($workspaceId, $id));

            return response()->json(['rule' => $rule->toArray()]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException|AlertRuleNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }

    public function evaluate(
        Request $request,
        EvaluateAlertRulesHandler $handler,
        GetCurrentWorkspaceHandler $workspaceHandler,
    ): JsonResponse {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $currentWorkspace = $workspaceHandler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));
            $workspaceId = $currentWorkspace->workspace->id;

            $result = $handler->handle(new EvaluateAlertRulesCommand(
                workspaceId: $workspaceId,
                ruleId: $request->query('rule_id'),
                warehouseId: $request->query('warehouse_id'),
            ));

            return response()->json([
                'rules_evaluated' => $result->rulesEvaluated,
                'alerts_triggered' => $result->alertsTriggered,
                'alerts_created' => $result->alertsCreated,
                'alerts_updated' => $result->alertsUpdated,
            ]);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'FORBIDDEN'], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json(['message' => $e->getMessage(), 'code' => 'NOT_FOUND'], 404);
        }
    }
}
