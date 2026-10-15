<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Contracts\Services\BrandServiceInterface;
use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\DTOs\BrandIdentity;
use App\Services\Cms\ThemeManagerService;
use Modules\Captcha\Services\CaptchaService;

if (! function_exists('cms_asset_entries')) {
    /** @return list<string> Shared runtime plus the selected brand and public theme. */
    function cms_asset_entries(bool $frontend = false): array
    {
        $entries = ['resources/css/app.css', 'resources/js/app.js'];
        $brand = brand()->theme;
        $brandCss = "resources/views/themes/admin/{$brand}/theme.css";
        if ($brand !== '' && is_file(base_path($brandCss))) {
            $entries[] = $brandCss;
        }
        if ($frontend) {
            $theme = app(ThemeManagerService::class)->getActiveTheme();
            $themeCss = "themes/{$theme}/theme.css";
            if (is_file(base_path($themeCss))) {
                $entries[] = $themeCss;
            }
        }

        return $entries;
    }
}

if (! function_exists('theme_asset')) {
    /**
     * Resolve the URL of an asset shipped inside a backoffice theme's assets/ dir.
     *
     * `theme_asset('termocms.css')` uses the active theme; pass an explicit theme
     * id as the second argument to target another one.
     */
    function theme_asset(string $path, ?string $theme = null): string
    {
        if (! app()->bound(AdminThemeServiceInterface::class)) {
            return asset('themes/admin/'.($theme ?? 'dbcms').'/'.ltrim($path, '/'));
        }

        /** @var AdminThemeServiceInterface $themes */
        $themes = app(AdminThemeServiceInterface::class);

        return $themes->themeAsset($theme ?? $themes->getActiveTheme(), $path);
    }
}

if (! function_exists('brand')) {
    /**
     * Active backoffice brand identity (from the active theme manifest).
     *
     * Single accessor for brand values — never hardcode a brand in views/config.
     *
     * @see docs/18_THEME_MANIFEST_AND_BRAND_REFERENCE.md
     */
    function brand(): BrandIdentity
    {
        return app(BrandServiceInterface::class)->current();
    }
}

if (! function_exists('module_setting')) {
    /**
     * Get a setting value for a specific CMS module with optional fallback.
     */
    function module_setting(string $module, string $key, mixed $default = null): mixed
    {
        if (app()->bound(ModuleSettingsServiceInterface::class)) {
            return app(ModuleSettingsServiceInterface::class)->get($module, $key, $default);
        }

        return config("modules.{$module}.{$key}", $default);
    }
}

if (! function_exists('setting')) {
    /**
     * Get a general CMS site setting value with optional fallback.
     */
    function setting(string $key, mixed $default = null): mixed
    {
        if (app()->bound(GeneralSettingsServiceInterface::class)) {
            return app(GeneralSettingsServiceInterface::class)->get($key, $default);
        }

        return $default;
    }
}

if (! function_exists('captcha_available')) {
    /**
     * Whether the Captcha module is installed and enabled.
     *
     * Safe when the module is absent: it short-circuits instead of resolving a
     * class that does not exist on disk. Captcha is a form *field*: this only
     * tells whether the type is offered/honoured, never forces it on a form.
     */
    function captcha_available(): bool
    {
        $service = CaptchaService::class;

        if (! class_exists($service)) {
            return false;
        }

        try {
            return (bool) app($service)->isEnabled();
        } catch (Throwable) {
            return false;
        }
    }
}
