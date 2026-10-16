<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Tests\Support\CmsFixtures;
use Tests\Support\EditorialUiComposition;

uses(RefreshDatabase::class, EditorialUiComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

test('admin theme service discovers registered backoffice themes', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);

    $themes = $themeService->getAvailableThemes();

    expect($themes)->toHaveKeys(['dbcms', 'termocms'])
        ->and($themeService->themeExists('dbcms'))->toBeTrue()
        ->and($themeService->themeExists('termocms'))->toBeTrue()
        ->and($themeService->themeExists('non_existent_theme'))->toBeFalse();
});

test('admin theme service correctly resolves theme filesystem paths', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);

    $dbcmsPath = $themeService->getThemePath('dbcms');
    $termocmsPath = $themeService->getThemePath('termocms');

    expect($dbcmsPath)->toBe(resource_path('views/themes/admin/dbcms'))
        ->and($termocmsPath)->toBe(resource_path('views/themes/admin/termocms'))
        ->and(File::isDirectory($dbcmsPath))->toBeTrue()
        ->and(File::isDirectory($termocmsPath))->toBeTrue();
});

test('active theme can be switched dynamically and updates view finder priority', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);

    $themeService->setActiveTheme('dbcms');
    expect($themeService->getActiveTheme())->toBe('dbcms');

    $finder = View::getFinder();
    $paths = $finder->getPaths();
    expect($paths[0])->toContain('themes/admin/dbcms');

    $themeService->setActiveTheme('termocms');
    expect($themeService->getActiveTheme())->toBe('termocms');

    $pathsAfterSwitch = View::getFinder()->getPaths();
    expect($pathsAfterSwitch[0])->toContain('themes/admin/termocms');
});

test('dbcms active theme resolves pure cyberpunk isolated views', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);
    $themeService->setActiveTheme('dbcms');

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertStatus(200);
    $response->assertSee('theme-dbcms');
    $response->assertSee('Google Sans Code');
    $response->assertSee('#1A1A1A');
    $response->assertSee('CORE');
    $response->assertDontSee('theme-termocms');
    $response->assertDontSee('Plus Jakarta Sans');
});

test('termocms active theme resolves pure corporate light isolated views', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);
    $themeService->setActiveTheme('termocms');

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertStatus(200);
    $response->assertSee('theme-termocms');
    $response->assertSee('Plus Jakarta Sans');
    $response->assertSee('bg-slate-50');
    $response->assertSee('#0090A8');
    $response->assertSee('CORPORATE');
    $response->assertDontSee('Google Sans Code');
    $response->assertDontSee('theme-dbcms');
});

test('non-overridden views cascade to base view directory while inheriting active theme shell', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);
    $themeService->setActiveTheme('termocms');

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    // admin.settings.index is NOT defined under themes/admin/termocms/, so it cascades to resources/views/admin/settings/index.blade.php
    $response = $this->actingAs($admin)->get(route('admin.settings.index'));

    $response->assertStatus(200);
    // Inherited theme shell properties
    $response->assertSee('theme-termocms');
    $response->assertSee('termoCMS');
    $response->assertSee('CORPORATE');
});

test('open-closed principle: custom brandable theme can be added dynamically', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);

    $customThemeDir = resource_path('views/themes/admin/novacms/admin');
    File::makeDirectory($customThemeDir, 0755, true, true);

    try {
        File::put($customThemeDir.'/layout.blade.php', <<<'BLADE'
<!DOCTYPE html>
<html lang="es" class="theme-novacms bg-indigo-950 text-indigo-100">
<head><title>Nova Backoffice</title></head>
<body>
    <div id="nova-shell">
        <span class="badge-nova">NOVA_CUSTOM_BRAND</span>
        @yield('content')
    </div>
</body>
</html>
BLADE
        );

        $themeService->setActiveTheme('novacms');
        expect($themeService->getActiveTheme())->toBe('novacms')
            ->and($themeService->themeExists('novacms'))->toBeTrue();

        $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('theme-novacms');
    } finally {
        File::deleteDirectory(resource_path('views/themes/admin/novacms'));
        $themeService->setActiveTheme('termocms');
    }
});

test('module view cascading resolves active theme override when present, falling back to base module view', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);
    $themeService->setActiveTheme('termocms');

    $mockModuleDir = storage_path('framework/testing/modules/test_mod/views');
    $themeOverrideDir = resource_path('views/themes/admin/termocms/modules/test_mod');

    File::makeDirectory($mockModuleDir, 0755, true, true);
    File::makeDirectory($themeOverrideDir, 0755, true, true);

    try {
        File::put($mockModuleDir.'/view1.blade.php', '<div>BASE_MODULE_VIEW</div>');
        File::put($themeOverrideDir.'/view1.blade.php', '<div>THEME_OVERRIDE_VIEW</div>');
        File::put($mockModuleDir.'/view2.blade.php', '<div>BASE_ONLY_VIEW</div>');

        View::addNamespace('test_mod', $mockModuleDir);
        $themeService->registerModuleThemePaths('test_mod', $mockModuleDir);

        expect(view('test_mod::view1')->render())->toBe('<div>THEME_OVERRIDE_VIEW</div>')
            ->and(view('test_mod::view2')->render())->toBe('<div>BASE_ONLY_VIEW</div>');
    } finally {
        File::deleteDirectory(storage_path('framework/testing/modules/test_mod'));
        File::deleteDirectory($themeOverrideDir);
    }
});

