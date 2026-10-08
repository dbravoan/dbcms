<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Modules;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use App\Models\CmsModule;
use Illuminate\Contracts\View\View;

final class ModulesGetController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(): View
    {
        // Auto-discover modules on disk
        $this->moduleManager->discoverModules();

        $modules = CmsModule::all();

        return view('admin.modules.index', [
            'modules' => $modules,
        ]);
    }
}
