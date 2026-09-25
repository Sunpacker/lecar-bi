<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Commands\AcceptInvitationCommand;
use App\Modules\Workspace\Application\Commands\AcceptInvitationHandler;
use App\Modules\Workspace\Application\Commands\CancelInvitationCommand;
use App\Modules\Workspace\Application\Commands\CancelInvitationHandler;
use App\Modules\Workspace\Application\Commands\CreateInvitationCommand;
use App\Modules\Workspace\Application\Commands\CreateInvitationHandler;
use App\Modules\Workspace\Application\Commands\ResendInvitationCommand;
use App\Modules\Workspace\Application\Commands\ResendInvitationHandler;
use App\Modules\Workspace\Application\Queries\GetInvitationDetailsHandler;
use App\Modules\Workspace\Application\Queries\GetInvitationDetailsQuery;
use App\Modules\Workspace\Application\Queries\ListInvitationsHandler;
use App\Modules\Workspace\Application\Queries\ListInvitationsQuery;
use App\Modules\Workspace\Domain\Exceptions\WorkspaceNotFoundException;
use App\Modules\Workspace\Domain\MembershipRole;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class InvitationController
{
    public function index(string $workspaceId, ListInvitationsHandler $handler): JsonResponse
    {
        $invitations = $handler->handle(new ListInvitationsQuery($workspaceId));

        return response()->json([
            'items' => array_map(fn ($inv) => [
                'id' => $inv->id,
                'workspace_id' => $inv->workspaceId,
                'email' => $inv->email,
                'role' => $inv->role,
                'status' => $inv->status,
                'expires_at' => $inv->expiresAt,
                'created_at' => $inv->createdAt,
            ], $invitations),
        ]);
    }

    public function store(string $workspaceId, Request $request, CreateInvitationHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', 'in:member,viewer'],
        ]);

        try {
            $invitation = $handler->handle(new CreateInvitationCommand(
                workspaceId: $workspaceId,
                email: (string) $validated['email'],
                role: MembershipRole::from((string) $validated['role']),
            ));

            return response()->json([
                'id' => $invitation->id,
                'workspace_id' => $invitation->workspaceId,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at' => $invitation->expiresAt,
                'created_at' => $invitation->createdAt,
            ], 201);
        } catch (WorkspaceNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function resend(string $workspaceId, string $invitationId, ResendInvitationHandler $handler): JsonResponse
    {
        try {
            $invitation = $handler->handle(new ResendInvitationCommand(
                workspaceId: $workspaceId,
                invitationId: $invitationId,
            ));

            return response()->json([
                'id' => $invitation->id,
                'workspace_id' => $invitation->workspaceId,
                'email' => $invitation->email,
                'role' => $invitation->role,
                'status' => $invitation->status,
                'expires_at' => $invitation->expiresAt,
                'created_at' => $invitation->createdAt,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function destroy(string $workspaceId, string $invitationId, CancelInvitationHandler $handler): Response|JsonResponse
    {
        try {
            $handler->handle(new CancelInvitationCommand(
                workspaceId: $workspaceId,
                invitationId: $invitationId,
            ));

            return response()->noContent();
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function showPublic(string $token, GetInvitationDetailsHandler $handler): JsonResponse
    {
        try {
            $details = $handler->handle(new GetInvitationDetailsQuery($token));

            return response()->json([
                'email' => $details->email,
                'workspace_name' => $details->workspaceName,
                'role' => $details->role,
                'is_expired' => $details->isExpired,
                'is_existing_user' => $details->isExistingUser,
            ]);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function accept(string $token, Request $request, AcceptInvitationHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        try {
            $result = $handler->handle(new AcceptInvitationCommand(
                rawToken: $token,
                name: $validated['name'] ?? null,
                password: $validated['password'] ?? null,
            ));

            return response()->json([
                'user' => [
                    'id' => $result['user']->id,
                    'email' => $result['user']->email,
                    'name' => $result['user']->name,
                ],
                'workspace_id' => $result['workspace_id'],
                'token' => $result['token'],
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        } catch (\DomainException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'BAD_REQUEST',
            ], 400);
        }
    }
}
