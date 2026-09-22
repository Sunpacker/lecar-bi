<?php

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Commands\AuthenticateUserCommand;
use App\Modules\Workspace\Application\Commands\AuthenticateUserHandler;
use App\Modules\Workspace\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Workspace\Presentation\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;

final class AuthController
{
    public function login(LoginRequest $request, AuthenticateUserHandler $handler): JsonResponse
    {
        try {
            $user = $handler->handle(new AuthenticateUserCommand(
                email: (string) $request->validated('email'),
                password: (string) $request->validated('password'),
            ));

            return response()->json([
                'user' => [
                    'id' => $user->id()->value(),
                    'email' => $user->email(),
                    'name' => $user->name(),
                ],
            ]);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }
    }
}
