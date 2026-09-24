<?php

declare(strict_types=1);

namespace App\Modules\Support\Presentation\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateSupportTransportMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('support.bff_shared_secret');
        $provided = (string) $request->header('X-Support-BFF-Key');
        if (strlen($expected) < 32 || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Unauthenticated support transport',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        }

        return $next($request);
    }
}
