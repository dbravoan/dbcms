<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Modules;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class ModuleUninstallPostController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(string $identifier): RedirectResponse
    {
        if ($this->moduleManager->isCore($identifier)) {
            return back()->with('error', "El módulo '{$identifier}' es un módulo troncal esencial del sistema y no puede ser desinstalado.");
        }

        $this->moduleManager->uninstall($identifier);

        return back()->with('success', "Module '{$identifier}' uninstalled successfully.");
    }
}
