<?php

declare(strict_types=1);

/**
 * Guardrails for the brand/theme refactor.
 *
 * The brand identity belongs to the active theme manifest
 * (resources/views/themes/admin/{id}/theme.json). Configuration files must stay
 * brand-agnostic and never branch on a specific theme identifier.
 *
 * @see docs/18_THEME_MANIFEST_AND_BRAND_REFERENCE.md
 */
it('never hardcodes a brand identifier in configuration files', function (): void {
    $forbidden = ['termocms', 'dbcms', 'Termosalud', 'termoCMS'];

    foreach (glob(__DIR__.'/../../config/*.php') ?: [] as $file) {
        $contents = (string) file_get_contents($file);
        $relative = 'config/'.basename($file);
        // The upstream package identity is not an administrative theme/brand.
        if (basename($file) === 'updates.php') {
            $contents = str_replace("'dbravoan/dbcms'", "'upstream/package'", $contents);
        }

        foreach ($forbidden as $needle) {
            // Allowed only as an `env('...', 'default')` fallback value.
            $allowedEnvPattern = "/env\(\s*'[^']*'\s*,\s*'".preg_quote($needle, '/')."'\s*\)/";

            $stripped = preg_replace($allowedEnvPattern, '', $contents) ?? $contents;

            expect(str_contains($stripped, $needle))
                ->toBeFalse("Configuration file {$relative} hardcodes the brand '{$needle}'. Brand data belongs to the theme manifest, not to config.");
        }
    }
});

it('does not branch on a specific theme identifier inside config', function (): void {
    $contents = (string) file_get_contents(__DIR__.'/../../config/cms.php');

    expect($contents)->not->toContain("=== 'termocms'")
        ->not->toContain('=== "termocms"')
        ->not->toContain("=== 'dbcms'")
        ->not->toContain('=== "dbcms"');
});

it('declares the brand identity only inside backoffice theme manifests', function (): void {
    $manifests = glob(__DIR__.'/../../resources/views/themes/admin/*/theme.json') ?: [];

    expect($manifests)->not->toBeEmpty();

    foreach ($manifests as $manifest) {
        $data = json_decode((string) file_get_contents($manifest), true);

        expect($data)->toBeArray();
        expect(array_key_exists('brand', $data))->toBeTrue();

        foreach (['name', 'tagline', 'accent', 'mode'] as $key) {
            expect(array_key_exists($key, $data['brand']))
                ->toBeTrue("Theme manifest {$manifest} brand block is missing '{$key}'.");
        }
    }
});
