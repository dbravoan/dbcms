<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

afterEach(function (): void {
    Cache::flush();
});

test('a friendly shop sort filter renders a dynamic noindex page', function (): void {
    // Markerless canonical form (the legacy `/orden/precio-asc` 301s here).
    $response = $this->get('/es/es/tienda/precio-asc');

    $response->assertOk();

    expect((string) $response->getContent())->toContain('noindex, follow');
});

test('a non-canonical shop permutation 301s to the canonical URL', function (): void {
    // Legacy markers and compact ranges consolidate into the natural canonical.
    $this->get('/es/es/tienda/orden/precio-desc/precio/0-100000')
        ->assertStatus(301)
        ->assertRedirect('/es/es/tienda/entre-0-100000-eur/precio-desc');
});

test('an unknown shop filter value is a 404', function (): void {
    // Unknown category: the legacy marker form 301s to the canonical, which 404s.
    $this->followingRedirects()->get('/es/es/tienda/categoria/no-such-category')->assertNotFound();

    // A canonical range without results is a 404, never an empty page.
    $this->get('/es/es/tienda/entre-999999998-999999999-eur')->assertNotFound();
});
