<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdatePreparationServiceInterface;
use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\JsonResponse;

final class UpdateAdoptPostController
{
    public function __invoke(UpdateRequest $request, UpdatePreparationServiceInterface $service): JsonResponse
    {
        return response()->json($service->adopt((string) $request->validated('version'), (string) $request->validated('distribution', 'source'), (int) $request->user()?->getAuthIdentifier()));
    }
}
