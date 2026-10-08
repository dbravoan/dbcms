<?php

declare(strict_types=1);

namespace App\Services\Cms;

use App\Core\Themes\ThemeManifestReader;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;

class ThemeManagerService
{
    /**
     * Last theme whose view resolution was synced.
     */
    private ?string $syncedTheme = null;

    public function __construct(
        private readonly ThemeManifestReader $manifests,
    ) {}

    /**
     * Clears cached view resolution when the active theme changes.
     *
     * Front themes are selected through `cms.theme`. The value only changes via
     * `.env` / the Themes panel, i.e. with a fresh process or after `view:clear`.
     * This hook exists so a theme switch inside a long-lived process (queue worker,
     * test suite) doesn't serve the previous theme's views.
     */
    public function syncActiveTheme(): void
    {
        $theme = $this->getActiveTheme();

        if ($this->syncedTheme === $theme) {
            return;
        }

        $this->syncedTheme = $theme;

        try {
            $factory = View::getFacadeRoot();

            // Drop memoised resolved views so a theme switch inside a long-lived
            // process is picked up. Compiled Blade files are keyed by view path,
            // so they are NOT deleted on every request; an explicit switch
            // flushes them through flushCompiledViews().
            $factory->flushState();
            $factory->getFinder()->flush();
        } catch (\Throwable) {
            // View factory not ready (e.g. during early boot).
        }
    }

    /**
     * Drop the compiled Blade cache. Only needed when the active theme changes.
     */
    public function flushCompiledViews(): void
    {
        foreach (glob(storage_path('framework/views/*.php')) ?: [] as $compiled) {
            @unlink($compiled);
        }
    }

    /**
     * Gets list of available themes in themes/ directory.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getAvailableThemes(): array
    {
        $themesDir = base_path('themes');
        if (! File::exists($themesDir)) {
            File::makeDirectory($themesDir, 0755, true);
        }

        $themes = [];
        foreach (File::directories($themesDir) as $dir) {
            $identifier = basename($dir);
            $manifest = $this->manifests->read($dir);

            $themes[$identifier] = $manifest !== []
                ? $manifest
                : [
                    'name' => ucfirst($identifier),
                    'identifier' => $identifier,
                    'version' => '1.0.0',
                    'description' => "Tema {$identifier}",
                ];
        }

        return $themes;
    }

    /**
     * Returns currently active theme identifier.
     */
    public function getActiveTheme(): string
    {
        return (string) config('cms.theme', 'db-cyber');
    }

    /**
     * Sets active theme in runtime/session or writes to .env.
     */
    public function setActiveTheme(string $themeIdentifier): bool
    {
        config(['cms.theme' => $themeIdentifier]);
        $this->flushCompiledViews();

        // Persist to .env
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $content = (string) File::get($envPath);
            if (preg_match('/^CMS_THEME=/m', $content)) {
                $content = (string) preg_replace('/^CMS_THEME=.*/m', "CMS_THEME={$themeIdentifier}", $content);
            } else {
                $content .= "\nCMS_THEME={$themeIdentifier}";
            }
            File::put($envPath, $content);
        }

        return true;
    }
}
