<?php

declare(strict_types=1);
use Database\Seeders\PlatformShowcaseSeeder;

/**
 * Front-office performance guardrails.
 *
 * These tests fail the build when a regression reintroduces a blocking CDN, an
 * un-bundled asset pipeline or an oversized document. They encode the performance
 * budget agreed for the public site.
 */
beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

it('never loads a blocking third-party CDN in any front-office theme', function (): void {
    $layouts = glob(base_path('themes/*/layouts/app.blade.php')) ?: [];

    expect($layouts)->not->toBeEmpty();

    $forbidden = [
        'cdn.tailwindcss.com',
        'cdn.jsdelivr.net',
        'cdnjs.cloudflare.com',
        'fonts.googleapis.com',
        'fonts.gstatic.com',
        'unpkg.com',
    ];

    foreach ($layouts as $layout) {
        $contents = (string) file_get_contents($layout);

        foreach ($forbidden as $needle) {
            expect(str_contains($contents, $needle))
                ->toBeFalse(basename(dirname(dirname($layout)))." still loads {$needle}");
        }

        // Every front layout must go through the Vite bundle.
        expect($contents)->toContain('@vite(');
    }
});

it('never loads a blocking third-party CDN in the backoffice either', function (): void {
    $views = array_merge(
        glob(resource_path('views/themes/admin/*/admin/layout.blade.php')) ?: [],
        glob(resource_path('views/themes/admin/*/errors/layout.blade.php')) ?: [],
        [resource_path('views/admin/layout.blade.php')],
    );

    foreach ($views as $view) {
        if (! is_file($view)) {
            continue;
        }

        $contents = (string) file_get_contents($view);

        expect(str_contains($contents, 'cdn.tailwindcss.com'))->toBeFalse("{$view} loads the Tailwind CDN");
        expect(str_contains($contents, 'cdnjs.cloudflare.com'))->toBeFalse("{$view} loads a cdnjs asset");
    }
});

it('optimises every front-office image with loading and decoding hints', function (): void {
    $offenders = [];

    foreach (glob(base_path('themes/*/**/*.blade.php')) ?: [] as $view) {
        $contents = (string) file_get_contents($view);

        // Blade-aware scan: `->` inside {{ ... }} must not end the tag.
        preg_match_all('/<img\b(?:(?!\{\{|\?>)[^>])*(?:\{\{.*?\}\}(?:(?!\{\{|\?>)[^>])*)*>/s', $contents, $matches);

        foreach ($matches[0] as $tag) {
            if (! str_contains($tag, 'loading=')) {
                $offenders[] = str_replace(base_path().'/', '', $view);
            }
        }
    }

    $offenders = array_values(array_unique($offenders));

    expect($offenders)->toBeEmpty("Views with images missing loading=\"lazy|eager\":\n".implode("\n", array_slice($offenders, 0, 10)));
});

it('keeps the public HTML within the performance budget', function (): void {
    config(['cms.theme' => 'db-cyber']);

    $response = $this->get('/es/es/');
    $response->assertOk();

    $html = (string) $response->getContent();
    $sizeKb = strlen($html) / 1024;

    expect($sizeKb)->toBeLessThan(150, sprintf('Home HTML is %.1f KB (budget 150 KB).', $sizeKb));

    // A single bundled stylesheet + a single bundled script.
    $assets = array_unique(preg_match_all('#/build/assets/[A-Za-z0-9._-]+#', $html, $m) ? $m[0] : []);
    expect(count($assets))->toBeLessThanOrEqual(2, 'Too many bundled asset references: '.implode(', ', $assets));
});
