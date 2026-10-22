<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Modules;

use App\Core\Modules\ModuleManager;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;

final class ModuleInstallPostController extends Controller
{
    public function __construct(
        private readonly ModuleManager $moduleManager
    ) {}

    public function __invoke(string $identifier): RedirectResponse
    {
        try {
            $this->moduleManager->install($identifier);
        } catch (\DomainException $error) {
            return back()->with('error', $error->getMessage());
        }

        return back()->with('success', "Module '{$identifier}' installed successfully.");
    }
}
