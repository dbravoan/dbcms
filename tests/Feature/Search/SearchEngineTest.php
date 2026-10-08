<?php

declare(strict_types=1);

use App\Core\Search\SearchDocument;
use App\Core\Search\SearchEngine;
use App\Core\Search\SearchIndexer;
use App\Models\SearchDocumentIndex;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
| InnoDB publishes its FULLTEXT index at commit time, so rows written inside a
| test transaction are never matched by MATCH()…AGAINST(). The engine is built
| for exactly that: when the full-text pass finds nothing it answers with the
| literal pass (same ranking intent: title > excerpt > body, then entity
| weight). These tests therefore pin the contract — result set, ordering,
| facets, locale/type scoping, suggestions — which is what callers depend on.
| The full-text SQL itself is exercised against committed data (dev smoke run
| and the search page in the front office).
*/

beforeEach(function (): void {
    app(SearchIndexer::class)->flush();
});

function indexDocument(array $overrides = []): SearchDocument
{
    return new SearchDocument(...array_merge([
        'type' => 'post',
        'entityId' => '1',
        'locale' => 'es',
        'url' => '/es/es/blog/demo',
        'title' => 'Demo',
        'excerpt' => '',
        'body' => '',
        'weight' => 1.0,
    ], $overrides));
}

function engine(): SearchEngine
{
    return app(SearchEngine::class);
}

test('a query matches titles, excerpts and bodies', function (): void {
    $indexer = app(SearchIndexer::class);
    $indexer->index(indexDocument(['entityId' => '1', 'title' => 'Arquitectura modular']));
    $indexer->index(indexDocument(['entityId' => '2', 'title' => 'Otro artículo', 'excerpt' => 'Sobre arquitectura limpia']));
    $indexer->index(indexDocument(['entityId' => '3', 'title' => 'Nada que ver', 'body' => 'Hablaremos de arquitectura al final']));

    $results = engine()->search('arquitectura', 'es');

    expect($results->total)->toBe(3)
        ->and($results->hits)->toHaveCount(3)
        // A title match outweighs an excerpt match, which outweighs a body match.
        ->and($results->hits[0]->title)->toBe('Arquitectura modular')
        ->and($results->hits[1]->title)->toBe('Otro artículo')
        ->and($results->hits[2]->title)->toBe('Nada que ver');
});

test('matching is accent- and case-insensitive', function (): void {
    app(SearchIndexer::class)->index(indexDocument(['entityId' => '1', 'title' => 'Producción multimercado']));

    expect(engine()->search('produccion', 'es')->total)->toBe(1)
        ->and(engine()->search('PRODUCCION', 'es')->total)->toBe(1)
        ->and(engine()->search('producción', 'es')->total)->toBe(1)
        // The literal pass also finds inside words; a word that is not there at
        // all still yields nothing.
        ->and(engine()->search('duccion', 'es')->total)->toBe(1)
        ->and(engine()->search('duccionzzz', 'es')->total)->toBe(0);
});

test('extra words narrow the result set', function (): void {
    $indexer = app(SearchIndexer::class);
    $indexer->index(indexDocument(['entityId' => '1', 'title' => 'Agentes de inteligencia artificial']));
    $indexer->index(indexDocument(['entityId' => '2', 'title' => 'Agentes de soporte']));

    expect(engine()->search('agentes', 'es')->total)->toBe(2)
        ->and(engine()->search('agentes inteligencia', 'es')->total)->toBe(1)
        ->and(engine()->search('agentes inteligencia artificial', 'es')->total)->toBe(1)
        ->and(engine()->search('agentes robots', 'es')->total)->toBe(0);
});

test('results are scoped by locale and entity type, and carry facets', function (): void {
    $indexer = app(SearchIndexer::class);
    $indexer->index(indexDocument(['entityId' => '1', 'locale' => 'es', 'title' => 'Laravel en producción']));
    $indexer->index(indexDocument(['entityId' => '2', 'locale' => 'en', 'title' => 'Laravel in production']));
    $indexer->index(indexDocument(['entityId' => '3', 'locale' => 'es', 'type' => 'product', 'title' => 'Servidor Laravel']));

    $results = engine()->search('laravel', 'es');

    expect($results->total)->toBe(2)
        ->and($results->facets)->toHaveKeys(['post', 'product'])
        ->and($results->facets['post']['count'])->toBe(1)
        ->and($results->facets['product']['count'])->toBe(1)
        ->and(engine()->search('laravel', 'en')->total)->toBe(1)
        ->and(engine()->search('laravel', 'es', 'product')->total)->toBe(1)
        ->and(engine()->search('laravel', 'es', 'product')->hits[0]->title)->toBe('Servidor Laravel');
});

test('the entity weight breaks ties between entities', function (): void {
    $indexer = app(SearchIndexer::class);
    $indexer->index(indexDocument(['entityId' => '1', 'title' => 'Servidor edge', 'weight' => 1.0]));
    $indexer->index(indexDocument(['entityId' => '2', 'type' => 'product', 'title' => 'Servidor edge pro', 'weight' => 3.0]));

    $results = engine()->search('servidor edge', 'es');

    expect($results->hits[0]->type)->toBe('product');
});

test('an empty result set suggests existing titles', function (): void {
    app(SearchIndexer::class)->index(indexDocument(['entityId' => '1', 'title' => 'Arquitectura modular y GEO']));

    $results = engine()->search('zzz arquitectura', 'es');

    expect($results->hasResults())->toBeFalse()
        ->and($results->suggestions)->toContain('Arquitectura modular y GEO');
});

test('an empty query returns an empty result without touching the index', function (): void {
    app(SearchIndexer::class)->index(indexDocument(['title' => 'Arquitectura']));

    expect(engine()->search('   ', 'es')->hasResults())->toBeFalse()
        ->and(engine()->search('   ', 'es')->total)->toBe(0);
});

test('hits expose the canonical url and the localized entity label', function (): void {
    app(SearchIndexer::class)->index(indexDocument([
        'entityId' => '1',
        'type' => 'product',
        'title' => 'Servidor edge',
        'url' => 'http://localhost:8080/es/es/tienda/servidor-edge',
    ]));

    $hit = engine()->search('servidor', 'es')->hits[0];

    expect($hit->url)->toBe('http://localhost:8080/es/es/tienda/servidor-edge')
        ->and($hit->label)->toBe('Producto')
        ->and(SearchDocumentIndex::query()->count())->toBe(1);
});

test('limit and offset paginate the ranked results', function (): void {
    $indexer = app(SearchIndexer::class);

    foreach (range(1, 5) as $i) {
        $indexer->index(indexDocument(['entityId' => (string) $i, 'title' => "Resultado {$i}"]));
    }

    $page1 = engine()->search('resultado', 'es', null, limit: 2);
    $page2 = engine()->search('resultado', 'es', null, limit: 2, offset: 2);

    expect($page1->total)->toBe(5)
        ->and($page1->hits)->toHaveCount(2)
        ->and($page2->hits)->toHaveCount(2)
        ->and($page1->hits[0]->title)->toBe('Resultado 1')
        ->and($page2->hits[0]->title)->toBe('Resultado 3');
});

test('the engine reports which pass answered the query', function (): void {
    app(SearchIndexer::class)->index(indexDocument(['entityId' => '1', 'title' => 'Mejora tu UX con IA']));

    expect(engine()->search('ux', 'es')->engine)->toBe('fallback');
});
