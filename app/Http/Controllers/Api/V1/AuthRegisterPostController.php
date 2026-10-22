<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\UserAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;

final class AuthRegisterPostController extends Controller
{
    public function __invoke(RegisterRequest $request, UserAuthServiceInterface $service): JsonResponse
    {
        $user = $service->registerUser($request->toDTO());

        return (new UserResource($user))->response()->setStatusCode(201);
    }
}
