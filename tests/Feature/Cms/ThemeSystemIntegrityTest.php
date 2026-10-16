<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\Market;
use App\Services\Cms\ThemeManagerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Modules\Gallery\Models\Gallery;
use Tests\Support\CmsFixtures;
use Tests\Support\PublicContentComposition;

uses(RefreshDatabase::class, PublicContentComposition::class);

beforeEach(function (): void {
    config(['cms.page_cache.enabled' => false]);
    CmsFixtures::localization(['es', 'en', 'fr', 'ca'], ['es', 'eu']);
    CmsFixtures::home(['es', 'en', 'fr', 'ca'], ['es', 'eu']);
    $gallery = Gallery::create(['title' => 'Gallery fixture', 'slug' => 'gallery-fixture', 'is_active' => true]);
    foreach (['es', 'en', 'fr', 'ca'] as $locale) {
        $gallery->translations()->create(['locale' => $locale, 'title' => 'Gallery fixture', 'slug' => 'gallery-fixture']);
    }
    $gallery->markets()->sync(Market::pluck('id')->all());

    // Discovered dynamically: a newly added front theme is covered automatically.
    $this->frontThemes = array_keys(app(ThemeManagerService::class)->getAvailableThemes());
});

afterEach(function (): void {
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms']);
});

test('all front themes render the home page in every locale', function (): void {
    foreach ($this->frontThemes as $theme) {
        config(['cms.theme' => $theme]);

        foreach (['es', 'en', 'ca'] as $locale) {
            $this->get("/es/{$locale}")->assertOk()->assertSee('front-'.$theme, false);
        }
    }
});

test('gallery renders across all themes and locales', function (): void {
    foreach ($this->frontThemes as $theme) {
        config(['cms.theme' => $theme]);

        $this->get('/eu/es/galeria')->assertOk();
        $this->get('/eu/en/gallery')->assertOk();
        $this->get('/eu/fr/galerie')->assertOk();

        // Localized item slug resolution (per locale, like blog/shop).
        $this->get('/eu/es/galeria/gallery-fixture')->assertOk();
        $this->get('/eu/en/gallery/gallery-fixture')->assertOk();
    }
});

test('gallery exposes localized slug settings like blog and shop', function (): void {
    /** @var ModuleSettingsServiceInterface $settings */
    $settings = app(ModuleSettingsServiceInterface::class);

    // The schema expands `slug_{locale}` for every active language.
    expect($settings->getSchema('gallery'))
        ->toHaveKeys(['slug_default', 'slug_es', 'slug_en', 'slug_fr']);

    $settings->set('gallery', 'slug_es', 'obras');

    $slugs = app(LocalizationServiceInterface::class)->getAllModuleSlugs('gallery');
    expect($slugs)->toContain('obras');
});

test('backoffice themes expose manifests and are discoverable', function (): void {
    /** @var AdminThemeServiceInterface $themes */
    $themes = app(AdminThemeServiceInterface::class);

    $available = $themes->getAvailableThemes();
    expect($available)->toHaveKeys(['dbcms', 'termocms']);

    foreach (['dbcms', 'termocms'] as $id) {
        $manifest = $themes->getThemeManifest($id);
        expect($manifest['name'])->not->toBeEmpty()
            ->and($manifest['identifier'])->toBe($id);
    }
});

test('the theme asset route serves assets with the correct mime and blocks unknown themes', function (): void {
    $this->get('/theme-assets/termocms/termocms.css')
        ->assertOk()
        ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

    $this->get('/theme-assets/unknown-theme/whatever.css')->assertNotFound();
    $this->get('/theme-assets/termocms/does-not-exist.css')->assertNotFound();
});

test('the front html carries the active backoffice theme class', function (): void {
    // A separate test case per theme: the backoffice theme class is resolved when
    // the application boots, exactly like a real request.
    config(['cms.admin_theme' => 'termocms']);
    $this->get('/es/es')->assertOk()->assertSee('theme-termocms', false);
});

test('the front html carries theme-dbcms when that is the active backoffice theme', function (): void {
    config(['cms.admin_theme' => 'dbcms']);
    $this->get('/es/es')->assertOk()->assertSee('theme-dbcms', false);
});

test('make:front-theme generates a renderable theme', function (): void {
    // Newly generated source files are compiled by the developer's next Vite build.
    $this->withoutVite();
    $id = 'tmpgen'.substr(md5(uniqid('', true)), 0, 6);
    $path = base_path("themes/{$id}");

    try {
        $this->artisan('make:front-theme', ['id' => $id])->assertSuccessful();

        expect(file_exists("{$path}/layouts/app.blade.php"))->toBeTrue()
            ->and(file_exists("{$path}/theme.css"))->toBeTrue()
            ->and(file_exists("{$path}/theme.json"))->toBeTrue();

        config(['cms.theme' => $id]);
        $this->get('/es/es')->assertOk()->assertSee('front-'.$id, false);
    } finally {
        File::deleteDirectory($path);
    }
});

test('make:admin-theme generates a discoverable theme', function (): void {
    $id = 'tmpgen'.substr(md5(uniqid('', true)), 0, 6);
    $path = resource_path("views/themes/admin/{$id}");

    try {
        $this->artisan('make:admin-theme', ['id' => $id])->assertSuccessful();

        /** @var AdminThemeServiceInterface $themes */
        $themes = app(AdminThemeServiceInterface::class);

        expect($themes->themeExists($id))->toBeTrue()
            ->and($themes->getThemeManifest($id)['name'])->not->toBeEmpty();
    } finally {
        File::deleteDirectory($path);
    }
});
