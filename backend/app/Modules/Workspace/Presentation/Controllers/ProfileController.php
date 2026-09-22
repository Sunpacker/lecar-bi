<?php

namespace App\Modules\Workspace\Presentation\Controllers;

use App\Modules\Workspace\Application\Queries\GetCurrentUserHandler;
use App\Modules\Workspace\Application\Queries\GetCurrentUserQuery;
use App\Modules\Workspace\Domain\Exceptions\UserNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
}
