<?php

declare(strict_types=1);

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Sections\SectionRendererService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;
use Tests\Support\VisualContentFixtures;
use Tests\Support\VisualModulesComposition;

uses(RefreshDatabase::class, VisualModulesComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    VisualContentFixtures::form();
    VisualContentFixtures::post();
    VisualContentFixtures::product();
    VisualContentFixtures::gallery();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->registry = app(BlockRegistryInterface::class);
    $this->renderer = app(SectionRendererService::class);
});

test('block registry registers all static and dynamic block types', function (): void {
    $activeBlocks = $this->registry->getActiveBlocks();

    expect($activeBlocks)->toHaveKeys([
        'hero',
        'text',
        'features',
        'kpi',
        'pricing',
        'faq',
        'testimonials',
        'cta',
        'before_after',
        'code_embed',
        'roi',
        'widget_posts',
        'charts',
        'widget_form',
        'gallery',
        'widget_products',
    ]);
});

test('every active block type exposes a non-empty FontAwesome icon for pickers', function (): void {
    foreach ($this->registry->getActiveBlocks() as $type => $block) {
        $icon = (string) $block->getIcon();

        expect(trim($icon))->not->toBe('');
        expect($icon)->toContain('fa-');
    }
});

test('block registry dynamic data payloads provide all select entities', function (): void {
    $payloads = $this->registry->getDynamicDataPayloads();

    expect($payloads)->toHaveKeys([
        'forms',
        'blog_categories',
        'blog_posts',
        'shop_categories',
        'shop_products',
        'gallery_albums',
    ]);

    expect($payloads['forms'])->not->toBeEmpty();
    expect($payloads['blog_categories'])->not->toBeEmpty();
    expect($this->registry->get('charts')->getEditorSchema()['chart_type']['options'])->toHaveKeys(['bar', 'line', 'donut', 'metric']);
});

test('section renderer renders decoupled roi block composing charts service', function (): void {
    $html = $this->renderer->renderSection([
        'type' => 'roi',
        'title' => 'Calculadora de Rentabilidad',
        'initial_investment' => 15000,
        'return_percentage' => 40,
        'months' => 12,
        'chart_mode' => 'bar',
        'content' => 'Nota metodológica sobre el ahorro en arquitectura perimetral.',
    ]);

    expect($html)->toContain('Calculadora de Rentabilidad');
    expect($html)->toContain('Retorno Neto Proyectado (ROI)');
    expect($html)->toContain('<svg'); // Verified that SVG chart was generated
});

test('section renderer renders polymorphic card grid for features, pricing, testimonials', function (): void {
    // 1. Features
    $featuresHtml = $this->renderer->renderSection([
        'type' => 'features',
        'title' => 'Ventajas Competitivas',
        'grid_columns' => '3',
        'items' => [
            ['icon' => '⚡', 'title' => 'Alta Velocidad', 'description' => 'Menos de 20ms'],
            ['icon' => '🛡️', 'title' => 'Seguridad TLS', 'description' => 'Cifrado total'],
        ],
    ]);

    expect($featuresHtml)->toContain('Ventajas Competitivas');
    expect($featuresHtml)->toContain('Alta Velocidad');
    expect($featuresHtml)->toContain('cms-card');

    // 2. Pricing
    $pricingHtml = $this->renderer->renderSection([
        'type' => 'pricing',
        'title' => 'Nuestras Tarifas',
        'items' => [
            ['name' => 'Pro Enterprise', 'price' => '99€', 'period' => '/mes', 'badge' => 'Top', 'features' => ['SLA 99.9%'], 'cta_text' => 'Comprar'],
        ],
    ]);

    expect($pricingHtml)->toContain('Nuestras Tarifas');
    expect($pricingHtml)->toContain('Pro Enterprise');
    expect($pricingHtml)->toContain('99€');

    // 3. Testimonials
    $testimonialsHtml = $this->renderer->renderSection([
        'type' => 'testimonials',
        'title' => 'Opiniones Reales',
        'items' => [
            ['author' => 'Sara Connor', 'role' => 'Tech Lead', 'quote' => 'Excelente solución modular.', 'rating' => 5],
        ],
    ]);

    expect($testimonialsHtml)->toContain('Opiniones Reales');
    expect($testimonialsHtml)->toContain('Sara Connor');
    expect($testimonialsHtml)->toContain('★★★★★');
});

test('section renderer renders multi-column layout with nested sub-blocks', function (): void {
    $multiColSection = [
        'columns_layout' => '2',
        'columns' => [
            [
                'id' => 'col_1',
                'blocks' => [
                    ['type' => 'hero', 'title' => 'Columna Izquierda Hero', 'content' => 'Subtítulo'],
                ],
            ],
            [
                'id' => 'col_2',
                'blocks' => [
                    ['type' => 'widget_form', 'title' => 'Formulario en Columna 2', 'form_slug' => 'contacto'],
                ],
            ],
        ],
    ];

    $html = $this->renderer->renderSection($multiColSection);

    expect($html)->toContain('cms-section-grid');
    expect($html)->toContain('Columna Izquierda Hero');
    expect($html)->toContain('Formulario en Columna 2');
    expect($html)->toContain('form');
});
