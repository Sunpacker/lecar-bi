<?php

declare(strict_types=1);

namespace NotificationService\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TrustedServiceMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expectedSecret = (string) config('services.notification.shared_secret', 'test-secret');
        $providedSecret = (string) $request->header('X-Server-Secret', '');

        if ($providedSecret === '' || ! hash_equals($expectedSecret, $providedSecret)) {
            return response()->json([
                'message' => 'Unauthorized server secret',
                'code' => 'UNAUTHORIZED',
            ], 401);
        }

        $userId = $request->header('X-User-Id');
        $workspaceId = $request->header('X-Workspace-Id');

        if (! $userId || ! $workspaceId) {
            return response()->json([
                'message' => 'Missing authenticated user or workspace context',
                'code' => 'BAD_REQUEST',
            ], 400);
        }

        $request->attributes->set('auth_user_id', trim((string) $userId));
        $request->attributes->set('auth_workspace_id', trim((string) $workspaceId));

        return $next($request);
    }
}
