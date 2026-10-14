<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdateExecutionServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class UpdateRunGetController
{
    public function __invoke(Request $request, string $run, UpdateExecutionServiceInterface $service): JsonResponse
    {
        return response()->json($service->status($run, (int) $request->user()?->getAuthIdentifier()));
    }
}
