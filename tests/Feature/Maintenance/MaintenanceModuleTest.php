<?php

declare(strict_types=1);

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Core\Modules\ModuleManager;
use App\Models\Language;
use App\Models\Market;
use App\Models\Role;
use App\Models\User;
use App\Services\Geo\LlmsTxtGeneratorService;
use App\Services\Seo\SitemapService;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Modules\Blog\Models\Category as BlogCategory;
use Modules\Blog\Models\CategoryTranslation as BlogCategoryTranslation;
use Modules\Maintenance\Services\MaintenanceService;
use Modules\Pages\Models\Page;
use Modules\Pages\Models\PageTranslation;
use Modules\Shop\Models\ProductCategory as ShopCategory;
use Modules\Shop\Models\ProductCategoryTranslation as ShopCategoryTranslation;

beforeEach(function (): void {
    app(ModuleManager::class)->discoverModules();

    // Ensure clean maintenance state
    $lockFile = storage_path('framework/cms-maintenance.json');
    if (File::exists($lockFile)) {
        File::delete($lockFile);
    }
    Config::set('cms.maintenance_mode', false);
    try {
        app(ModuleSettingsServiceInterface::class)->set('maintenance', 'is_enabled', false);
    } catch (Throwable) {
    }

    // Seed market, language, and home page for frontoffice testing
    $spanish = Language::firstOrCreate(['code' => 'es'], ['name' => 'Spanish', 'native_name' => 'Español', 'is_default' => true, 'is_active' => true]);
    $marketEs = Market::firstOrCreate(['code' => 'es'], ['name' => 'España', 'default_locale' => 'es', 'currency' => 'EUR', 'is_default' => true, 'is_active' => true]);
    $marketEs->languages()->syncWithoutDetaching([$spanish->id => ['is_default' => true]]);

    $page = Page::firstOrCreate(
        ['template' => 'home'],
        ['is_published' => true, 'published_at' => now()]
    );
    if (! $page->published_at || ! $page->is_published) {
        $page->update(['is_published' => true, 'published_at' => now()]);
    }
    $page->markets()->syncWithoutDetaching([$marketEs->id]);
    PageTranslation::firstOrCreate(
        ['page_id' => $page->id, 'locale' => 'es'],
        ['slug' => 'home', 'title' => 'Inicio dbCMS', 'meta_title' => 'Inicio dbCMS', 'meta_description' => 'Portada oficial']
    );
});

afterEach(function (): void {
    $lockFile = storage_path('framework/cms-maintenance.json');
    if (File::exists($lockFile)) {
        File::delete($lockFile);
    }
    try {
        app(ModuleSettingsServiceInterface::class)->set('maintenance', 'is_enabled', false);
    } catch (Throwable) {
    }
    app()['env'] = 'testing';
    Config::set('app.env', 'testing');
    Config::set('cms.lock_roles_in_production', false);
    Config::set('cms.maintenance_mode', false);
});

test('maintenance module is discovered by ModuleManager', function (): void {
    $manager = app(ModuleManager::class);
    $discovered = $manager->discoverModules();

    expect($discovered)->toHaveKey('maintenance');
    expect($discovered['maintenance']['name'])->toBe('Mantenimiento & Construcción');
});

test('public routes return 200 when maintenance is disabled', function (): void {
    $service = app(MaintenanceService::class);
    $service->disable();

    $response = $this->get('/es/es');
    $response->assertStatus(200);
});

test('public routes return 503 with Retry-After when maintenance is enabled', function (): void {
    $service = app(MaintenanceService::class);
    $service->enable();

    $response = $this->get('/es/es');
    $response->assertStatus(503);
    $response->assertHeader('Retry-After');
    $response->assertSee('Google Sans Code');
    $response->assertSee('cursor-blink');
    $response->assertSee('canvas');
    $response->assertSee('&gt;', false);
});

