<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Commands\ChangePasswordCommand;
use App\Modules\Workspace\Application\Commands\ChangePasswordHandler;
use App\Modules\Workspace\Application\Commands\UpdateProfileCommand;
use App\Modules\Workspace\Application\Commands\UpdateProfileHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentUserHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentUserQuery;
use App\Modules\Workspace\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Workspace\Domain\Exceptions\UserNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class ProfileController
{
    public function me(Request $request, GetCurrentUserHandler $handler): JsonResponse
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');

        try {
            $user = $handler->handle(new GetCurrentUserQuery($userId));

            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function update(Request $request, UpdateProfileHandler $handler): JsonResponse
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        try {
            $user = $handler->handle(new UpdateProfileCommand(
                userId: $userId,
                name: (string) $validated['name'],
            ));

            return response()->json([
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
            ]);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }

    public function changePassword(Request $request, ChangePasswordHandler $handler): Response|JsonResponse
    {
        $userId = (string) $request->attributes->get('authenticated_user_id');
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        try {
            $handler->handle(new ChangePasswordCommand(
                userId: $userId,
                currentPassword: (string) $validated['current_password'],
                newPassword: (string) $validated['new_password'],
            ));

            return response()->noContent();
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'message' => 'Неверный текущий пароль.',
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        } catch (UserNotFoundException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'NOT_FOUND',
            ], 404);
        }
    }
}
