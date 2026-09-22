<?php

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Queries\GetAccessibleWorkspacesHandler;
use App\Modules\Workspace\Application\Queries\GetAccessibleWorkspacesQuery;
use App\Modules\Workspace\Application\Queries\GetWorkspaceByIdHandler;
use App\Modules\Workspace\Application\Queries\GetWorkspaceByIdQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceController
{
    public function index(Request $request, GetAccessibleWorkspacesHandler $handler): JsonResponse
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $workspaces = $handler->handle(new GetAccessibleWorkspacesQuery($userId));

        return response()->json([
            'items' => array_map(fn ($ws) => [
                'id' => $ws->id,
                'name' => $ws->name,
                'slug' => $ws->slug,
                'role' => $ws->role,
            ], $workspaces),
        ]);
    }

    public function show(string $id, Request $request, GetWorkspaceByIdHandler $handler): JsonResponse
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');

        try {
            $workspace = $handler->handle(new GetWorkspaceByIdQuery($userId, $id));

            return response()->json([
                'id' => $workspace->id,
                'name' => $workspace->name,
                'slug' => $workspace->slug,
                'role' => $workspace->role,
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
