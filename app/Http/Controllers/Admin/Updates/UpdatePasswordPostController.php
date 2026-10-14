<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\JsonResponse;

final class UpdatePasswordPostController
{
    public function __invoke(UpdateRequest $request): JsonResponse
    {
        $request->session()->put('auth.password_confirmed_at', time());

        return response()->json(['confirmed' => true]);
    }
}
