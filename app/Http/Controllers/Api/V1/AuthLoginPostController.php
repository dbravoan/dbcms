<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\UserAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class AuthLoginPostController extends Controller
{
    public function __invoke(LoginRequest $request, UserAuthServiceInterface $service): JsonResponse
    {
        $authPayload = $service->authenticate($request->toDTO());

        return response()->json([
            'user' => new UserResource($authPayload['user']),
            'token' => $authPayload['token'],
            'expires_at' => $authPayload['expires_at'],
        ]);
    }
}
