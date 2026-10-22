<?php

declare(strict_types=1);

namespace Tests\Smoke;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ModuleLifecycleSmokeTest extends TestCase
{
    use DatabaseMigrations;

    public function test_catalog_is_read_only_and_an_explicit_extension_can_be_installed_and_disabled(): void
    {
        require_once base_path('tests/Fixtures/Modules/LifecycleProbe/LifecycleProbeModuleServiceProvider.php');
        config(['cms.modules' => [], 'cms.module_packages.root' => base_path('tests/Fixtures/Modules')]);
        $manager = app(ModuleManager::class);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::create(['slug' => 'admin', 'name' => 'Admin']));

        self::assertSame(0, CmsModule::query()->count());
        DB::enableQueryLog();
        self::assertArrayHasKey('lifecycle-probe', $manager->discoverModules());
        $this->actingAs($admin)->get('/admin/modules')->assertOk()->assertSee('Lifecycle Probe');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        self::assertSame([], array_values(array_filter($queries, static fn (array $query): bool => preg_match('/^\s*(insert|update|delete)\b.*cms_modules/i', $query['query']) === 1)));
        self::assertSame(0, CmsModule::query()->count());
        self::assertFalse(Schema::hasTable('lifecycle_probe_entries'));

        $this->from('/admin/modules')->post('/admin/modules/lifecycle-probe/install')->assertRedirect('/admin/modules')->assertSessionHasNoErrors();
        self::assertTrue($manager->isModuleEnabled('lifecycle-probe'));
        self::assertTrue(Schema::hasTable('lifecycle_probe_entries'));
        DB::table('lifecycle_probe_entries')->insert(['value' => 'Preserved data']);
        $this->get('/admin/module-lifecycle-probe')->assertOk()->assertSee('Extension available');
        self::assertSame(['lifecycle-probe'], CmsModule::query()->pluck('identifier')->all());

        $this->post('/admin/modules/lifecycle-probe/toggle')->assertRedirect();
        self::assertFalse($manager->isModuleEnabled('lifecycle-probe'));
        self::assertTrue(Schema::hasTable('lifecycle_probe_entries'));
        $this->get('/admin/module-lifecycle-probe')->assertNotFound();
        $this->post('/admin/modules/lifecycle-probe/toggle')->assertRedirect();
        self::assertTrue($manager->isModuleEnabled('lifecycle-probe'));

