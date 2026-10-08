<?php

declare(strict_types=1);

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\DTOs\Seo\SeoMetadataDTO;
use App\Services\Seo\JsonLdSchemaService;
use App\Services\Seo\OpenGraphService;

test('optional SEO images are omitted when their unconfigured fallback files are missing', function (): void {
    app(GeneralSettingsServiceInterface::class)->set('general.site_logo', '');
    app(GeneralSettingsServiceInterface::class)->set('general.og_image', '');
    config(['seo.defaults.og_image' => url('/images/og-default.png')]);
    expect(is_file(public_path('images/logo.png')))->toBeFalse();
    expect(is_file(public_path('images/og-default.png')))->toBeFalse();
    $graph = app(JsonLdSchemaService::class)->generateSiteGraph();
    expect($graph['@graph'][0])->not->toHaveKey('logo');
    $tags = app(OpenGraphService::class)->resolveMetaTags(new SeoMetadataDTO('A public title', 'A real description', url('/')));
    expect($tags)->not->toHaveKey('og:image')->not->toHaveKey('twitter:image')->and($tags['twitter:card'])->toBe('summary');
});

test('configured SEO images preserve their destination and relative media become absolute URLs', function (): void {
    app(GeneralSettingsServiceInterface::class)->set('general.site_logo', '/uploads/public-identity.webp');
    app(GeneralSettingsServiceInterface::class)->set('general.og_image', '/uploads/public-social.webp');
    $graph = app(JsonLdSchemaService::class)->generateSiteGraph();
    expect($graph['@graph'][0]['logo']['url'])->toBe(url('/uploads/public-identity.webp'));
    $tags = app(OpenGraphService::class)->resolveMetaTags(new SeoMetadataDTO('Public title', 'Description', url('/')));
    expect($tags['og:image'])->toBe(url('/uploads/public-social.webp'));
    $external = 'https://cdn.example.test/identity.webp';
    $tags = app(OpenGraphService::class)->resolveMetaTags(new SeoMetadataDTO('Public title', 'Description', url('/'), $external));
    expect($tags['og:image'])->toBe($external)->and($tags['twitter:image'])->toBe($external)->and($tags['twitter:card'])->toBe('summary_large_image');
});

test('JsonLdSchemaService generates valid site and organization graph', function (): void {
    $service = new JsonLdSchemaService;
    $graph = $service->generateSiteGraph();

    expect($graph['@context'])->toBe('https://schema.org')
        ->and($graph['@graph'])->toBeArray()
        ->and(count($graph['@graph']))->toBeGreaterThanOrEqual(2);
});
