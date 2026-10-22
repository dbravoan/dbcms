<?php

declare(strict_types=1);
use Database\Seeders\PlatformShowcaseSeeder;
use Tests\Support\DemoComposition;

uses(DemoComposition::class);

/**
 * Front-office performance guardrails.
 *
 * These tests fail the build when a regression reintroduces a blocking CDN, an
 * un-bundled asset pipeline or an oversized document. They encode the performance
 * budget agreed for the public site.
 */
it('keeps the public HTML within the performance budget', function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    config(['cms.theme' => 'db-cyber', 'cms.admin_theme' => 'dbcms', 'cms.page_cache.enabled' => false]);

    $response = $this->get('/es/es/');
    $response->assertOk();

    $html = (string) $response->getContent();
    $sizeKb = strlen($html) / 1024;

    expect($sizeKb)->toBeLessThan(150, sprintf('Home HTML is %.1f KB (budget 150 KB).', $sizeKb));

    // Shared CSS + selected brand CSS + active frontend CSS + one shared script.
    // Installing additional themes must not add more requests to this page.
    $assets = array_unique(preg_match_all('#/build/assets/[A-Za-z0-9._-]+#', $html, $m) ? $m[0] : []);
    expect(count($assets))->toBeLessThanOrEqual(4, 'Too many bundled asset references: '.implode(', ', $assets));
});
