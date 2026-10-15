<?php

declare(strict_types=1);

namespace App\Core\Modules;

use App\Contracts\Services\ModulePackageInstallerInterface;
use App\Contracts\Services\ModulePackageUploadServiceInterface;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Core\Search\SearchIndexer;
use App\DTOs\Modules\ModulePackageDTO;
use App\Events\ModuleAvailabilityChanged;
use App\Models\CmsModule;
use App\Models\MenuItem;
use App\Services\Cms\PageCacheService;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Modules\Pages\Models\PageSection;

class ModuleManager implements ModulePackageUploadServiceInterface
{
    /**
     * @var array<string, ModuleInterface>
     */
    protected array $activeModules = [];

    /** @var array<string, ModuleInterface> */
    protected array $registeredModules = [];

    public function __construct(
        protected Application $app
    ) {}

    /**
     * Scans modules directory and syncs all discovered modules to database.
     *
     * @return array<string, array<string, mixed>>
     */
    public function discoverModules(): array
    {
        $modulesPath = base_path('modules');

        if (! File::exists($modulesPath)) {
            File::makeDirectory($modulesPath, 0755, true);
        }

        $discovered = [];
        $moduleDirectories = File::directories($modulesPath);

        foreach ($moduleDirectories as $dir) {
            $manifestPath = $dir.'/module.json';

            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) File::get($manifestPath), true);

            if (! is_array($manifest) || ! isset($manifest['identifier'], $manifest['provider'])) {
                continue;
            }

            $identifier = (string) $manifest['identifier'];
            $discovered[$identifier] = $manifest;

