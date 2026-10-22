<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Modules;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use App\Models\CmsModule;
use Illuminate\Contracts\View\View;

final class ModulesGetController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager,
        private readonly ModuleSettingsServiceInterface $settings
    ) {}

    public function __invoke(): View
    {
        $modules = $this->moduleManager->catalog();

        return view('admin.modules.index', [
            'modules' => $modules,
            'configuration' => $modules->mapWithKeys(fn (CmsModule $module): array => [$module->identifier => [
                'hasSchema' => $module->is_installed && $this->settings->getSchema($module->identifier) !== [],
                'configured' => $this->settings->isConfigured($module->identifier),
            ]]),
        ]);
    }
}