test('termocms renders official termosalud favicon, imagotipo, and logotipo assets', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);
    $themeService->setActiveTheme('termocms');

    $loginResponse = $this->get(route('admin.login'));
    $loginResponse->assertStatus(200);
    $loginResponse->assertSee('theme-assets/termocms/termosalud-favicon.png');
    $loginResponse->assertSee('theme-assets/termocms/termosalud-logo.png');

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
    $dashboardResponse->assertStatus(200);
    $dashboardResponse->assertSee('theme-assets/termocms/termosalud-favicon.png');
    $dashboardResponse->assertSee('theme-assets/termocms/termosalud-logo.png');
});

test('brand edition badge is data-driven from the theme manifest and optional', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);

    // termocms declares its edition in theme.json; dbcms declares none.
    expect($themeService->getThemeManifest('termocms')['brand']['edition'])->toBe('Corporate')
        ->and($themeService->getThemeManifest('dbcms')['brand']['edition'])->toBeNull();

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    // termocms active -> the badge renders.
    $themeService->setActiveTheme('termocms');
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('CORPORATE');

    // The badge is owned by the manifest: dbcms declares no edition -> no badge.
    $themeService->setActiveTheme('dbcms');
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertDontSee('CORPORATE');
});

test('termocms loads termocms.css design system across core views and module views', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);
    $themeService->setActiveTheme('termocms');

    $admin = User::where('email', 'admin@db-cms.io')->firstOrFail();

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));
    $response->assertStatus(200);
    $response->assertSee('theme-assets/termocms/termocms.css');

    $pagesResponse = $this->actingAs($admin)->get(route('admin.pages.index'));
    $pagesResponse->assertStatus(200);
    $pagesResponse->assertSee('theme-assets/termocms/termocms.css');
    $pagesResponse->assertDontSee('Google Sans Code');

    $aiResponse = $this->actingAs($admin)->get(route('admin.ai_assistant.workbench'));
    $aiResponse->assertStatus(200);
    $aiResponse->assertSee('theme-assets/termocms/termocms.css');
    $aiResponse->assertDontSee('Google Sans Code');

    $auditResponse = $this->actingAs($admin)->get(route('admin.audit.index'));
    $auditResponse->assertStatus(200);
    $auditResponse->assertSee('theme-assets/termocms/termocms.css');
    $auditResponse->assertDontSee('Google Sans Code');
    $auditResponse->assertDontSee('Departamento de Calidad & Auditoría | >_dbCMS');
});

test('status-badge and chip components render unified styling for dbcms and termocms', function (): void {
    /** @var AdminThemeServiceInterface $themeService */
    $themeService = app(AdminThemeServiceInterface::class);

    // Test dbcms styling
    $themeService->setActiveTheme('dbcms');

    $dbcmsBadge = Blade::render('<x-admin.status-badge :active="true" activeText="Publicada" />');
    expect($dbcmsBadge)->toContain('bg-[#00FF99]/10')
        ->toContain('text-[#00FF99]')
        ->toContain('font-mono')
        ->toContain('Publicada');

    $dbcmsChip = Blade::render('<x-admin.chip>5 secciones</x-admin.chip>');
    expect($dbcmsChip)->toContain('bg-gray-800')
        ->toContain('text-gray-300')
        ->toContain('5 secciones');

    $dbcmsMarketChip = Blade::render('<x-admin.chip variant="market">ES</x-admin.chip>');
    expect($dbcmsMarketChip)->toContain('bg-black/60')
        ->toContain('text-[#00FF99]')
        ->toContain('ES');

    $themeService->setActiveTheme('termocms');

    // The corporate theme keeps the same semantic component classes.
    $badge = Blade::render('<x-admin.status-badge :active="true" activeText="Publicada" />');
    expect($badge)->toContain('admin-badge')
        ->toContain('admin-badge-positive')
        ->toContain('Publicada');

    $chip = Blade::render('<x-admin.chip>5 secciones</x-admin.chip>');
    expect($chip)->toContain('admin-chip')
        ->toContain('admin-chip-default')
        ->toContain('5 secciones');

    $marketChip = Blade::render('<x-admin.chip variant="market">ES</x-admin.chip>');
    expect($marketChip)->toContain('admin-chip')
        ->toContain('admin-chip-market')
        ->toContain('ES');

});
