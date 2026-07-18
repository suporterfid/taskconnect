<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var \App\Infrastructure\Persistence\Eloquent\User $user */
        $user = $request->user();
        $user->loadMissing('preferences');

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }
}
