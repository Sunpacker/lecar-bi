<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Presentation\Middleware;

use App\Modules\Workspace\Application\Guards\WorkspaceAccessGuard;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\InsufficientWorkspaceCapabilityException;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\WorkspaceCapability;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class RequireWorkspaceCapabilityMiddleware
{
    public function __construct(
        private GetCurrentWorkspaceHandler $currentWorkspaceHandler,
        private WorkspaceAccessGuard $accessGuard,
    ) {}

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');

        if ($userId === '') {
            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $routeWorkspaceId = $request->route('workspaceId');
        $headerWorkspaceId = $request->header('X-Workspace-Id');
        $requestedWorkspaceId = is_string($routeWorkspaceId) && $routeWorkspaceId !== ''
            ? $routeWorkspaceId
            : (is_string($headerWorkspaceId) && $headerWorkspaceId !== '' ? $headerWorkspaceId : null);

        try {
            $currentWorkspace = $this->currentWorkspaceHandler->handle(
                new GetCurrentWorkspaceQuery($userId, $requestedWorkspaceId)
            );
            $workspaceId = $currentWorkspace->workspace->id;

            $parsedCapability = WorkspaceCapability::from($capability);
            $workspace = $this->accessGuard->assertCapability($userId, $workspaceId, $parsedCapability);

            $request->attributes->set('current_workspace', $currentWorkspace);
            $request->attributes->set('current_workspace_id', $workspaceId);
            $request->attributes->set('workspace', $workspace);

            return $next($request);
        } catch (UnauthorizedWorkspaceAccessException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'FORBIDDEN',
            ], 403);
        } catch (InsufficientWorkspaceCapabilityException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INSUFFICIENT_CAPABILITY',
            ], 403);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }
}
