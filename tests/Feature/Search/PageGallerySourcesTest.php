<?php

declare(strict_types=1);

use App\Core\Search\Contracts\SearchSourceInterface;
use App\Core\Search\Contracts\SearchSourceRegistryInterface;
use App\Core\Search\SearchDocument;
use App\Core\Search\SearchEngine;
use App\Core\Search\SearchIndexer;
use App\Models\Language;
use App\Models\Market;
use App\Models\SearchDocumentIndex;
use App\Services\Resources\MarketMutationHandler;
use Database\Seeders\PlatformShowcaseSeeder;
use Modules\Gallery\Models\Gallery;
use Modules\Pages\Models\Page;

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->market = Market::where('code', 'es')->firstOrFail();
});

test('pages and galleries register real public search sources', function (): void {
    $sources = app(SearchSourceRegistryInterface::class);
    expect($sources->has('page'))->toBeTrue()->and($sources->has('gallery'))->toBeTrue();
});

test('page search includes published block content without draft sections', function (): void {
    $page = Page::create(['is_published' => true, 'template' => 'default']);
    $page->translations()->create(['locale' => 'es', 'title' => 'Public source', 'slug' => 'block-search-source']);
    foreach ([['published', 'Calidadbloquepublico'], ['draft', 'Confidencialbloqueborrador']] as [$status, $body]) {
        $section = $page->sections()->create(['type' => 'text', 'is_active' => true, 'status' => $status, 'order' => 0]);
        $section->translations()->create(['locale' => 'es', 'content_json' => ['content' => $body]]);
    }
    app(SearchIndexer::class)->indexEntity('page', $page->getKey());
    expect(app(SearchEngine::class)->search('Calidadbloquepublico', 'es', 'page', market: 'es')->total)->toBe(1);
    expect(app(SearchEngine::class)->search('Confidencialbloqueborrador', 'es', 'page', market: 'es')->total)->toBe(0);
});

test('gallery documents retain public market and translated content restrictions', function (): void {
    $gallery = Gallery::create(['is_active' => true, 'title' => 'Gallery source', 'slug' => 'gallery-source']);
    $gallery->translations()->create(['locale' => 'es', 'title' => 'Normativagaleria', 'slug' => 'normativa-galeria', 'description' => 'Contenido público']);
    $gallery->markets()->sync([$this->market->getKey()]);
    app(SearchIndexer::class)->indexEntity('gallery', $gallery->getKey());
    expect(app(SearchEngine::class)->search('Normativagaleria', 'es', 'gallery', market: 'es')->total)->toBe(1);
    expect(app(SearchEngine::class)->search('Normativagaleria', 'en', 'gallery', market: 'es')->total)->toBe(0);
    expect(app(SearchEngine::class)->search('Normativagaleria', 'es', 'gallery', market: 'eu')->total)->toBe(0);
});

test('scheduled page search becomes visible at its boundary without another write or rebuild', function (): void {
    $this->travelTo(now()->startOfSecond());
    $page = Page::create(['is_published' => true, 'published_at' => now()->addMinute(), 'template' => 'default']);
    $page->translations()->create(['locale' => 'es', 'title' => 'Calendariopublicacion', 'slug' => 'calendario-publicacion']);
    app(SearchIndexer::class)->indexEntity('page', $page->getKey());
    expect(app(SearchEngine::class)->search('Calendariopublicacion', 'es', 'page', market: 'es')->total)->toBe(0);
    $this->travel(1)->minutes();
    expect(app(SearchEngine::class)->search('Calendariopublicacion', 'es', 'page', market: 'es')->total)->toBe(1);
});

