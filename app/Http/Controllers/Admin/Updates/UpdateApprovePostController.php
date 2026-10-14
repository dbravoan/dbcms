<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Http\Adapters\UpdateApprovalAdapter;
use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\JsonResponse;

final class UpdateApprovePostController
{
    public function __invoke(UpdateRequest $request, string $run, UpdateApprovalAdapter $service): JsonResponse
    {
        return response()->json($service->approve($request, $run));
    }
}
