<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Updates;

use App\Contracts\Services\UpdateCatalogServiceInterface;
use App\Http\Requests\Admin\Updates\UpdateRequest;
use Illuminate\Http\JsonResponse;

final class UpdateCheckPostController
{
    public function __invoke(UpdateRequest $request, UpdateCatalogServiceInterface $catalog): JsonResponse
    {
        $state = $catalog->check($request->boolean('force'));

        return response()->json($state, ($state['error'] ?? null) === null ? 200 : 503);
    }
}