test('batch imports defer repeated projections and incremental edits and deletions remain indexed', function (): void {
    $indexer = app(SearchIndexer::class);
    $registry = app(SearchSourceRegistryInterface::class);
    $source = new class($registry->get('page')) implements SearchSourceInterface
    {
        public int $projections = 0;

        public function __construct(private readonly SearchSourceInterface $source) {}

        public function type(): string
        {
            return $this->source->type();
        }

        public function label(string $locale): string
        {
            return $this->source->label($locale);
        }

        public function weight(): float
        {
            return $this->source->weight();
        }

        public function documents(?string $locale = null): iterable
        {
            yield from $this->source->documents($locale);
        }

        public function documentsFor(int|string $entityId): iterable
        {
            $this->projections++;
            yield from $this->source->documentsFor($entityId);
        }
    };
    $registry->register($source);
    $page = $indexer->batch(function (): Page {
        $page = Page::create(['is_published' => true, 'template' => 'default']);
        $page->translations()->create(['locale' => 'es', 'title' => 'Batch first title', 'slug' => 'batch-source']);
        $page->translations()->firstOrFail()->update(['title' => 'Batch final title']);
        expect(SearchDocumentIndex::where('type', 'page')->where('entity_id', $page->getKey())->exists())->toBeFalse();

        return $page;
    });
    expect(SearchDocumentIndex::where('type', 'page')->where('entity_id', $page->getKey())->exists())->toBeTrue();
    expect($source->projections)->toBe(1);
    $page->translations()->firstOrFail()->update(['title' => 'Indiceactualizado']);
    expect(app(SearchEngine::class)->search('Indiceactualizado', 'es', 'page', market: 'es')->total)->toBe(1);
    $page->delete();
    expect(SearchDocumentIndex::where('type', 'page')->where('entity_id', $page->getKey())->exists())->toBeFalse();
});

test('adding an existing language to a market refreshes projections without creating translations', function (): void {
    $page = app(SearchIndexer::class)->batch(function (): Page {
        $page = Page::create(['is_published' => true, 'template' => 'default']);
        $page->translations()->create(['locale' => 'es', 'title' => 'Mercadonuevopagina', 'slug' => 'mercado-nuevo-pagina']);

        return $page;
    });
    $market = Market::create(['code' => 'qa', 'name' => 'QA market', 'default_locale' => 'es', 'currency' => 'EUR', 'is_active' => true]);
    expect(app(SearchEngine::class)->search('Mercadonuevopagina', 'es', 'page', market: 'qa')->total)->toBe(0);
    $language = Language::where('code', 'es')->firstOrFail();
    $market->languages()->sync([$language->getKey()]);
    app(MarketMutationHandler::class)->saved($market, ['language_ids' => [$language->getKey()]], false);
    expect(app(SearchEngine::class)->search('Mercadonuevopagina', 'es', 'page', market: 'qa')->total)->toBe(1);
    expect($page->translations()->count())->toBe(1);
});

test('failed source replacement preserves the previous index after a completed batch', function (): void {
    $indexer = app(SearchIndexer::class);
    $indexer->index(new SearchDocument('page', 'qa-preserved', 'es', 'https://example.invalid/old', 'Previous index'));
    app(SearchSourceRegistryInterface::class)->register(new class implements SearchSourceInterface
    {
        public function type(): string
        {
            return 'page';
        }

        public function label(string $locale): string
        {
            return 'Page';
        }

        public function weight(): float
        {
            return 1.0;
        }

        public function documents(?string $locale = null): iterable
        {
            for ($i = 0; $i < 201; $i++) {
                yield new SearchDocument('page', 'qa-new-'.$i, 'es', 'https://example.invalid/new-'.$i, 'New index');
            }
            throw new RuntimeException('Source projection failed after one complete batch.');
        }

        public function documentsFor(int|string $entityId): iterable
        {
            yield from $this->documents();
        }
    });
    expect(fn () => $indexer->indexSource('page', replace: true))->toThrow(RuntimeException::class, 'Source projection failed');
    expect(SearchDocumentIndex::where('type', 'page')->where('entity_id', 'qa-preserved')->exists())->toBeTrue();
    expect(SearchDocumentIndex::where('type', 'page')->where('entity_id', 'like', 'qa-new-%')->count())->toBe(0);
    expect(fn () => $indexer->batch(function () use ($indexer): void {
        $indexer->indexEntity('page', 'qa-new');
        throw new LogicException('Original import failure');
    }))->toThrow(LogicException::class, 'Original import failure');
});
