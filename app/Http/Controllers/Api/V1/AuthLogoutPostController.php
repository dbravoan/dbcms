<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\UserAuthServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthLogoutPostController extends Controller
{
    public function __invoke(Request $request, UserAuthServiceInterface $service): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $service->revokeTokens($user);

        return response()->json(['message' => 'Tokens revoked successfully.']);
    }
}
