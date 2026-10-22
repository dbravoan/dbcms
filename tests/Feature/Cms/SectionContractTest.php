<?php

declare(strict_types=1);

use App\Content\Pages\Models\Page;
use App\Content\Pages\Models\PageSection;
use App\Content\Pages\Models\SectionTranslation;
use App\Core\Sections\SectionLayout;
use App\Core\Sections\SectionRendererService;
use App\Models\Market;
use App\Services\Content\PublishedSectionTextService;

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
                ['id' => 'c2', 'blocks' => [['type' => 'text', 'content' => 'Segunda columna']]],
            ],
        ],
    ], null, 'es');

    $document = new DOMDocument;
    @$document->loadHTML($rendered);
    $columns = (new DOMXPath($document))->query('//*[contains(concat(" ", normalize-space(@class), " "), " cms-column ")]');
    expect($columns->length)->toBe(2)
        ->and($rendered)->toContain('Bloque de columna', 'Segunda columna');
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

test('untranslated sections are absent from public rendering and semantic text', function (): void {
    $section = new PageSection(['type' => 'text', 'styling_json' => ['type' => 'text', 'content' => 'MASTER PRIVATE TEXT']]);
    $section->setRelation('translations', collect([
        new SectionTranslation(['locale' => 'en', 'content_json' => ['content' => 'English only section']]),
    ]));
    $page = new Page;
    $page->setRelation('publishedSections', collect([$section]));
    $renderer = app(SectionRendererService::class);
    $text = app(PublishedSectionTextService::class);

    expect($renderer->renderSection($section, null, 'es'))->toBe('')
        ->and($text->text($page, new Market, 'es'))->toBe('')
        ->and($renderer->renderSection($section, null, 'en'))->toContain('English only section')
        ->and($text->text($page, new Market, 'en'))->toContain('English only section')->not->toContain('MASTER PRIVATE TEXT');
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
