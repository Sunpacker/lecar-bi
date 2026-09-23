<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Commands\ChangeWorkspaceMemberRoleCommand;
use App\Modules\Workspace\Application\Commands\ChangeWorkspaceMemberRoleHandler;
use App\Modules\Workspace\Application\Queries\GetWorkspaceMembersHandler;
use App\Modules\Workspace\Application\Queries\GetWorkspaceMembersQuery;
use App\Modules\Workspace\Domain\Exceptions\InsufficientWorkspaceCapabilityException;
use App\Modules\Workspace\Domain\Exceptions\LastWorkspaceOwnerException;
use App\Modules\Workspace\Domain\Exceptions\UnauthorizedWorkspaceAccessException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceMemberNotFoundException;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Presentation\Requests\ChangeWorkspaceMemberRoleRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkspaceMemberController
{
    public function index(
        string $workspaceId,
        Request $request,
        GetWorkspaceMembersHandler $handler,
    ): JsonResponse {
        $actorUserId = (string) $request->attributes->get('authenticated_user_id');

        try {
            $members = $handler->handle(new GetWorkspaceMembersQuery(
                actorUserId: $actorUserId,
                workspaceId: $workspaceId,
            ));

            return response()->json([
                'items' => array_map(fn ($member) => [
                    'user' => [
                        'id' => $member->user->id,
                        'email' => $member->user->email,
                        'name' => $member->user->name,
                    ],
                    'role' => $member->role,
                ], $members),
            ]);
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

    public function updateRole(
        string $workspaceId,
        string $userId,
        ChangeWorkspaceMemberRoleRequest $request,
        ChangeWorkspaceMemberRoleHandler $handler,
    ): JsonResponse {
        $actorUserId = (string) $request->attributes->get('authenticated_user_id');
        $role = (string) $request->validated('role');

        try {
            $member = $handler->handle(new ChangeWorkspaceMemberRoleCommand(
                actorUserId: $actorUserId,
                workspaceId: $workspaceId,
                targetUserId: $userId,
                role: $role,
            ));

            return response()->json([
                'member' => [
                    'user' => [
                        'id' => $member->user->id,
                        'email' => $member->user->email,
                        'name' => $member->user->name,
                    ],
                    'role' => $member->role,
                ],
            ]);
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
        } catch (WorkspaceMemberNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'WORKSPACE_MEMBER_NOT_FOUND',
            ], 404);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        } catch (LastWorkspaceOwnerException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'LAST_WORKSPACE_OWNER',
            ], 409);
        }
    }
}
