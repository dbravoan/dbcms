<?php

declare(strict_types=1);

namespace Tests\Feature\Core;

use App\Contracts\Services\AdminNavigationServiceInterface;
use App\Contracts\Services\ModuleSettingsAdministrationInterface;
use App\Core\Modules\ModuleManager;
use App\DTOs\Admin\AdminNavigationItem;
use App\DTOs\Admin\AdminNavigationSection;
use App\Http\Controllers\Admin\ModuleSettings\ModuleSettingsGetController;
use App\Http\Controllers\Admin\ModuleSettings\ModuleSettingsPutController;
use App\Models\CmsModule;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\CmsFoundationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\ModuleAdministrationComposition;

uses(RefreshDatabase::class, ModuleAdministrationComposition::class);

beforeEach(function (): void {
    $this->seed(CmsFoundationSeeder::class);

    /** @var ModuleManager $manager */
    $manager = app(ModuleManager::class);
    $manager->discoverModules();

    $role = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrator']);
    $this->adminUser = User::firstOrCreate(
        ['email' => 'admin@db-cms.io'],
        [
            'name' => 'Admin User',
            'password' => bcrypt('secret123'),
        ]
    );
    $this->adminUser->roles()->syncWithoutDetaching([$role->id]);

    Gate::before(fn () => true);
});

test('navigation service returns typed sections and items', function (): void {
    /** @var AdminNavigationServiceInterface $navService */
    $navService = app(AdminNavigationServiceInterface::class);

    $sections = $navService->getNavigationSections($this->adminUser);

    expect($sections)->not->toBeEmpty();
    expect($sections[0])->toBeInstanceOf(AdminNavigationSection::class);
    expect($sections[0]->title)->toBe('General');
    expect($sections[0]->items)->not->toBeEmpty();
    expect($sections[0]->items[0])->toBeInstanceOf(AdminNavigationItem::class);
});

test('deactivating module hides it from navigation identifiers and items', function (): void {
    /** @var ModuleManager $moduleManager */
    $moduleManager = app(ModuleManager::class);
    $moduleManager->discoverModules();

    $shopModule = CmsModule::where('identifier', 'shop')->firstOrFail();
    $moduleManager->deactivate('shop');
    $navService = app(AdminNavigationServiceInterface::class);
    expect($navService->getActiveModuleIdentifiers())->not->toContain('shop');
    $sections = $navService->getNavigationSections($this->adminUser);
    $routes = collect($sections)->flatMap(fn (AdminNavigationSection $section) => $section->items)->pluck('route')->all();
    expect($routes)->not->toContain('admin.shop.products.index');
});

test('module settings get controller is invokable and renders view', function (): void {
    $response = $this->actingAs($this->adminUser, 'web')->get(route('admin.modules.settings', ['identifier' => 'mcp']));

    $response->assertOk();
    expect(is_callable(new ModuleSettingsGetController(
        app(ModuleSettingsAdministrationInterface::class)
    )))->toBeTrue();
});

test('module settings put controller is invokable and updates settings', function (): void {
    expect(is_callable(new ModuleSettingsPutController(
        app(ModuleSettingsAdministrationInterface::class)
    )))->toBeTrue();

    $response = $this->actingAs($this->adminUser, 'web')->put(route('admin.modules.settings.update', ['identifier' => 'mcp']), [
        'is_enabled' => true,
    ]);

    $response->assertRedirect(route('admin.modules.settings', ['identifier' => 'mcp']));
    $response->assertSessionHas('success');
});

test('ensure module enabled middleware aborts when module disabled', function (): void {
    /** @var ModuleManager $moduleManager */
    $moduleManager = app(ModuleManager::class);
    $moduleManager->discoverModules();

    CmsModule::where('identifier', 'ai_assistant')->update(['is_active' => false]);
    $moduleManager->deactivate('ai_assistant');

    $response = $this->actingAs($this->adminUser, 'web')->get('/admin/ai-assistant');
    $response->assertNotFound();

    CmsModule::where('identifier', 'ai_assistant')->update(['is_active' => true]);
    $moduleManager->activate('ai_assistant');

    $responseActive = $this->actingAs($this->adminUser, 'web')->get('/admin/ai-assistant');
    $responseActive->assertOk();
});
