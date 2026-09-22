<?php

namespace App\Modules\Workspace\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateUserIdMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $userId = $request->header('X-User-Id');

        if ($userId === null || trim($userId) === '') {
            return response()->json([
                'message' => 'Unauthenticated: missing X-User-Id header',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        $request->attributes->set('authenticated_user_id', trim($userId));

        return $next($request);
    }
}
