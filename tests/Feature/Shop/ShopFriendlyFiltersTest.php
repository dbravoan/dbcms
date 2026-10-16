<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\CmsFixtures;
use Tests\Support\FilterFixtures;
use Tests\Support\ShopComposition;

uses(RefreshDatabase::class, ShopComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    FilterFixtures::shop();
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

afterEach(function (): void {
    Cache::flush();
});

test('a friendly shop sort filter renders a dynamic noindex page', function (): void {
    // The canonical sort token resolves directly.
    $response = $this->get('/es/tienda/precio-asc');

    $response->assertOk();

    expect((string) $response->getContent())->toContain('noindex, follow');
});

test('a non-canonical shop permutation 301s to the canonical URL', function (): void {
    // Valid dimensions in reverse order redirect to the declared order.
    $this->get('/es/tienda/precio-desc/entre-0-100000-eur')
        ->assertStatus(301)
        ->assertRedirect('/es/tienda/entre-0-100000-eur/precio-desc');
});

test('an unknown shop filter value is a 404', function (): void {
    // A qualified category must exist in the declared vocabulary.
    $this->followingRedirects()->get('/es/tienda/categoria-no-such-category')->assertNotFound();

    // A canonical range without results is a 404, never an empty page.
    $this->get('/es/tienda/entre-999999998-999999999-eur')->assertNotFound();
});
