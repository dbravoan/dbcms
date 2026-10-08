<?php

declare(strict_types=1);

use App\DTOs\Front\FrontPageData;
use App\Models\Market;
use Database\Seeders\PlatformShowcaseSeeder;
use Modules\Gallery\Contracts\GalleryFrontPageServiceInterface;
use Modules\Gallery\DTOs\GalleryFrontInput;
use Modules\Search\Contracts\SearchFrontPageServiceInterface;
use Modules\Search\DTOs\SearchFrontInput;

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->market = Market::where('code', 'eu')->firstOrFail();
});

test('gallery presentation returns immutable view data and missing albums without a response', function (): void {
    $service = app(GalleryFrontPageServiceInterface::class);
    $result = $service->show(new GalleryFrontInput($this->market, 'es', url('/eu/es/galeria/recorrido-plataforma'), 'recorrido-plataforma'));
    expect($result)->toBeInstanceOf(FrontPageData::class);
    expect((new ReflectionClass($result))->isReadOnly())->toBeTrue();
    expect($result->viewName)->not->toBeNull();
    expect($result->data)->toHaveKeys(['seoMeta', 'gallery', 'translation', 'items', 'breadcrumbs', 'hreflangs', 'alternateUrls', 'currentMarket']);
    expect($result->data['seoMeta']->schema['@graph'][0]['inLanguage'])->toBe('es');
    $missing = $service->show(new GalleryFrontInput($this->market, 'es', url('/eu/es/galeria/not-available'), 'not-available'));
    expect($missing->viewName)->toBeNull()->and($missing->redirectUrl)->toBeNull();
    $index = $service->index(new GalleryFrontInput($this->market, 'es', url('/eu/es/galeria'), page: 2));
    expect($index->data['galleries']->currentPage())->toBe(2);
    expect($index->data['galleries']->url(3))->toContain('/eu/es/galeria?page=3');
});

test('search presentation declares canonical redirects and view outcomes through its module contract', function (): void {
    $service = app(SearchFrontPageServiceInterface::class);
    $result = $service->hub(new SearchFrontInput($this->market, 'es', 'Calidad Europa'));
    expect($result)->toBeInstanceOf(FrontPageData::class);
    expect($result->redirectUrl)->toEndWith('/eu/es/buscar/calidad-europa');
    expect($result->redirectStatus)->toBe(301)->and($result->viewName)->toBeNull();
    $view = $service->hub(new SearchFrontInput($this->market, 'es'));
    expect($view->data)->toHaveKeys(['layout', 'currentMarket', 'searchUrl', 'coverage', 'popularTerms', 'indexable', 'breadcrumbs', 'seoMeta']);
    $results = $service->results(new SearchFrontInput($this->market, 'es', term: 'calidad', entityType: 'post'));
    expect($results->data['seoMeta']->schema['url'])->toBe($results->data['seoMeta']->canonical);
    expect($results->data['seoMeta']->description)->not->toContain('%s');
    $missing = $service->results(new SearchFrontInput($this->market, 'es', term: 'calidad', entityType: 'imaginary-resource'));
    expect($missing->viewName)->toBeNull()->and($missing->redirectUrl)->toBeNull();
});
