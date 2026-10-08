<?php

declare(strict_types=1);

namespace App\Http\Controllers\Install;

use App\Contracts\Services\InstallationDatabaseServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Install\DatabaseSetupRequest;
use Illuminate\Http\JsonResponse;

final class DatabaseTestPostController extends Controller
{
    public function __construct(private readonly InstallationDatabaseServiceInterface $service) {}

    public function __invoke(DatabaseSetupRequest $request): JsonResponse
    {
        $result = $this->service->test($request->toDto());

        return response()->json($result->connectionResponse(), $result->success ? 200 : 422);
    }
}