test('future end_datetime calculates dynamic Retry-After header and countdown markup', function (): void {
    $service = app(MaintenanceService::class);
    $future = Carbon::now()->addHours(3);
    $service->enable($future->toDateTimeString(), 'Actualización programada');

    $response = $this->get('/es/es');
    $response->assertStatus(503);

    $retryAfter = (int) $response->headers->get('Retry-After');
    expect($retryAfter)->toBeGreaterThan(10000)->toBeLessThanOrEqual(10800);

    $response->assertSee('countdown-wrapper');
    $response->assertSee('Actualización programada');
});

test('custom_html mode renders custom markup instead of default canvas', function (): void {
    $settingsService = app(ModuleSettingsServiceInterface::class);
    $settingsService->setMany('maintenance', [
        'is_enabled' => true,
        'mode' => 'custom_html',
        'custom_html' => '<div id="custom-construction"><h1>Sitio en Obras</h1></div>',
    ]);

    $service = app(MaintenanceService::class);
    $service->enable();

    $response = $this->get('/es/es');
    $response->assertStatus(503);
    $response->assertSee('<div id="custom-construction"><h1>Sitio en Obras</h1></div>', false);
    $response->assertDontSee('Google Sans Code');
});

test('staff users with backoffice roles bypass maintenance mode on public routes', function (): void {
    $service = app(MaintenanceService::class);
    $service->enable();

    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $user = User::factory()->create();
    $user->roles()->sync([$adminRole->id]);

    $response = $this->actingAs($user, 'web')->get('/es/es');
    $response->assertStatus(200);
});

test('admin routes and health checks are always accessible during maintenance', function (): void {
    $service = app(MaintenanceService::class);
    $service->enable();

    // Login page
    $response = $this->get('/admin/login');
    $response->assertStatus(200);

    // Health check endpoint
    $response = $this->get('/up');
    $response->assertStatus(200);
});

test('cms:maintenance artisan command enables, disables, and reports status', function (): void {
    $this->artisan('cms:maintenance enable --until="2026-12-31 23:59:59"')
        ->assertExitCode(0)
        ->expectsOutputToContain('Modo Mantenimiento ACTIVADO');

    $service = app(MaintenanceService::class);
    expect($service->isActive())->toBeTrue();

    $this->artisan('cms:maintenance status')
        ->assertExitCode(0)
        ->expectsOutputToContain('ACTIVO (503)');

    $this->artisan('cms:maintenance disable')
        ->assertExitCode(0)
        ->expectsOutputToContain('DESACTIVADO');

    expect($service->isActive())->toBeFalse();
});

test('admin preview returns 200 with preview header without enabling maintenance', function (): void {
    $service = app(MaintenanceService::class);
    $service->disable();

    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $user = User::factory()->create();
    $user->roles()->sync([$adminRole->id]);

    $response = $this->actingAs($user, 'web')->get('/admin/maintenance/preview');
    $response->assertStatus(200);
    $response->assertHeader('X-CMS-Maintenance-Preview', '1');
    $response->assertSee('cursor-blink');
    $response->assertSee('canvas');
});

test('in production environment adminer and quick login are hidden and roles are locked', function (): void {
    // 1. Simulate production environment
    app()['env'] = 'production';
    Config::set('app.env', 'production');
    Config::set('cms.lock_roles_in_production', true);

    // 2. Check login page: Adminer and Demo personas are not rendered
    $response = $this->get('/admin/login');
    $response->assertStatus(200);
    $response->assertDontSee('localhost:8088');
    $response->assertDontSee('Adminer DB');
    $response->assertDontSee('Acceso Rápido de 1-Clic');
    $response->assertDontSee('secret123');

    // 3. Authenticate admin user
    $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin']);
    $editorRole = Role::firstOrCreate(['slug' => 'editor'], ['name' => 'Editor']);
    $adminUser = User::factory()->create();
    $adminUser->roles()->sync([$adminRole->id]);

    // 4. Check dashboard: Adminer card and persona switcher not rendered
    $response = $this->actingAs($adminUser, 'web')->get('/admin');
    $response->assertStatus(200);
    $response->assertDontSee('id="nav-adminer"', false);
    $response->assertDontSee('id="nav-persona-switcher"', false);

    // 5. Check users list: Role select form not rendered, locked badge with fa-lock rendered
    $targetUser = User::factory()->create();
    $targetUser->roles()->sync([$editorRole->id]);

    $response = $this->actingAs($adminUser, 'web')->get('/admin/users');
    $response->assertStatus(200);
    $response->assertDontSee('[Ambiente Demo Activo]');
    $response->assertSee('fa-lock');

    // 6. Attempt to update role via POST should be rejected in production
    $response = $this->withoutMiddleware([PreventRequestForgery::class, ValidateCsrfToken::class])
        ->actingAs($adminUser, 'web')
        ->post(route('admin.users.update-role', $targetUser), [
            'role_id' => $adminRole->id,
        ]);

    $response->assertSessionHasErrors(['error']);
    expect($targetUser->fresh()->roles->first()->id)->toBe($editorRole->id);

    // Revert environment to testing
    app()['env'] = 'testing';
    Config::set('app.env', 'testing');
    Config::set('cms.lock_roles_in_production', false);
});

