<?php

declare(strict_types=1);

use App\Core\Sections\SectionLayout;
use Modules\Pages\Models\PageSection;
use Modules\Pages\Models\SectionTranslation;
use Modules\Pages\Services\SectionRendererService;

/**
 * El contrato canónico de secciones: sección → columnas → bloques.
 *
 * El editor escribe `columns`, el render lee `columns` y el contenido de cada
 * idioma gana al master. Antes, el editor escribía `columns_data`, el render
 * leía `columns` (ocupada por la rejilla del bloque) y el master pisaba el
 * idioma: las columnas no se pintaban y la web inglesa mostraba español.
 */
test('the editor payload renders multi-column sections', function (): void {
    $block = ['type' => 'text', 'title' => 'Bloque de columna', 'content' => 'Contenido'];

    $rendered = app(SectionRendererService::class)->renderSection([
        'id' => 0,
        'type' => 'text',
        'styling_json' => [
            'columns_layout' => '2',
            'columns' => [
                ['id' => 'c1', 'blocks' => [$block]],
                ['id' => 'c2', 'blocks' => []],
            ],
        ],
    ], null, 'es');

    expect($rendered)->toContain('cms-column')
        ->and(substr_count($rendered, 'cms-column'))->toBe(2)
        ->and($rendered)->toContain('Bloque de columna');
});

test('a section without columns wraps its content in one column', function (): void {
    $rendered = app(SectionRendererService::class)->renderSection([
        'id' => 0,
        'type' => 'text',
        'styling_json' => ['content' => 'Contenido propio'],
    ], null, 'es');

    expect($rendered)->toContain('Contenido propio');
});

test('every locale renders its own column content', function (): void {
    $master = [
        'columns_layout' => '2',
        'columns' => [['id' => 'c1', 'blocks' => [['type' => 'text', 'title' => 'MASTER']]], ['id' => 'c2', 'blocks' => []]],
    ];

    $section = new PageSection(['type' => 'text', 'styling_json' => $master]);
    $section->setRelation('translations', collect([
        new SectionTranslation([
            'locale' => 'es',
            'content_json' => ['columns' => [['id' => 'c1', 'blocks' => [['type' => 'text', 'title' => 'TEXTO ES']]], ['id' => 'c2', 'blocks' => []]]],
        ]),
        new SectionTranslation([
            'locale' => 'en',
            'content_json' => ['columns' => [['id' => 'c1', 'blocks' => [['type' => 'text', 'title' => 'TEXT EN']]], ['id' => 'c2', 'blocks' => []]]],
        ]),
    ]));

    $renderer = app(SectionRendererService::class);

    $es = $renderer->renderSection($section, null, 'es');
    $en = $renderer->renderSection($section, null, 'en');

    expect($es)->toContain('TEXTO ES')->not->toContain('TEXT EN')
        ->and($en)->toContain('TEXT EN')->not->toContain('TEXTO ES');
});

test('the block grid size uses its own key and never collides with columns', function (): void {
    $payload = SectionLayout::normalize(['type' => 'features', 'grid_columns' => '3']);

    expect($payload)->not->toHaveKey('columns')
        ->and($payload['grid_columns'])->toBe('3');

    // Un bloque con rejilla dentro de una sección con columnas: ambos conviven.
    $withColumns = SectionLayout::normalize([
        'type' => 'text',
        'columns_layout' => '2',
        'columns' => [['id' => 'c1', 'blocks' => [['type' => 'features', 'grid_columns' => '4']]]],
    ]);

    expect(SectionLayout::gridColumns($withColumns['columns'][0]['blocks'][0]))->toBe('4');
});

test('stacking back to one column keeps every block, in order', function (): void {
    $payload = SectionLayout::withLayout([
        'type' => 'text',
        'columns_layout' => '2',
        'columns' => [
            ['id' => 'c1', 'blocks' => [['type' => 'text', 'title' => 'uno']]],
            ['id' => 'c2', 'blocks' => [['type' => 'text', 'title' => 'dos']]],
        ],
    ], '1');

    $blocks = SectionLayout::blocks($payload);

    expect($blocks)->toHaveCount(2)
        ->and($blocks[0]['title'])->toBe('uno')
        ->and($blocks[1]['title'])->toBe('dos');
});

test('opening columns moves the section content into the first column', function (): void {
    $payload = SectionLayout::withLayout(['type' => 'text', 'title' => 'Contenido propio'], '2');
    $columns = SectionLayout::columns($payload);

    expect($columns)->toHaveCount(2)
        ->and($columns[0]['blocks'][0]['title'])->toBe('Contenido propio')
        ->and($columns[1]['blocks'])->toBe([]);
});

test('plain text gathers every block, columns included', function (): void {
    $text = SectionLayout::plainText([
        'columns_layout' => '2',
        'columns' => [
            ['id' => 'c1', 'blocks' => [['type' => 'text', 'title' => 'Primero', 'content' => 'Cuerpo uno']]],
            ['id' => 'c2', 'blocks' => [['type' => 'faq', 'title' => 'Segundo', 'items' => [['title' => 'Pregunta', 'description' => 'Respuesta']]]]],
        ],
    ]);

    expect($text)->toContain('Primero')->toContain('Cuerpo uno')->toContain('Segundo')->toContain('Pregunta');
});
