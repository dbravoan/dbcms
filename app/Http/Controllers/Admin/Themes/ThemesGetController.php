<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Themes;

use App\Http\Controllers\Controller;
use App\Services\Cms\ThemeManagerService;
use Illuminate\Contracts\View\View;

final class ThemesGetController extends Controller
{
    public function __construct(
        private readonly ThemeManagerService $themeManager
    ) {}

    public function __invoke(): View
    {
        $themes = $this->themeManager->getAvailableThemes();
        $activeTheme = $this->themeManager->getActiveTheme();

        return view('admin.themes.index', [
            'themes' => $themes,
            'activeTheme' => $activeTheme,
        ]);
    }
}
