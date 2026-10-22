<?php

declare(strict_types=1);

namespace Tests\Regression;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Blocks\BlockPackageManager;
use App\Core\Modules\ModuleManager;
use App\Events\ModuleAvailabilityChanged;
use App\Models\CmsModule;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\BlogComposition;
use Tests\TestCase;

final class ModuleAvailabilityCommitTest extends TestCase
{
    use BlogComposition, DatabaseMigrations;

    public function test_availability_notifications_follow_real_commit_and_rollback(): void
    {
        $manager = app(ModuleManager::class);
        Event::fake([ModuleAvailabilityChanged::class]);
        DB::transaction(function () use ($manager): void {
            $manager->deactivate('blog');
            Event::assertNotDispatched(ModuleAvailabilityChanged::class);
        });
        Event::assertDispatched(ModuleAvailabilityChanged::class, static fn (ModuleAvailabilityChanged $event): bool => $event->module === 'blog' && ! $event->available && $event->operation === 'deactivate');

        Event::fake([ModuleAvailabilityChanged::class]);
        try {
            DB::transaction(function () use ($manager): void {
                $manager->activate('blog');
                throw new RuntimeException('rollback');
            });
            self::fail('The failed transaction must propagate.');
        } catch (RuntimeException $exception) {
            self::assertSame('rollback', $exception->getMessage());
        }
        Event::assertNotDispatched(ModuleAvailabilityChanged::class);
        self::assertFalse($manager->isModuleEnabled('blog'));
    }

    public function test_uninstall_preserves_the_record_and_emits_unavailability_after_commit(): void
    {
        $manager = app(ModuleManager::class);
        Event::fake([ModuleAvailabilityChanged::class]);
        DB::transaction(function () use ($manager): void {
            $manager->uninstall('blog');
            Event::assertNotDispatched(ModuleAvailabilityChanged::class);
        });

        self::assertTrue(CmsModule::where('identifier', 'blog')->exists());
        self::assertFalse($manager->isModuleEnabled('blog'));
        Event::assertDispatched(ModuleAvailabilityChanged::class, static fn (ModuleAvailabilityChanged $event): bool => $event->module === 'blog' && $event->operation === 'uninstall' && ! $event->available);
    }

    #[DataProvider('extensionKinds')]
    public function test_runtime_invalidation_only_runs_after_a_committed_extension_change(string $kind): void
    {
        $routeCache = sys_get_temp_dir().'/dbcms-route-cache-'.bin2hex(random_bytes(8)).'.php';
        $previousRouteCache = [getenv('APP_ROUTES_CACHE'), $_ENV['APP_ROUTES_CACHE'] ?? null, $_SERVER['APP_ROUTES_CACHE'] ?? null];
        putenv('APP_ROUTES_CACHE='.$routeCache);
        $_ENV['APP_ROUTES_CACHE'] = $_SERVER['APP_ROUTES_CACHE'] = $routeCache;
        file_put_contents($routeCache, '<?php return [];');

        try {
            Cache::forget('illuminate:queue:restart');
            $disable = static function () use ($kind): void {
                if ($kind === 'module') {
                    app(ModuleManager::class)->deactivate('blog');
                } elseif ($kind === 'settings') {
                    app(ModuleSettingsServiceInterface::class)->set('blog', 'comments_enabled', false);
                } else {
                    app(BlockPackageManager::class)->setEnabled('basic', false);
                }
            };
            try {
                DB::transaction(function () use ($disable, $routeCache): void {
                    $disable();
                    self::assertNull(Cache::get('illuminate:queue:restart'));
                    self::assertFileExists($routeCache);
                    throw new RuntimeException('rollback');
                });
            } catch (RuntimeException $error) {
                self::assertSame('rollback', $error->getMessage());
            }
            self::assertNull(Cache::get('illuminate:queue:restart'));
            self::assertFileExists($routeCache);
            if ($kind === 'settings') {
                self::assertTrue(app(ModuleSettingsServiceInterface::class)->get('blog', 'comments_enabled'));
            }
            DB::transaction(function () use ($disable, $routeCache): void {
                $disable();
                self::assertNull(Cache::get('illuminate:queue:restart'));
                self::assertFileExists($routeCache);
            });
            self::assertIsInt(Cache::get('illuminate:queue:restart'));
            if ($kind === 'module') {
                self::assertFileDoesNotExist($routeCache);
            } else {
                self::assertFileExists($routeCache);
            }
            if ($kind === 'settings') {
                self::assertFalse(app(ModuleSettingsServiceInterface::class)->get('blog', 'comments_enabled'));
                self::assertFalse(config('modules.blog.comments_enabled'));
            }
        } finally {
            if (is_file($routeCache)) {
                unlink($routeCache);
            }
            putenv($previousRouteCache[0] === false ? 'APP_ROUTES_CACHE' : 'APP_ROUTES_CACHE='.$previousRouteCache[0]);
            foreach (['_ENV', '_SERVER'] as $index => $variable) {
                if ($previousRouteCache[$index + 1] === null) {
                    unset($GLOBALS[$variable]['APP_ROUTES_CACHE']);
                } else {
                    $GLOBALS[$variable]['APP_ROUTES_CACHE'] = $previousRouteCache[$index + 1];
                }
            }
        }
    }

    /** @return array<string,array{string}> */
    public static function extensionKinds(): array
    {
        return ['module' => ['module'], 'block package' => ['block-package'], 'settings' => ['settings']];
    }
}
