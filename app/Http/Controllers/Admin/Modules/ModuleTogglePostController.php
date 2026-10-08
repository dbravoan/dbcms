<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Modules;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use App\Models\CmsModule;
use Illuminate\Http\RedirectResponse;

final class ModuleTogglePostController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(string $identifier): RedirectResponse
    {
        /** @var CmsModule $module */
        $module = CmsModule::where('identifier', $identifier)->firstOrFail();

        if ($module->is_active) {
            $this->moduleManager->deactivate($identifier);
            $msg = "Module '{$identifier}' deactivated.";
        } else {
            $this->moduleManager->activate($identifier);
            $msg = "Module '{$identifier}' activated.";
        }

        return back()->with('success', $msg);
    }
}
