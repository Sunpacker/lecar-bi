<?php

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentWorkspaceQuery;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CurrentWorkspaceController
{
    public function show(Request $request, GetCurrentWorkspaceHandler $handler): JsonResponse
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $requestedWs = $request->header('X-Workspace-Id');

        try {
            $current = $handler->handle(new GetCurrentWorkspaceQuery($userId, $requestedWs));

            return response()->json([
                'user' => [
                    'id' => $current->user->id,
                    'email' => $current->user->email,
                    'name' => $current->user->name,
                ],
                'workspace' => [
                    'id' => $current->workspace->id,
                    'name' => $current->workspace->name,
                    'slug' => $current->workspace->slug,
                    'role' => $current->workspace->role,
                    'capabilities' => $current->workspace->capabilities,
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
