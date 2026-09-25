<?php

declare(strict_types=1);

namespace App\Modules\Workspace\Presentation\Middleware;

use App\Modules\Workspace\Application\Contracts\AuthTokenServiceInterface;
use App\Modules\Workspace\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateUserIdMiddleware
{
    public function __construct(
        private AuthTokenServiceInterface $tokenService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // 1. If request has Bearer token, ALWAYS validate the token
        $bearerToken = $request->bearerToken();
        if ($bearerToken !== null && trim($bearerToken) !== '') {
            $userId = $this->tokenService->resolveUserIdByToken(trim($bearerToken));
            if ($userId !== null) {
                $userModel = new UserModel([
                    'id' => $userId,
                    'email' => "{$userId}@autobi.internal",
                    'name' => "User {$userId}",
                ]);
                $userModel->exists = true;
                Sanctum::actingAs($userModel);

                $request->attributes->set('authenticated_user_id', $userId);

                return $next($request);
            }

            // Bearer token provided but invalid or revoked -> 401
            app('auth')->forgetGuards();

            return response()->json([
                'message' => 'Unauthenticated',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        // 2. Fallback: check if user was explicitly set on the guard (e.g. via actingAs without Bearer token)
        $guard = auth('sanctum');
        if ($guard->hasUser()) {
            $user = $guard->user();
            if ($user !== null) {
                $request->attributes->set('authenticated_user_id', (string) $user->id);

                return $next($request);
            }
        }

        // 3. No token and no authenticated user -> 401
        return response()->json([
            'message' => 'Unauthenticated',
            'code' => 'UNAUTHENTICATED',
        ], 401);
    }
}
