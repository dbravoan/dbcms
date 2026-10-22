<?php

declare(strict_types=1);

use Database\Seeders\CmsFoundationSeeder;

beforeEach(function (): void {
    $this->seed(CmsFoundationSeeder::class);
});

test('/sitemap.xml returns a valid segmented sitemap index', function (): void {
    $response = $this->get('/sitemap.xml');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<sitemapindex', false)
        ->assertSee('sitemaps/pages.xml', false);
});

test('/sitemaps/{segment}.xml returns a valid child sitemap', function (): void {
    $response = $this->get('/sitemaps/pages.xml');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<urlset', false);
});

test('unknown and out-of-range sitemap segments remain unavailable', function (): void {
    $this->get('/sitemaps/unknown.xml')->assertNotFound();
    $this->get('/sitemaps/pages-999.xml')->assertNotFound();
});

test('/{market}/{locale}/sitemap.xml returns valid regional XML sitemaps', function (): void {
    $response1 = $this->get('/es/es/sitemap.xml');
    $response1->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<urlset', false)
        ->assertSee('/es/es', false);

});

test('api endpoint returns valid Schema.org site graph', function (): void {
    $response = $this->getJson('/api/v1/geo/metadata');

    $response->assertStatus(200)
        ->assertJsonPath('@context', 'https://schema.org')
        ->assertJsonStructure([
            '@context',
            '@graph' => [
                '*' => ['@type', '@id'],
            ],
        ]);
});
