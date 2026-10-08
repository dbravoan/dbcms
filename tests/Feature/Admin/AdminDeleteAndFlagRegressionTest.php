<?php

declare(strict_types=1);

use App\Models\Market;
use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

/**
 * Blade copies the attributes of an `<x-…>` component as **literal text**: an
 * `@js()` (or any other directive) inside `@click="…"` on a component tag does
 * not compile and reaches Alpine as `JSON.parse('…')`, which raises
 * "Invalid or unexpected token" and breaks the whole Alpine tree — the delete
 * button (and everything sharing that `x-data`) stops working.
 *
 * This guardrail keeps that class of bug out: directives must live inside the
 * component's own Blade body or be resolved in a variable first.
 */
test('component attributes never carry un-compiled Blade directives', function (): void {
    $directories = [
        resource_path('views'),
        base_path('modules'),
        base_path('themes'),
    ];

    $offenders = [];

    foreach ($directories as $directory) {
        foreach (File::allFiles($directory) as $file) {
            if ($file->getExtension() !== 'php' || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $contents = (string) file_get_contents($file->getPathname());

            // Cualquier <x-…> cuya etiqueta contenga una directiva @js/@json/@php.
            if (preg_match_all('/<x-[a-zA-Z0-9_.\-]+[^>]*?@(?:js|json|php)\s*\([^>]*?>/s', $contents, $matches) > 0) {
                foreach ($matches[0] as $match) {
                    $offenders[] = $file->getRelativePathname().' → '.preg_replace('/\s+/', ' ', mb_substr($match, 0, 90));
                }
            }
        }
    }

    expect($offenders)->toBeEmpty(
        "Blade directives inside <x-…> attributes are not compiled and break Alpine:\n".implode("\n", $offenders)
    );
});

test('the admin delete flow still renders a valid alpine handler everywhere', function (): void {
    $pages = [
        '/admin/markets',
        '/admin/users',
        '/admin/menus',
        '/admin/blog/posts',
        '/admin/blog/categories',
        '/admin/shop/products',
        '/admin/shop/categories',
        '/admin/pages',
        '/admin/galleries',
        '/admin/forms',
        '/admin/forms/submissions',
        '/admin/tokens',
        '/admin/seo/filters',
    ];

    foreach ($pages as $page) {
        $response = $this->actingAs($this->admin, 'web')->get($page);
        $response->assertOk();

        $html = (string) $response->getContent();

        // Ninguna directiva llega cruda al navegador…
        expect($html)->not->toContain('@js(')
            ->not->toContain('@json(');
    }
});

test('the delete buttons dispatch a resolved payload', function (): void {
    $html = (string) $this->actingAs($this->admin, 'web')->get('/admin/markets')->getContent();

    // El botón usa el estado de Alpine resuelto por Blade, no una directiva.
    expect($html)->toContain('confirm-delete')
        ->toContain('deleteLabels.market')
        ->and(substr_count($html, 'confirm-delete'))->toBeGreaterThan(0);

    // Y las traducciones están disponibles en el x-data.
    expect($html)->toContain('Eliminar mercado')
        ->toContain('Eliminar idioma');
});

test('a market can be deleted through the admin endpoint', function (): void {
    // Un mercado sin contenido asociado (los que tienen datos están protegidos).
    $market = Market::query()->create([
        'code' => 'zz',
        'name' => 'Mercado temporal',
        'currency' => 'EUR',
        'default_locale' => 'es',
        'is_active' => false,
    ]);

    $this->actingAs($this->admin, 'web')
        ->delete(route('admin.markets.destroy', $market))
        ->assertRedirect(route('admin.markets.index'));

    expect(Market::query()->whereKey($market->getKey())->exists())->toBeFalse();
});

test('a market with content is protected instead of being deleted', function (): void {
    $market = Market::query()->where('code', 'mx')->firstOrFail();

    $this->actingAs($this->admin, 'web')
        ->delete(route('admin.markets.destroy', $market))
        ->assertRedirect(route('admin.markets.index'))
        ->assertSessionHas('error');

    expect(Market::query()->whereKey($market->getKey())->exists())->toBeTrue();
});

test('the market edit modal receives the flag of the market being edited', function (): void {
    $market = Market::query()->where('code', 'es')->firstOrFail();

    $html = (string) $this->actingAs($this->admin, 'web')->get('/admin/markets')->getContent();

    // openEdit() copia la bandera al estado del modal…
    expect($html)->toContain('flag: m.flag')
        // …y el selector de bandera la sigue declarativamente.
        ->toContain('editMarket.flag')
        ->toContain('syncFrom');
});
