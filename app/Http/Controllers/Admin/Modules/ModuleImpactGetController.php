<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Modules;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class ModuleImpactGetController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(string $identifier): JsonResponse
    {
        abort_unless($this->moduleManager->isModuleDiscovered($identifier), 404);
        $impact = $this->moduleManager->getDeactivationImpact($identifier);

        return response()->json($impact);
    }
}
