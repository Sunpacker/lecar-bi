<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Commands\AuthenticateUserCommand;
use App\Modules\Workspace\Application\Commands\AuthenticateUserHandler;
use App\Modules\Workspace\Application\Contracts\AuthTokenServiceInterface;
use App\Modules\Workspace\Domain\Exceptions\InvalidCredentialsException;
use App\Modules\Workspace\Presentation\Requests\LoginRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController
{
    public function login(
        LoginRequest $request,
        AuthenticateUserHandler $handler,
        AuthTokenServiceInterface $tokenService
    ): JsonResponse {
        try {
            $user = $handler->handle(new AuthenticateUserCommand(
                email: (string) $request->validated('email'),
                password: (string) $request->validated('password'),
            ));

            $token = $tokenService->createToken($user->id()->value());

            return response()->json([
                'user' => [
                    'id' => $user->id()->value(),
                    'email' => $user->email(),
                    'name' => $user->name(),
                ],
                'token' => $token,
            ]);
        } catch (InvalidCredentialsException $e) {
            return response()->json([
                'message' => $e->getMessage(),
                'code' => 'INVALID_CREDENTIALS',
            ], 401);
        }
    }

    public function logout(Request $request, AuthTokenServiceInterface $tokenService): Response
    {
        $bearerToken = $request->bearerToken();
        if ($bearerToken !== null && trim($bearerToken) !== '') {
            $tokenService->revokeToken(trim($bearerToken));
        }

        $userId = (string) $request->attributes->get('authenticated_user_id');
        if ($userId !== '') {
            $tokenService->revokeAllUserTokens($userId);
        }

        return response()->noContent();
    }
}