        $this->post('/admin/modules/lifecycle-probe/uninstall')->assertRedirect();
        self::assertFalse($manager->isModuleEnabled('lifecycle-probe'));
        self::assertTrue(Schema::hasTable('lifecycle_probe_entries'));
        self::assertFalse($manager->activate('lifecycle-probe'));
        self::assertSame('Preserved data', DB::table('lifecycle_probe_entries')->value('value'));
    }

    public function test_missing_module_directory_stays_absent_on_discovery(): void
    {
        $path = storage_path('framework/testing-absent-modules-'.bin2hex(random_bytes(6)));
        config(['cms.module_packages.root' => $path]);
        self::assertSame([], app(ModuleManager::class)->discoverModules());
        self::assertDirectoryDoesNotExist($path);
        self::assertSame(0, CmsModule::query()->count());
    }

    public function test_invalid_provider_cannot_create_or_activate_installation_state(): void
    {
        $path = storage_path('framework/testing-invalid-provider-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($path.'/InvalidProvider');
        File::put($path.'/InvalidProvider/module.json', json_encode([
            'identifier' => 'invalid-provider', 'name' => 'Invalid provider', 'version' => '1.0.0',
            'provider' => 'Modules\\InvalidProvider\\InvalidProviderModuleServiceProvider',
        ], JSON_THROW_ON_ERROR));
        $provider = $path.'/InvalidProvider/InvalidProviderModuleServiceProvider.php';
        File::put($provider, '<?php namespace Modules\\InvalidProvider; final class InvalidProviderModuleServiceProvider {}');
        require_once $provider;
        config(['cms.module_packages.root' => $path]);
        try {
            app(ModuleManager::class)->install('invalid-provider');
            self::fail('Invalid providers must be rejected before changing installation state.');
        } catch (DomainException $error) {
            self::assertStringContainsString('no valid service provider', $error->getMessage());
            self::assertFalse(CmsModule::where('identifier', 'invalid-provider')->exists());
        } finally {
            File::deleteDirectory($path);
        }
    }

    public function test_active_dependents_prevent_deactivation_and_uninstallation(): void
    {
        $path = storage_path('framework/testing-module-graph-'.bin2hex(random_bytes(6)));
        config(['cms.module_packages.root' => $path]);
        try {
            foreach (['foundation' => [], 'consumer' => ['foundation' => '^1.0']] as $identifier => $requires) {
                $folder = ucfirst($identifier);
                $manifest = ['identifier' => $identifier, 'name' => $folder, 'version' => '1.0.0', 'provider' => "Modules\\{$folder}\\{$folder}ModuleServiceProvider", 'requires' => $requires, 'availability_setting' => 'enabled', 'settings' => ['enabled' => ['type' => 'boolean', 'default' => true]]];
                File::ensureDirectoryExists($path.'/'.$folder);
                File::put($path.'/'.$folder.'/module.json', json_encode($manifest, JSON_THROW_ON_ERROR));
                CmsModule::create(array_diff_key($manifest, ['requires' => true, 'settings' => true, 'availability_setting' => true]) + ['is_active' => true, 'is_installed' => true]);
            }
            $manager = app(ModuleManager::class);
            foreach (['deactivate', 'uninstall'] as $operation) {
                try {
                    $manager->{$operation}('foundation');
                    self::fail('Active dependents must prevent availability changes.');
                } catch (DomainException $error) {
                    self::assertStringContainsString('consumer', $error->getMessage());
                }
                self::assertTrue($manager->isModuleEnabled('foundation'));
            }
            $settings = app(ModuleSettingsServiceInterface::class);
            try {
                $settings->setMany('foundation', ['enabled' => false]);
                self::fail('Availability settings must not bypass active dependents.');
            } catch (DomainException $error) {
                self::assertStringContainsString('consumer', $error->getMessage());
            }
            $manager->deactivate('consumer');
            $manager->deactivate('foundation');
            self::assertFalse($manager->isModuleEnabled('foundation'));
            try {
                $manager->activate('consumer');
                self::fail('Inactive requirements must prevent activation.');
            } catch (DomainException $error) {
                self::assertStringContainsString('requires selected module [foundation]', $error->getMessage());
            }
            self::assertFalse($manager->isModuleEnabled('consumer'));
            try {
                $manager->uninstall('foundation');
                self::fail('Installed dependents must prevent uninstall even when inactive.');
            } catch (DomainException $error) {
                self::assertStringContainsString('consumer', $error->getMessage());
            }
            self::assertTrue((bool) CmsModule::where('identifier', 'foundation')->value('is_installed'));
            self::assertTrue($manager->uninstall('consumer'));
            self::assertTrue($manager->uninstall('foundation'));
        } finally {
            File::deleteDirectory($path);
        }
    }

    #[DataProvider('failingProviderPhases')]
    public function test_provider_failure_never_commits_installed_state(string $phase): void
    {
        $folder = ucfirst($phase).'Failure';
        $identifier = strtolower($folder);
        $path = storage_path('framework/testing-provider-failure-'.bin2hex(random_bytes(6)));
        File::ensureDirectoryExists($path.'/'.$folder);
        $class = "Modules\\{$folder}\\{$folder}ModuleServiceProvider";
        File::put($path.'/'.$folder.'/module.json', json_encode(['identifier' => $identifier, 'name' => $folder, 'version' => '1.0.0', 'provider' => $class], JSON_THROW_ON_ERROR));
        $provider = $path.'/'.$folder.'/'.$folder.'ModuleServiceProvider.php';
        File::put($provider, '<?php namespace Modules\\'.$folder.'; final class '.$folder.'ModuleServiceProvider extends \\App\\Core\\Modules\\BaseModuleServiceProvider { public function '.$phase.'(): void { throw new \\RuntimeException("Provider '.$phase.' failed"); } }');
        require_once $provider;
        config(['cms.modules' => [], 'cms.module_packages.root' => $path]);
        try {
            try {
                app(ModuleManager::class)->install($identifier);
                self::fail('A failing provider must abort installation.');
            } catch (\RuntimeException $error) {
                self::assertSame('Provider '.$phase.' failed', $error->getMessage());
            }
            self::assertFalse((bool) CmsModule::where('identifier', $identifier)->value('is_installed'));
            self::assertFalse((bool) CmsModule::where('identifier', $identifier)->value('is_active'));
            $this->refreshApplication();
            self::assertSame([], app(ModuleManager::class)->getActiveModules());
        } finally {
            File::deleteDirectory($path);
        }
    }

    /** @return array<string, array{string}> */
    public static function failingProviderPhases(): array
    {
        return ['registration' => ['register'], 'boot' => ['boot']];
    }
}
