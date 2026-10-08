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

test('/sitemaps.xml returns the sitemap index as an alias of sitemap.xml', function (): void {
    $response = $this->get('/sitemaps.xml');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<sitemapindex', false);
});

test('/sitemaps/{segment}.xml returns a valid child sitemap', function (): void {
    $response = $this->get('/sitemaps/pages.xml');

    $response->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<urlset', false);
});

test('/{market}/{locale}/sitemap.xml and sitemaps.xml return valid regional XML sitemaps', function (): void {
    $response1 = $this->get('/es/es/sitemap.xml');
    $response1->assertStatus(200)
        ->assertHeader('Content-Type', 'application/xml; charset=utf-8')
        ->assertSee('<urlset', false)
        ->assertSee('/es/es', false);

    $response2 = $this->get('/es/es/sitemaps.xml');
    $response2->assertStatus(200)
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
