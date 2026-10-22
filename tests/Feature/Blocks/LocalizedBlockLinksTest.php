<?php

declare(strict_types=1);

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Models\Market;
use Tests\Support\CmsFixtures;

test('local block actions keep the explicit market and locale while external and anchor links stay intact', function (string $type): void {
    CmsFixtures::localization(['es', 'en'], ['eu', 'uk']);
    app()->setLocale('es');
    $market = Market::where('code', 'uk')->firstOrFail();
    $block = app(BlockRegistryInterface::class)->get($type);
    $urls = ['/modules', 'https://example.test/path?a=1&b=2', '//example.test/path', '#details', ''];
    $links = array_map(static fn (string $url): array => ['name' => 'Plan', 'title' => 'Slide', 'cta_text' => 'Open', 'cta_url' => $url], $urls);
    $payload = ['cta_text' => 'Open', 'secondary_cta_text' => 'Secondary', 'items' => $links, 'slides' => $links];
    foreach ($urls as $url) {
        $html = $block->render($payload + ['cta_url' => $url, 'secondary_cta_url' => $url], $market, 'en');
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $hrefs = [];
        foreach ($dom->getElementsByTagName('a') as $anchor) {
            $hrefs[] = $anchor->getAttribute('href');
        }
        if (in_array($type, ['pricing', 'carousel'], true)) {
            expect($hrefs)->toBe([url('/uk/en/modules'), ...array_slice($urls, 1, 3)]);
        } elseif ($url === '') {
            expect($hrefs)->toBe([]);
        } else {
            expect($hrefs)->toBe(array_fill(0, $type === 'hero' ? 2 : 1, $url === '/modules' ? url('/uk/en/modules') : $url));
        }
    }
})->with(['hero', 'cta', 'pricing', 'carousel']);
