<?php

declare(strict_types=1);

/**
 * Enforces the canonical entity-form layout: every localized + market-aware
 * entity form must expose the language tabs (with flags) before the market
 * picker, so the user always finds them in the same place regardless of theme.
 */

/**
 * @return array<string, string> relative file => label
 */
function entityFormFiles(): array
{
    $root = dirname(__DIR__, 2);

    return [
        'resources/views/admin/pages/_form.blade.php' => 'Pages',
        'resources/views/admin/blog/posts/_form.blade.php' => 'Blog posts',
        'resources/views/admin/shop/products/_form.blade.php' => 'Shop products',
        'resources/views/admin/galleries/_form.blade.php' => 'Galleries',
        'resources/views/admin/forms/_form.blade.php' => 'Forms',
        'resources/views/admin/blog/categories/_form.blade.php' => 'Blog categories',
        'resources/views/admin/shop/categories/_form.blade.php' => 'Shop categories',
    ];
}

test('every entity form renders language tabs before the market picker', function (): void {
    foreach (entityFormFiles() as $relative => $label) {
        $path = dirname(__DIR__, 2).'/'.$relative;
        expect(file_exists($path))->toBeTrue("Missing entity form [{$relative}].");

        $content = (string) file_get_contents($path);

        $hasTabs = str_contains($content, 'locale-tabs');
        $hasMarket = str_contains($content, 'market-picker');

        expect($hasTabs)->toBeTrue("{$label} form must use <x-admin.locale-tabs>.");
        expect($hasMarket)->toBeTrue("{$label} form must use <x-admin.market-picker>.");

        $tabsPos = strpos($content, 'locale-tabs');
        $marketPos = strpos($content, 'market-picker');

        expect($tabsPos)->toBeLessThan(
            $marketPos,
            "{$label} form must render the language tabs before the market picker."
        );
    }
});

test('entity forms do not hardcode a language flag map', function (): void {
    foreach (entityFormFiles() as $relative => $label) {
        $content = (string) file_get_contents(dirname(__DIR__, 2).'/'.$relative);

        // Flags come from the backoffice (shared by AdminLayoutComposer / locale-tabs).
        expect(str_contains($content, '$flags = ['))
            ->toBeFalse("{$label} form must not hardcode a flag map.");
    }
});
