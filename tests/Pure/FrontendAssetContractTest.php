<?php

declare(strict_types=1);

it('never loads a blocking third-party CDN in any front-office theme', function (): void {
    $layouts = glob(dirname(__DIR__, 2).'/themes/*/layouts/app.blade.php') ?: [];

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
        glob(dirname(__DIR__, 2).'/resources/views/themes/admin/*/admin/layout.blade.php') ?: [],
        glob(dirname(__DIR__, 2).'/resources/views/themes/admin/*/errors/layout.blade.php') ?: [],
        [dirname(__DIR__, 2).'/resources/views/admin/layout.blade.php'],
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

    foreach (glob(dirname(__DIR__, 2).'/themes/*/**/*.blade.php') ?: [] as $view) {
        $contents = (string) file_get_contents($view);

        // Blade-aware scan: `->` inside {{ ... }} must not end the tag.
        preg_match_all('/<img\b(?:(?!\{\{|\?>)[^>])*(?:\{\{.*?\}\}(?:(?!\{\{|\?>)[^>])*)*>/s', $contents, $matches);

        foreach ($matches[0] as $tag) {
            if (! str_contains($tag, 'loading=')) {
                $offenders[] = str_replace(dirname(__DIR__, 2).'/', '', $view);
            }
        }
    }

    $offenders = array_values(array_unique($offenders));

    expect($offenders)->toBeEmpty("Views with images missing loading=\"lazy|eager\":\n".implode("\n", array_slice($offenders, 0, 10)));
});