test('sitemaps include blog and shop categories with hreflang alternates', function (): void {
    $market = Market::where('code', 'es')->first();
    if (! $market) {
        $market = Market::firstOrCreate(['code' => 'es'], ['name' => 'España', 'currency' => 'EUR', 'is_active' => true]);
    }

    // Create blog category
    $bCat = BlogCategory::create(['is_active' => true]);
    BlogCategoryTranslation::create([
        'category_id' => $bCat->id,
        'locale' => 'es',
        'name' => 'Ciberseguridad',
        'slug' => 'ciberseguridad-test',
        'description' => 'Noticias de seguridad',
    ]);

    // Create shop category
    $sCat = ShopCategory::create(['is_active' => true, 'order' => 1]);
    ShopCategoryTranslation::create([
        'category_id' => $sCat->id,
        'locale' => 'es',
        'name' => 'Hardware Cuántico',
        'slug' => 'hardware-cuantico-test',
        'description' => 'Servidores cuánticos',
    ]);

    $sitemapService = app(SitemapService::class);
    $xml = $sitemapService->generateSitemapXml($market, 'es');

    expect($xml)->toContain('/blog/ciberseguridad-test');
    expect($xml)->toContain('/tienda/hardware-cuantico-test');
    expect($xml)->toContain('xhtml:link');
});

test('llms.txt includes topic taxonomies and returns valid markdown', function (): void {
    $market = Market::where('code', 'es')->first();
    if (! $market) {
        $market = Market::firstOrCreate(['code' => 'es'], ['name' => 'España', 'currency' => 'EUR', 'is_active' => true]);
    }

    // Create blog category
    $bCat = BlogCategory::create(['is_active' => true]);
    BlogCategoryTranslation::create([
        'category_id' => $bCat->id,
        'locale' => 'es',
        'name' => 'IA y Algoritmos',
        'slug' => 'ia-y-algoritmos',
        'description' => 'Modelos y agentes autónomos',
    ]);

    $llmService = app(LlmsTxtGeneratorService::class);
    $txt = $llmService->generateLlmsTxt($market, 'es');

    expect($txt)->toContain('# ');
    expect($txt)->toContain('## Overview & Main Hubs');
    expect($txt)->toContain('## Topic Taxonomies & Categories');
    expect($txt)->toContain('IA y Algoritmos');
});

test('legacy admin maintenance url redirects to module settings and settings panel renders simulator', function (): void {
    $adminRole = Role::firstOrCreate(['name' => 'admin'], ['slug' => 'admin']);
    $user = User::factory()->create();
    $user->roles()->syncWithoutDetaching([$adminRole->id]);

    $this->actingAs($user)
        ->get('/admin/maintenance')
        ->assertStatus(302)
        ->assertRedirect(route('admin.modules.settings', ['identifier' => 'maintenance']));

    $response = $this->actingAs($user)
        ->get(route('admin.modules.settings', ['identifier' => 'maintenance']));

    $response->assertStatus(200);
    $response->assertSee('Mantenimiento & Construcción');
    $response->assertSee('Previsualización en Vivo');
});
