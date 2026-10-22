<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\JsonResponse;

final class UpdateAdvancePostController
{
    public function __invoke(UpdateRequest $request, string $run, UpdateExecutionServiceInterface $service): JsonResponse
    {
        return response()->json($service->advance($run, (int) $request->user()?->getAuthIdentifier()));
    }
}
