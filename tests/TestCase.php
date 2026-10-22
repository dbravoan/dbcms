<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Modules\ModuleManager;
use App\Http\Middleware\EnsureCmsIsInstalled;
use App\Http\Middleware\RedirectIfInstalled;
use App\Models\CmsModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BootstrapCacheIsolation;
use Throwable;

abstract class TestCase extends BaseTestCase
{
    /** @var list<string>|null */
    private static ?array $migratedModules = null;

    private ?BootstrapCacheIsolation $bootstrapCaches = null;

    public function createApplication()
    {
        $this->bootstrapCaches ??= new BootstrapCacheIsolation;
        // Select providers before Laravel boots and registers the core catch-all
        // routes. Retain the framework's cached-state and trait handling.
        $modules = defined(static::class.'::TEST_MODULES') ? static::TEST_MODULES : [];
        $previous = [getenv('CMS_MODULES'), $_ENV['CMS_MODULES'] ?? null, $_SERVER['CMS_MODULES'] ?? null];
        $selection = implode(',', $modules);
        putenv('CMS_MODULES='.$selection);
        $_ENV['CMS_MODULES'] = $_SERVER['CMS_MODULES'] = $selection;
        try {
            return parent::createApplication();
        } catch (Throwable $exception) {
            $this->bootstrapCaches->restore();
            $this->bootstrapCaches = null;
            throw $exception;
        } finally {
            putenv($previous[0] === false ? 'CMS_MODULES' : 'CMS_MODULES='.$previous[0]);
            if ($previous[1] === null) {
                unset($_ENV['CMS_MODULES']);
            } else {
                $_ENV['CMS_MODULES'] = $previous[1];
            }
            if ($previous[2] === null) {
                unset($_SERVER['CMS_MODULES']);
            } else {
                $_SERVER['CMS_MODULES'] = $previous[2];
            }
        }
    }

    protected function tearDown(): void
    {
        try {
            parent::tearDown();
        } finally {
            // Laravel resolves these environment paths dynamically, including
            // during application-destroyed callbacks and explicit cache commands.
            $this->bootstrapCaches?->restore();
            $this->bootstrapCaches = null;
        }
    }

    protected function setUpTraits()
    {
        $this->withoutMiddleware([EnsureCmsIsInstalled::class, RedirectIfInstalled::class]);
        Storage::fake('uploads');
        // A composition trait or PHPUnit class declares exactly its dependencies.
        // Providers must register migration paths before RefreshDatabase starts
        // its transaction; installing a package inside it would commit MySQL DDL.
        $modules = defined(static::class.'::TEST_MODULES') ? static::TEST_MODULES : [];
        if (in_array(RefreshDatabase::class, class_uses_recursive(static::class), true)) {
            if (self::$migratedModules !== $modules) {
                RefreshDatabaseState::$migrated = false;
            }
            self::$migratedModules = $modules;
        }

        $traits = parent::setUpTraits();
        if ($modules !== []) {
            app(ModuleManager::class)->reconcileModules($modules);
            CmsModule::query()->whereIn('identifier', $modules)->update(['is_installed' => true, 'is_active' => true]);
        }

        return $traits;
    }
}
