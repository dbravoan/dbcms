<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCancelPostController
{
    public function __invoke(UpdateRequest $request, string $run, UpdateExecutionServiceInterface $service): JsonResponse
    {
        return response()->json($service->cancel($run, (int) $request->user()?->getAuthIdentifier()));
    }
}
