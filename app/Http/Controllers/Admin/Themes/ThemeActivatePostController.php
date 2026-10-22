<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Themes;

use App\Http\Controllers\Controller;
use App\Services\Cms\ThemeManagerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ThemeActivatePostController extends Controller
{
    public function __construct(
        private readonly ThemeManagerService $themeManager
    ) {}

    public function __invoke(Request $request, string $theme): RedirectResponse
    {
        $this->themeManager->setActiveTheme($theme);

        return redirect()->route('admin.themes.index')->with('success', "Tema [{$theme}] activado exitosamente.");
    }
}
