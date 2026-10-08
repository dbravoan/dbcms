<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\UserAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function register(RegisterRequest $request, UserAuthServiceInterface $service): JsonResponse
    {
        $user = $service->registerUser($request->toDTO());

        return (new UserResource($user))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request, UserAuthServiceInterface $service): JsonResponse
    {
        $authPayload = $service->authenticate($request->toDTO());

        return response()->json([
            'user' => new UserResource($authPayload['user']),
            'token' => $authPayload['token'],
            'expires_at' => $authPayload['expires_at'],
        ]);
    }

    public function logout(Request $request, UserAuthServiceInterface $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $service->revokeTokens($user);

        return response()->json(['message' => 'Tokens revoked successfully.']);
    }
}
