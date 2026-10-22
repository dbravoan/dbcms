<?php

declare(strict_types=1);

use App\Services\Cms\ThemeManagerService;

beforeEach(function (): void {
    $this->frontThemes = array_keys(app(ThemeManagerService::class)->getAvailableThemes());
});

test('all front themes are discovered and complete', function (): void {
    $themes = app(ThemeManagerService::class)->getAvailableThemes();

    $required = [
        'theme.json',
        'theme.css',
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
        'shop/blocks/products.blade.php',
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
            preg_match_all('/themes::(db-[a-z0-9\-]+)\./', $content, $matches);

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
            ->and($layout)->toContain('themes::components.cookie-consent-modal')
            ->and($layout)->toContain('themes::components.visitor-geo-modal')
            ->and($layout)->toContain('themes::components.scroll-magic');
    }
});
