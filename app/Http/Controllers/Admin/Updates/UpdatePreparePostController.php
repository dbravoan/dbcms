<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdatePreparationServiceInterface;
use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\JsonResponse;

final class UpdatePreparePostController
{
    public function __invoke(UpdateRequest $request, UpdatePreparationServiceInterface $service): JsonResponse
    {
        return response()->json($service->prepare((string) $request->validated('version'), (int) $request->user()?->getAuthIdentifier()));
    }
}
