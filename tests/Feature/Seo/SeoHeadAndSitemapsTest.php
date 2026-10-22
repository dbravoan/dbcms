<?php

declare(strict_types=1);

use Tests\Support\BlogFixtures;
use Tests\Support\CmsFixtures;
use Tests\Support\PublicContentComposition;
use Tests\Support\ShopFixtures;

uses(PublicContentComposition::class);

/**
 * SEO/GEO regression suite: every public page type must emit a complete,
 * consistent head across themes.
 */
beforeEach(function (): void {
    CmsFixtures::localization(['es', 'en'], ['es', 'us']);
    CmsFixtures::identities();
    CmsFixtures::home()->translations()->update(['meta_description' => 'Página de inicio de prueba.']);
    BlogFixtures::post();
    ShopFixtures::product();
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);
});

dataset('seo_pages', [
    'home' => '/es/es/',
    'blog index' => '/es/es/blog',
    'blog post' => '/es/es/blog/arquitectura-modular',
    'shop index' => '/es/es/tienda',
    'shop product' => '/es/es/tienda/prd-edge-1000',
    'gallery index' => '/es/es/galeria',
]);

it('emits a complete SEO head on every public page', function (string $path): void {
    $response = $this->get($path);
    $response->assertOk();

    $html = (string) $response->getContent();
    $head = substr($html, 0, (int) strpos($html, '</head>'));

    foreach ([
        '<title>',
        'rel="canonical"',
        'name="robots"',
        'name="description"',
        'og:type',
        'og:title',
        'og:url',
        'og:locale',
        'twitter:card',
        'application/ld+json',
    ] as $needle) {
        expect(str_contains($head, $needle))->toBeTrue("{$path} is missing {$needle}");
    }
})->with('seo_pages');

it('emits valid JSON-LD with the expected schema types', function (): void {
    $response = $this->get('/es/es/');
    $html = (string) $response->getContent();

    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    expect($matches)->not->toBeEmpty('No JSON-LD block found.');

    $decoded = json_decode($matches[1], true);
    expect($decoded)->toBeArray()
        ->and($decoded['@context'])->toBe('https://schema.org');
});

it('canonicalises the current url and never duplicates the canonical tag', function (): void {
    $html = (string) $this->get('/es/es/blog')->getContent();

    expect(substr_count($html, 'rel="canonical"'))->toBe(1);

    preg_match('/<link rel="canonical" href="([^"]+)"/', $html, $m);
    expect($m[1])->toContain('/es/es/blog');
});

it('exposes a segmented sitemap index', function (): void {
    $response = $this->get('/sitemap.xml');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/xml; charset=utf-8');

    $xml = (string) $response->getContent();

    foreach (['pages.xml', 'posts.xml', 'products.xml', 'galleries.xml'] as $segment) {
        expect(str_contains($xml, $segment))->toBeTrue("Sitemap index is missing {$segment}");
    }
});

it('serves each sitemap segment as valid xml', function (string $segment): void {
    $response = $this->get("/sitemaps/{$segment}.xml");
    $response->assertOk();

    expect(simplexml_load_string((string) $response->getContent()))->not->toBeFalse();
})->with(['pages', 'posts', 'products', 'galleries']);

it('rejects unknown sitemap segments', function (): void {
    $this->get('/sitemaps/bogus.xml')->assertNotFound();
});

it('serves a dynamic robots.txt pointing at the sitemap and llms index', function (): void {
    $response = $this->get('/robots.txt');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/plain; charset=utf-8');

    $body = (string) $response->getContent();
    expect($body)->toContain('Sitemap:')
        ->toContain('LLMs:')
        ->toContain('User-agent: *');
});

it('serves a valid RSS feed', function (): void {
    $response = $this->get('/feed.xml');
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8');

    expect(simplexml_load_string((string) $response->getContent()))->not->toBeFalse();
});
