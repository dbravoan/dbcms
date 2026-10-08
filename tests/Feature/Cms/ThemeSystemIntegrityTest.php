<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Contracts\Services\LocalizationServiceInterface;
use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Models\Language;
use App\Models\Market;
use App\Services\Cms\ThemeManagerService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    // These parity cases explicitly include Catalan in the Spanish market.
    $catalan = Language::updateOrCreate(['code' => 'ca'], ['name' => 'Catalan', 'native_name' => 'Català', 'direction' => 'ltr', 'is_active' => true]);
    Market::where('code', 'es')->firstOrFail()->languages()->syncWithoutDetaching([$catalan->getKey() => ['is_default' => false]]);

    // Discovered dynamically: a newly added front theme is covered automatically.
    $this->frontThemes = array_keys(app(ThemeManagerService::class)->getAvailableThemes());
});

afterEach(function (): void {
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms']);
});

test('all front themes are discovered and complete', function (): void {
    $themes = app(ThemeManagerService::class)->getAvailableThemes();

    $required = [
        'theme.json',
        'layouts/app.blade.php',
        'partials/header.blade.php',
        'partials/footer.blade.php',
        'pages/show.blade.php',
        'blog/index.blade.php',
        'blog/category.blade.php',
        'blog/show.blade.php',
        'gallery/index.blade.php',
        'gallery/show.blade.php',
        'shop/index.blade.php',
        'shop/show.blade.php',
        'shop/cart.blade.php',
        'shop/checkout.blade.php',
        'shop/order_success.blade.php',
        'shop/widgets/featured_products.blade.php',
    ];

    foreach ($this->frontThemes as $id) {
        expect(array_key_exists($id, $themes))->toBeTrue("Theme {$id} is not discovered.");

        foreach ($required as $file) {
            expect(file_exists(base_path("themes/{$id}/{$file}")))
                ->toBeTrue("Theme {$id} is missing {$file}.");
        }
    }
});

test('all front themes are self-contained without cross-theme references', function (): void {
    foreach ($this->frontThemes as $id) {
        $base = base_path("themes/{$id}");
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base));

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $content = (string) file_get_contents($file->getPathname());
            preg_match_all('/themes\.(db-[a-z0-9\-]+)\./', $content, $matches);

            foreach (array_unique($matches[1]) as $referenced) {
                expect($referenced)->toBe($id, "Theme {$id} references another theme [{$referenced}] in {$file->getFilename()}.");
            }
        }
    }
});

test('every front layout exposes the required hooks', function (): void {
    foreach ($this->frontThemes as $id) {
        $layout = (string) file_get_contents(base_path("themes/{$id}/layouts/app.blade.php"));

        expect($layout)->toContain("@yield('content')")
            ->and($layout)->toContain("@stack('scripts')")
            ->and($layout)->toContain("@stack('admin_scripts')")
            ->and($layout)->toContain('components.frontoffice-admin-bar')
            ->and($layout)->toContain('themes.components.cookie-consent-modal')
            ->and($layout)->toContain('themes.components.visitor-geo-modal')
            ->and($layout)->toContain('themes.components.scroll-magic');
    }
});

test('all front themes render the home page in every locale', function (): void {
    foreach ($this->frontThemes as $theme) {
        config(['cms.theme' => $theme]);

        foreach (['es', 'en', 'ca'] as $locale) {
            $this->get("/es/{$locale}")->assertOk();
        }
    }
});

test('all front themes render every core route in every locale', function (): void {
    $routes = [
        'home-es' => '/es/es',
        'blog-es' => '/es/es/blog',
        'blog-category-es' => '/es/es/blog/tecnologia',
        'gallery-es' => '/es/es/galeria',
        'gallery-en' => '/es/en/gallery',
        'gallery-ca' => '/es/ca/galeria',
        'shop-es' => '/es/es/tienda',
        'shop-cart-es' => '/es/es/tienda/cart',
    ];

    $themes = $this->frontThemes;
    expect($themes)->not->toBeEmpty();

    foreach ($themes as $theme) {
        config(['cms.theme' => $theme]);

        foreach (['es', 'en', 'ca'] as $locale) {
            $routes["blog-{$locale}"] = "/es/{$locale}/blog";
        }

        foreach ($routes as $label => $path) {
            $response = $this->get($path);
            expect($response->getStatusCode())
                ->toBeLessThan(400, "Theme [{$theme}] route [{$label}] ({$path}) failed with {$response->getStatusCode()}.");
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
        $this->get('/eu/es/galeria/recorrido-plataforma')->assertOk();
        $this->get('/eu/en/gallery/platform-tour')->assertOk();
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
    $id = 'tmpgen'.substr(md5(uniqid('', true)), 0, 6);
    $path = base_path("themes/{$id}");

    try {
        $this->artisan('make:front-theme', ['id' => $id])->assertSuccessful();

        expect(file_exists("{$path}/layouts/app.blade.php"))->toBeTrue()
            ->and(file_exists("{$path}/theme.json"))->toBeTrue();

        config(['cms.theme' => $id]);
        $this->get('/es/es')->assertOk();
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
