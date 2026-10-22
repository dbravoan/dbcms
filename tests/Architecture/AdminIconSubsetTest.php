<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * The front/back office self-hosts a minimal Font Awesome subset generated from
 * the icon classes actually used (`npm run icons`). An icon that is used in the
 * markup but missing from the subset renders as an empty box, which is easy to
 * ship unnoticed — this guardrail catches it in the suite instead.
 */
test('every font awesome icon used in the project exists in the self-hosted subset', function (): void {
    $css = (string) File::get(resource_path('css/generated/fontawesome.css'));

    preg_match_all('/\.fa-(?:solid|regular|brands)\.fa-([a-z0-9-]+)::before/', $css, $matches);

    expect($matches[1])->not->toBeEmpty();

    $available = array_unique($matches[1]);

    // FontAwesome 5 names still used in older markup (kept in sync with
    // bin/generate-fontawesome-css.mjs).
    $aliases = [
        'check-circle' => 'circle-check',
        'columns' => 'table-columns',
    ];

    $directories = [
        base_path('themes'),
        base_path('modules'),
        base_path('block-packages'),
        base_path('app'),
        resource_path('views'),
    ];

    $offenders = [];

    foreach ($directories as $directory) {
        if (! File::isDirectory($directory)) {
            continue;
        }

        foreach (File::allFiles($directory) as $file) {
            $contents = (string) File::get($file->getPathname());

            preg_match_all('/fa-(?:solid|regular|brands)\s+fa-([a-z0-9-]+)/', $contents, $found);

            foreach (array_unique($found[1]) as $icon) {
                $icon = $aliases[$icon] ?? $icon;

                if (! in_array($icon, $available, true)) {
                    $offenders[] = "fa-{$icon} ({$file->getRelativePathname()})";
                }
            }
        }
    }

    expect(array_unique($offenders))->toBeEmpty(
        'Icons used but missing from the generated subset (run `npm run icons`): '.implode(', ', array_unique($offenders))
    );
});
