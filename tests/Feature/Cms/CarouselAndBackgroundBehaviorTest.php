<?php

declare(strict_types=1);

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\BlockTypeInterface;
use App\Core\Sections\SectionLayout;
use Modules\Pages\Services\SectionRendererService;

/**
 * Carousel block + background behaviour (parallax / fixed) on sections and
 * columns.
 *
 * The background controls live in the appearance modal's Section and Column
 * scopes, because those are the only places a background actually exists.
 */
test('the carousel block renders every slide with its controls', function (): void {
    $html = app(SectionRendererService::class)->renderBlockContent('carousel', [
        'type' => 'carousel',
        'autoplay' => true,
        'height' => 480,
        'show_arrows' => true,
        'show_dots' => true,
        'slides' => [
            ['image_url' => 'https://example.test/a.jpg', 'title' => 'SLIDE UNO', 'subtitle' => 'Sub uno', 'cta_text' => 'Ir', 'cta_url' => '/x'],
            ['image_url' => 'https://example.test/b.jpg', 'title' => 'SLIDE DOS'],
        ],
    ], null, 'es');

    expect($html)->toContain('SLIDE UNO')
        ->toContain('SLIDE DOS')
        ->toContain('Sub uno')
        ->toContain('autoplay: true')
        ->toContain('role="tablist"');

    expect(substr_count($html, 'cms-carousel-slide'))->toBe(2);
});

test('the carousel is a full width block and never nests inside a column', function (): void {
    $registry = app(BlockRegistryInterface::class);

    $carousel = $registry->get('carousel');
    expect($carousel)->not->toBeNull()
        ->and($carousel?->isFullWidth())->toBeTrue();

    // Los widgets que ya gestionan su propia rejilla tampoco entran en columnas.
    foreach (['features', 'pricing', 'testimonials', 'widget_posts', 'gallery', 'widget_products'] as $type) {
        $block = $registry->get($type);

        if ($block instanceof BlockTypeInterface) {
            expect($block->isFullWidth())->toBeTrue("Block [{$type}] should be full width.");
        }
    }
});

test('a section background can be pinned or parallaxed', function (): void {
    $renderer = app(SectionRendererService::class);

    $parallax = $renderer->renderSection([
        'id' => 0,
        'type' => 'text',
        'styling_json' => [
            'bg_type' => 'image',
            'bg_image_url' => 'https://example.test/hero.jpg',
            'bg_behavior' => 'parallax',
            'content' => 'hola',
        ],
    ], null, 'es');

    expect($parallax)->toContain('data-scroll-scrub="parallax"')
        ->toContain('cms-bg-layer');

    $fixed = $renderer->renderSection([
        'id' => 0,
        'type' => 'text',
        'styling_json' => [
            'bg_type' => 'image',
            'bg_image_url' => 'https://example.test/hero.jpg',
            'bg_behavior' => 'fixed',
            'content' => 'hola',
        ],
    ], null, 'es');

    expect($fixed)->toContain('background-attachment: fixed');
});

test('a column carries its own background and behaviour', function (): void {
    $html = app(SectionRendererService::class)->renderSection([
        'id' => 0,
        'type' => 'text',
        'styling_json' => [
            'columns_layout' => '2',
            'columns' => [
                ['id' => 'c1', 'style' => ['bg_type' => 'image', 'bg_image_url' => 'https://example.test/col.jpg', 'bg_behavior' => 'parallax'], 'blocks' => [['type' => 'text', 'title' => 'COL']]],
                ['id' => 'c2', 'blocks' => []],
            ],
        ],
    ], null, 'es');

    expect($html)->toContain('cms-column')
        ->toContain('cms-bg-layer')
        ->toContain('COL');
});

test('the column style is preserved by the canonical contract', function (): void {
    $payload = SectionLayout::normalize([
        'type' => 'text',
        'columns_layout' => '2',
        'columns' => [
            ['id' => 'c1', 'style' => ['bg_color' => '#111', 'bg_behavior' => 'fixed'], 'blocks' => []],
            ['id' => 'c2', 'blocks' => []],
        ],
    ]);

    expect($payload['columns'][0]['style']['bg_behavior'])->toBe('fixed')
        ->and($payload['columns'][0]['style']['bg_color'])->toBe('#111');
});

test('carousel slide headlines reach the plain text surfaces', function (): void {
    $text = SectionLayout::plainText([
        'columns_layout' => '1',
        'columns' => [[
            'id' => 'c1',
            'blocks' => [[
                'type' => 'carousel',
                'slides' => [
                    ['title' => 'Primera diapositiva', 'subtitle' => 'Subtítulo uno'],
                    ['title' => 'Segunda diapositiva'],
                ],
            ]],
        ]],
    ]);

    expect($text)->toContain('Primera diapositiva')
        ->toContain('Subtítulo uno')
        ->toContain('Segunda diapositiva');
});