            // Sync to database if table exists
            try {
                $isCore = (bool) ($manifest['is_core'] ?? false) || in_array($identifier, config('cms.core_modules', ['pages']), true);
                $module = CmsModule::firstOrNew(['identifier' => $identifier]);
                if (! $module->exists) {
                    $module->fill([
                        'name' => (string) ($manifest['name'] ?? $identifier),
                        'version' => (string) ($manifest['version'] ?? '1.0.0'),
                        'description' => (string) ($manifest['description'] ?? ''),
                        'provider' => (string) $manifest['provider'],
                        'is_pageable' => (bool) ($manifest['is_pageable'] ?? false),
                        'is_core' => $isCore,
                        'is_installed' => true,
                        'is_active' => (bool) ($manifest['default_active'] ?? true),
                    ])->save();
                } else {
                    $module->update([
                        'name' => (string) ($manifest['name'] ?? $identifier),
                        'version' => (string) ($manifest['version'] ?? '1.0.0'),
                        'description' => (string) ($manifest['description'] ?? ''),
                        'provider' => (string) $manifest['provider'],
                        'is_core' => $isCore,
                        'is_pageable' => (bool) ($manifest['is_pageable'] ?? false),
                    ]);
                }
            } catch (\Throwable) {
                // Table might not be migrated yet
            }
        }

        return $discovered;
    }

    /**
     * Boots all active modules by registering their service providers.
     */
    public function bootActiveModules(): void
    {
        // 1. In testing or fresh boot, discover modules directly
        $discovered = $this->discoverModules();

        // Register providers deterministically; route middleware checks availability.
        // Ensure 'pages' module is booted last so its clean /{slug} catch-all does not shadow other modules (blog, shop, etc.)
        if (isset($discovered['pages'])) {
            $pagesManifest = $discovered['pages'];
            unset($discovered['pages']);
            $discovered['pages'] = $pagesManifest;
        }

        foreach ($discovered as $identifier => $manifest) {
            // Load current installation state without making route registration conditional.
            try {
                $moduleRecord = CmsModule::where('identifier', $identifier)->first();
            } catch (\Throwable) {
                // DB might not be ready or migrated during installation
                $moduleRecord = null;
            }

            $providerClass = $manifest['provider'] ?? '';

            if (class_exists($providerClass)) {
                /** @var BaseModuleServiceProvider $provider */
                $provider = $this->app->register($providerClass);

                // NOTE: do NOT call boot() explicitly here. `app->register()` boots
                // the provider when the application is already booted, and providers
                // registered during the boot phase are booted by Laravel's boot loop.
                // Booting again would run every provider's boot() twice (duplicated
                // view composers, re-registered routes, etc.).

                if ($provider instanceof ModuleInterface) {
                    $this->registeredModules[$identifier] = $provider;
                    if ($moduleRecord === null || ($moduleRecord->is_active && $moduleRecord->is_installed)) {
                        $this->activeModules[$identifier] = $provider;
                    }

                    // Sync stored module settings into Laravel runtime config
                    if ($moduleRecord && is_array($moduleRecord->settings)) {
                        foreach ($moduleRecord->settings as $sKey => $sVal) {
                            config(["modules.{$identifier}.{$sKey}" => $sVal]);
                        }
                    }
                }
            }
        }
    }

    /**
     * Installs a module by running its migrations.
     */
    public function install(string $identifier): bool
    {
        $module = CmsModule::where('identifier', $identifier)->firstOrFail();
        $migrationPath = $this->migrationPath($identifier);

        if (File::exists(base_path($migrationPath))) {
            $exitCode = Artisan::call('migrate', [
                '--path' => $migrationPath,
                '--force' => true,
            ]);
            if ($exitCode !== 0) {
                throw new \RuntimeException('Module migrations failed; installation state was not changed.');
            }
        }

        $manifest = $this->getModuleManifest($identifier);
        $active = $module->is_active || (bool) ($manifest['default_active'] ?? true);
        $module->update(['is_installed' => true, 'is_active' => $active]);
        if ($active && isset($this->registeredModules[$identifier])) {
            $this->activeModules[$identifier] = $this->registeredModules[$identifier];
        } else {
            unset($this->activeModules[$identifier]);
        }
        app(PageCacheService::class)->invalidate();

        $this->rebuildSearchAfterCommit($identifier);

        $this->notifyAvailabilityAfterCommit($identifier, 'install');

        return true;
    }

    /**
     * Uninstalls availability while preserving migrations and all user data.
     */
    public function uninstall(string $identifier): bool
    {
        $module = CmsModule::where('identifier', $identifier)->firstOrFail();
        $module->update(['is_installed' => false, 'is_active' => false]);
        unset($this->activeModules[$identifier]);
        app(PageCacheService::class)->invalidate();

        $this->notifyAvailabilityAfterCommit($identifier, 'uninstall');

        return true;
    }

    /**
     * Activates a module.
     */
    public function activate(string $identifier): bool
    {
        $updated = (bool) CmsModule::where('identifier', $identifier)->where('is_installed', true)->update(['is_active' => true]);
        if ($updated && isset($this->registeredModules[$identifier])) {
            $this->activeModules[$identifier] = $this->registeredModules[$identifier];
        }
        app(PageCacheService::class)->invalidate();

        if ($updated) {
            $this->rebuildSearchAfterCommit($identifier);
            $this->notifyAvailabilityAfterCommit($identifier, 'activate');
        }

        return $updated;
    }

    /**
     * Deactivates a module.
     */
    public function deactivate(string $identifier): bool
    {
        unset($this->activeModules[$identifier]);

        $updated = (bool) CmsModule::where('identifier', $identifier)->update(['is_active' => false]);
        app(PageCacheService::class)->invalidate();

        if ($updated) {
            $this->notifyAvailabilityAfterCommit($identifier, 'deactivate');
        }

        return $updated;
    }

    private function notifyAvailabilityAfterCommit(string $identifier, string $operation): void
    {
        $event = new ModuleAvailabilityChanged($identifier, $this->isModuleEnabled($identifier), $operation);
        DB::afterCommit(static fn () => event($event));
    }

    private function rebuildSearchAfterCommit(string $identifier): void
    {
        DB::afterCommit(function () use ($identifier): void {
            if (! $this->app->bound(SearchSourceRegistryInterface::class) || ! Schema::hasTable('search_documents')) {
                return;
            }
            $sources = $this->app->make(SearchSourceRegistryInterface::class);
            foreach ($sources->all() as $source) {
                if ($identifier === 'search' || $this->ownerOfClass($source::class) === $identifier) {
                    $this->app->make(SearchIndexer::class)->indexSource($source->type(), replace: true);
                }
            }
        });
    }

    /**
     * Alias for deactivate.
     */
    public function disable(string $identifier): bool
    {
        return $this->deactivate($identifier);
    }

    /**
     * Alias for activate.
     */
    public function enable(string $identifier): bool
    {
        return $this->activate($identifier);
    }

    /**
     * Uploads and extracts a module ZIP package.
     */
    public function uploadPackage(UploadedFile $zipFile): string
    {
        return $this->upload(new ModulePackageDTO($zipFile->getPathname(), (int) $zipFile->getSize()));
    }

    public function upload(ModulePackageDTO $upload): string
    {
        $installer = $this->app->make(ModulePackageInstallerInterface::class);
        $package = $installer->install($upload);
        try {
            DB::transaction(function () use ($package): void {
                CmsModule::create([
                    'identifier' => $package->manifest['identifier'],
                    'name' => $package->manifest['name'],
                    'version' => $package->manifest['version'],
                    'description' => $package->manifest['description'] ?? '',
                    'provider' => $package->manifest['provider'],
                    'is_pageable' => (bool) ($package->manifest['is_pageable'] ?? false),
                    'is_core' => false,
                    'is_installed' => false,
                    'is_active' => false,
                ]);
            });
        } catch (\Throwable $error) {
            $installer->rollback($package);
            throw $error;
        }

        return 'Module package uploaded and registered; install it to run migrations and activate it.';
    }

    /**
     * @return array<string, ModuleInterface>
     */
    public function getActiveModules(): array
    {
        return $this->activeModules;
    }

    /** Resolve ownership from registered manifests, including custom identifiers. */
    public function ownerOfClass(string $class): ?string
    {
        foreach ($this->registeredModules as $identifier => $provider) {
            $namespace = (new \ReflectionClass($provider))->getNamespaceName();
            if ($namespace !== '' && str_starts_with($class, $namespace.'\\')) {
                return $identifier;
            }
        }

        return null;
    }

    /**
     * Checks if a module is currently active and enabled.
     */
    public function isActive(string $identifier): bool
    {
        try {
            $record = CmsModule::where('identifier', $identifier)->first();
            if ($record !== null) {
                if (! (bool) $record->is_active || ! (bool) $record->is_installed) {
                    return false;
                }

                // Verify module settings toggle if configured. Exclusions:
                // - 'maintenance': its is_enabled toggle locks the site with a 503, not module availability.
                // - 'content_audit': its is_enabled toggle governs the quality flow (observer), never the
                //   module itself, so the audit backoffice (including its settings) must stay reachable.
                if ($identifier !== 'maintenance' && $identifier !== 'content_audit' && is_array($record->settings) && array_key_exists('is_enabled', $record->settings)) {
                    $enabled = $record->settings['is_enabled'];
                    if ($enabled === false || $enabled === 0 || $enabled === '0' || $enabled === 'false') {
                        return false;
                    }
                }

                return true;
            }
        } catch (\Throwable) {
            // DB not ready or connection issue
        }

        return isset($this->activeModules[$identifier]);
    }

    /** Resolve case-sensitive module paths from the provider, never its identifier. */
    private function migrationPath(string $identifier): string
    {
        $manifest = $this->getModuleManifest($identifier);
        $provider = $manifest['provider'] ?? null;
        if (! is_string($provider) || ! class_exists($provider)) {
            throw new \RuntimeException('Module provider was not discovered.');
        }
        $file = (new \ReflectionClass($provider))->getFileName();
        $directory = $file === false ? false : realpath(dirname($file));
        $root = realpath(base_path('modules'));
        if ($directory === false || $root === false || ! str_starts_with($directory, $root.DIRECTORY_SEPARATOR)) {
            throw new \RuntimeException('Module provider is outside the modules directory.');
        }

        return substr($directory, strlen(base_path()) + 1).'/Database/Migrations';
    }

    /**
     * Alias for isActive to check if a module is enabled.
     */
    public function isModuleEnabled(string $identifier): bool
    {
        return $this->isActive($identifier);
    }

    /**
     * Checks if a module manifest has been discovered on disk.
     */
    public function isModuleDiscovered(string $identifier): bool
    {
        return $this->getModuleManifest($identifier) !== null;
    }

    /**
     * Retrieves the parsed module.json manifest for a given module identifier.
     *
     * @return array<string, mixed>|null
     */
    public function getModuleManifest(string $identifier): ?array
    {
        $modulesPath = base_path('modules');
        if (! File::exists($modulesPath)) {
            return null;
        }

        foreach (File::directories($modulesPath) as $dir) {
            $manifestPath = $dir.'/module.json';
            if (! File::exists($manifestPath)) {
                continue;
            }

            $manifest = json_decode((string) File::get($manifestPath), true);
            if (is_array($manifest) && ($manifest['identifier'] ?? '') === $identifier) {
                /** @var array<string, mixed> $manifest */
                return $manifest;
            }
        }

        return null;
    }

    /**
     * Checks if a module is marked as core / trunk.
     */
    public function isCore(string $identifier): bool
    {
        $identifier = strtolower($identifier);

        if (in_array($identifier, (array) config('cms.core_modules', ['pages']), true)) {
            return true;
        }

        try {
            $record = CmsModule::where('identifier', $identifier)->first();
            if ($record && $record->is_core) {
                return true;
            }
        } catch (\Throwable) {
            // DB not available
        }

        $manifest = $this->getModuleManifest($identifier);

        return (bool) ($manifest['is_core'] ?? false);
    }

    /**
     * Assesses the impact of deactivating a module (affected menus, pages, and core risks).
     *
     * @return array{
     *     identifier: string,
     *     is_core_risk: bool,
     *     warning: ?string,
     *     affected_menus: array<int, array{menu: string, label: string, url: string}>,
     *     affected_pages: array<int, array{id: int, title: string, reason: string}>,
     *     has_impact: bool
     * }
     */
    public function getDeactivationImpact(string $identifier): array
    {
        $identifier = strtolower($identifier);
        $isCoreRisk = $this->isCore($identifier);
        $warning = $isCoreRisk
            ? "El módulo '{$identifier}' es un módulo troncal que provee el enrutamiento principal y la portada del CMS. Desactivarlo pondrá el sitio en modo Clean Install."
            : null;

        $affectedMenus = [];
        $affectedPages = [];

        // 1. Check affected navigation menu items
        try {
            if (class_exists(MenuItem::class)) {
                $patterns = match ($identifier) {
                    'blog' => ['/blog', 'blog'],
                    'shop' => ['/shop', 'shop', '/tienda', 'tienda'],
                    'gallery' => ['/galeria', 'galeria', '/gallery', 'gallery'],
                    'forms' => ['/forms', 'forms', '/contacto', 'contacto'],
                    default => ["/{$identifier}", $identifier],
                };

                $items = MenuItem::with(['menu', 'translations'])->get();
                foreach ($items as $item) {
                    $url = (string) $item->url;
                    $type = (string) $item->type;
                    $matched = false;

                    foreach ($patterns as $pattern) {
                        if (str_contains(strtolower($url), strtolower($pattern)) || strtolower($type) === strtolower($pattern)) {
                            $matched = true;
                            break;
                        }
                    }

                    if ($matched) {
                        $label = $item->translations->first()->title ?? $item->url ?? 'Enlace';
                        $menuTitle = $item->menu->name ?? 'Menú Principal';
                        $affectedMenus[] = [
                            'menu' => (string) $menuTitle,
                            'label' => (string) $label,
                            'url' => (string) $url,
                        ];
                    }
                }
            }
        } catch (\Throwable) {
            // DB not available or table missing
        }

        // 2. Check affected page sections / widgets
        try {
            if (class_exists(PageSection::class)) {
                $sectionTypes = match ($identifier) {
                    'blog' => ['widget_posts'],
                    'shop' => ['widget_products', 'products'],
                    'gallery' => ['gallery'],
                    'forms' => ['widget_form', 'form'],
                    'charts' => ['widget_stats'],
                    'beforeafter' => ['before_after'],
                    default => [],
                };

                $sections = PageSection::with(['page.translations'])
                    ->where(function ($q) use ($sectionTypes, $identifier): void {
                        if (! empty($sectionTypes)) {
                            $q->whereIn('type', $sectionTypes);
                        }
                        $q->orWhere('widget_key', 'LIKE', "{$identifier}%");
                    })
                    ->get();

                foreach ($sections as $sec) {
                    $page = $sec->page;
                    $pTitle = $page->translations->first()->title ?? "Página #{$sec->page_id}";
                    $affectedPages[] = [
                        'id' => (int) $sec->page_id,
                        'title' => (string) $pTitle,
                        'reason' => "Contiene bloque/widget: {$sec->type}".($sec->widget_key ? " ({$sec->widget_key})" : ''),
                    ];
                }
            }
        } catch (\Throwable) {
            // Pages module not installed
        }

        // Deduplicate affected pages by ID
        $uniquePages = [];
        $seenPageIds = [];
        foreach ($affectedPages as $p) {
            if (! in_array($p['id'], $seenPageIds, true)) {
                $seenPageIds[] = $p['id'];
                $uniquePages[] = $p;
            }
        }

        $hasImpact = $isCoreRisk || count($affectedMenus) > 0 || count($uniquePages) > 0;

        return [
            'identifier' => $identifier,
            'is_core' => $isCoreRisk,
            'is_core_risk' => $isCoreRisk,
            'warning' => $warning,
            'core_warning' => $warning,
            'affected_menus' => $affectedMenus,
            'affected_pages' => $uniquePages,
            'has_impact' => $hasImpact,
        ];
    }
}
