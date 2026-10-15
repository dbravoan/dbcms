# 22 · General Search (cross-entity, friendly URLs)

> **Status**: Implemented (core engine + `modules/Search` + Blog/Shop/Pages/Gallery sources + WebMCP)
> **Scope**: every installed module that declares searchable entities
> **Related**: [`20_FRONT_FILTERING_AND_SEO_URLS.md`](20_FRONT_FILTERING_AND_SEO_URLS.md) · [`05_GEO_AND_SEO_ENGINEERING.md`](05_GEO_AND_SEO_ENGINEERING.md) · [`21_FORM_RENDERING_AND_FIELD_TYPES.md`](21_FORM_RENDERING_AND_FIELD_TYPES.md)

---

## 1. What it is

A single, site-wide search over **registered public sources**: blog posts, products,
pages, galleries and other entities a module explicitly registers, with:

- **friendly URLs**: `/{market}/{locale}/{prefix}/{term}` — no query string;
- **Google-like relevance**: column-weighted ranking, accent- and case-insensitive,
  prefix matching, all typed words required;
- **its own page**: a hub (`/{prefix}`) plus result pages, indexable only when
  the editor activates them from the **Filtros y búsquedas** inbox;
- **injection**: a reusable search box (header, block manager, any page).

Nothing is statically generated: results are runtime pages.

```
/es/es/buscar                      hub          (indexable once activated)
/es/es/buscar/laravel              results
/es/es/buscar/laravel/product      results of one entity type
/es/en/search/laravel              same, English prefix
/es/es/buscar?q=laravel            → 301 /es/es/buscar/laravel
/es/es/search/laravel              → 301 /es/es/buscar/laravel   (alias prefix)
```

---

## 2. Core engine (`App\Core\Search`)

| Class | Responsibility |
| :--- | :--- |
| `SearchDocument` | One indexable entity translation: type, entity id, locale, **canonical URL**, title, excerpt, body, weight |
| `SearchHit` / `SearchResults` | Ranked hit and the result of a query (hits, total, per-entity facets, suggestions, which pass answered) |
| `Contracts\SearchSourceInterface` | What a module implements: `type()`, `label($locale)`, `weight()`, `documents()`, `documentsFor($id)` |
| `SearchSourceRegistry` | Discovery point for the sources of the installed modules |
| `AbstractSearchSource` | Shared iteration, locale filtering and incremental reindexing; each source stays a few lines long |
| `ContextualSearchSource` | Canonical documents for each eligible market/language, with entity relations loaded once and iteration in groups of 200 |
| `SearchIndexer` | Idempotent upsert (`type + entity + locale + market`), reindex entity/type/all, forget, coverage stats |
| `SearchEngine` | Ranking and result assembly |
| `SearchEntityObserver` | Shared after-commit indexing; Page/Gallery observers resolve their own entity identifiers |
| `SearchContextRebuilder` | Rebuild projections after authorized market/language configuration changes |

`EntitySchema` declares the searchable contract of an entity
(`searchable`, `searchWeight`, `searchPrefix`, `urlTemplate`), so the admin inbox
knows which entities exist and how their URLs are built.

### Ranking and matching

- **Weights**: `title × 3 + excerpt × 2 + body × 1`, multiplied by the entity
  weight (a blog post is `1.2`, a product `1.1`, …).
- **Accent/case-insensitive**: the index uses a `*_ci` collation, so `produccion`
  finds `Producción`.
- **Prefix**: `larav` finds `Laravel` and `Laravelista`.
- **AND across words**: `agentes inteligencia` narrows the previous result set.
- **Literal fallback**: InnoDB publishes its FULLTEXT index at commit time and has
  a minimum token length, so when the full-text pass finds nothing the engine
  answers with a literal pass (AND per word, same ranking intent). That keeps
  short queries (`IA`, `UX`) and inside-word matches working, and it is why the
  engine reports which pass answered (`engine: fulltext|fallback`).

### Index storage

`search_documents` holds one denormalized row per entity translation and market with the
already-resolved canonical URL and **one FULLTEXT index per ranked column**
(MySQL requires `MATCH()` to match an index column list exactly, which is what
makes per-column weighting possible). Ranking uses that denormalized index;
public predicates also query module entities to revalidate publication, language,
market and module availability. Result URLs are resolved for the requested context.

Rebuild it with:

```bash
./vendor/bin/sail artisan search:reindex                 # everything (fresh)
./vendor/bin/sail artisan search:reindex --keep          # upsert only
./vendor/bin/sail artisan search:reindex --type=post --locale=es
./vendor/bin/sail artisan search:reindex --stats         # coverage report
```

The targeted `--type` command upserts the selected source/locale; it does not purge obsolete contexts. Use the full command without `--keep` to replace the complete derived index. Internal lifecycle handlers request `indexSource(..., replace: true)` when replacing one source.

`search:reindex` is idempotent, so it is safe to schedule. Freshness is
event-driven as well: saving or deleting an entity (or any of its translations)
reindexes **only that entity** (`SearchIndexer::indexEntity()`), which is what the
module providers hook.

Pages index authored text from active published sections, including their translated
content; draft sections are excluded. Galleries index the translated album and
its existing image labels/captions. Page/Gallery observers run after commit, so
approval, rollback and relation edits are projected from final live data. Reactivating
or reinstalling a module rebuilds its registered sources. Changing a market or
language through the CMS resource service rebuilds contexts after commit without
creating translations.

Scheduled pages may have prepared documents in this **internal** index before
their publication deadline. The public engine always applies `published_at <= now`
and context visibility before results, facets and suggestions. Advancing the clock
does not require another write or manual rebuild. Internal index access is not a
public content API.

Incremental replacement is atomic. Source/full rebuilds use local database
transactions and batches of 200 upserts; a failed projection rolls back the index
replacement. Sources must project stored local content and perform no HTTP calls
inside a rebuild. Large rebuilds can hold index locks: schedule them away from
heavy publishing activity and measure with the site's real dataset. `--keep`
preserves existing rows and upserts current documents, while a fresh rebuild removes
stale rows. A failure still rolls back that rebuild.

Bulk imports can use `SearchIndexer::batch($operation)`: repeated saves queue each
type/entity once, then project its final state. The showcase seeder uses this
mechanism while preserving normal audit/model events. If the operation fails, its
original exception is preserved and pending projections are discarded; use normal
domain transactions for the content mutation and rerun indexing after recovery.

---

## 3. Adding a searchable entity to a module

1. Declare the entity in the module's `EntitySchema` (mark it `searchable`,
   `searchPrefix` = the localization key of the module prefix, `searchWeight`).
2. Write a source — usually by extending `AbstractSearchSource`:

```php
final class ProductSearchSource extends AbstractSearchSource
{
    public function __construct(private readonly LocalizationServiceInterface $localization) {}

    public function type(): string { return 'product'; }
    public function label(string $locale): string { return __('Producto'); }
    public function weight(): float { return 1.1; }

    protected function query(): Builder
    {
        return Product::query()->where('is_active', true)->with('translations');
    }

    protected function translations(Model $entity): iterable
    {
        return $entity->getRelation('translations');
    }

    protected function document(Model $entity, Model $translation): ?SearchDocument
    {
        return new SearchDocument(
            type: $this->type(),
            entityId: (string) $entity->getKey(),
            locale: (string) $translation->getAttribute('locale'),
            url: $this->localization->frontUrl('shop', (string) $translation->getAttribute('slug'), $translation->getAttribute('locale')),
            title: (string) $translation->getAttribute('name'),
            excerpt: (string) ($translation->getAttribute('summary') ?? ''),
            body: strip_tags((string) $translation->getAttribute('description')),
            weight: $this->weight(),
        );
    }
}
```

3. Register it in the module provider's `boot()`:

```php
if ($this->app->bound(SearchSourceRegistryInterface::class)) {
    $this->app->make(SearchSourceRegistryInterface::class)->register(
        $this->app->make(ProductSearchSource::class)
    );
}
```

4. Hook the model events (`saved`/`deleted` on the entity and its translations)
   and you are done: the entity appears in results, facets, suggestions and the
   WebMCP tool with no change to the search module.

---

## 4. `modules/Search` (the front office)

| Piece | Where |
| :--- | :--- |
| Routes (`hub`, `results`, typed results) | `SearchModuleServiceProvider::registerPageableRoutes()` |
| Controller | `Http\Controllers\SearchFrontController` |
| Domain presentation | `Contracts\SearchFrontPageServiceInterface` → `Services\SearchFrontPageService` |
| Transport input / result | readonly `DTOs\SearchFrontInput` / shared `App\DTOs\Front\FrontPageData` |
| Settings reader | `Services\SearchSettings` |
| Analytics | `Services\SearchAnalyticsService` (+ `search_terms` table, `Models\SearchTerm`) |
| Views (theme-overridable) | `Resources/views/index.blade.php`, `results.blade.php` |
| Admin activation | `Routes/admin.php`, `Http\Controllers\Admin\SearchSettingsPostController` |

Settings live in `module.json` and are editable both in the module settings screen
and in the inbox card: `is_indexable`, `results_per_page`,
`min_results_to_index`, `track_terms`, and the localizable prefixes
(`buscar` / `search` / `cercar` / `recherche` / `suche`).

### Indexability policy (same quality gate as filters)

| Page | Robots |
| :--- | :--- |
| Hub, module not activated | `noindex, follow` |
| Hub, activated | `index, follow` (and it enters the sitemap + llms.txt) |
| Results, activated, page 1, ≥ `min_results_to_index` results | `index, follow` |
| Results, not activated / thin / page ≥ 2 | `noindex, follow` |

Result pages emit `SearchResultsPage` + `ItemList` JSON-LD, plus OpenGraph,
Twitter cards, canonical and hreflang through the shared `seo-head` partial.
The graph URL includes the selected entity facet and matches its canonical URL.
The hub's coverage counts only indexed entities currently public in its own
market/language, rather than global private or scheduled rows.

### Recorded search analytics

Every search records the normalized term with its hit counter and whether it
found anything (`search_terms`: `term`, `locale`, `market_code`, `hits`,
`results`, `zero_results`, `last_seen_at`). It is throttled per term so a refresh
does not inflate the counters, and it powers the inbox's two rankings:

- **Términos más buscados** — what to index, feature or link;
- **Búsquedas sin resultados** — the honest content/curation to-do list.

Core capability statistics request these counters through
`App\Contracts\Services\SearchAnalyticsInterface`. The core default returns an
empty list; the Search module supplies its own adapter and checks its current
availability before reading stored terms. Disabling Search therefore removes its
analytics contribution without requiring a module model in core CMS actions.
These records do not include a user identifier, but a visitor can put personal
information in a search term; do not describe arbitrary entered text as anonymous.

---

## 5. Injection

| Surface | How |
| :--- | :--- |
| Theme header | `@include('themes.components.search-box', ['variant' => 'dark'\|'light'])` — already wired in `db-cyber` and `default-clean`; the box targets the current market + locale automatically |
| Any composed page | "Buscador general" block (`SearchBoxBlock`, requires the `search` module) |
| Any custom markup | `<x-cms-search.box :variant="'dark'\|'light'" size="md\|lg" />` |

All of them are plain GET forms (`?q=…`) that consolidate with a **301** into the
canonical friendly URL, so they work without JavaScript and never leave a query
string in the address bar. When the module is deactivated the box renders
nothing.

---

## 6. Agent navigation (WebMCP)

| Tool | Purpose |
| :--- | :--- |
| `cms_search` | Runs the general search: ranked results with their canonical URLs, per-entity facets, suggestions when nothing matches, the hub URL and the **index policy** (`index`/`noindex`) of the result page — so an agent never cites a thin page as a source |

```json
{"jsonrpc":"2.0","id":1,"method":"tools/call",
 "params":{"name":"cms_search","arguments":{"query":"arquitectura modular","type":"post","limit":5}}}
```

---

## 7. Tests

| Suite | Covers |
| :--- | :--- |
| `tests/Feature/Search/SearchEngineTest.php` | Matching (title/excerpt/body + order), accents, prefix, AND semantics, locale/type scoping, facets, entity weight, suggestions, pagination, reported pass |
| `tests/Feature/Search/SearchIndexingTest.php` | Registered sources, `search:reindex` (URLs, per locale, freshness), incremental reindex, event-driven sync, single type/locale |
| `tests/Feature/Search/PageGallerySourcesTest.php` | Page/Gallery registration, public block text, market/locale isolation, scheduled visibility without rebuild, one projection per batched entity, incremental edits/deletions, context refresh and rollback after a failing source |
| `tests/Feature/Search/SearchCapabilityAnalyticsContractTest.php` | Neutral core implementation, real scalar counters and locale filtering, disabled-module availability |
| `tests/Architecture/GallerySearchFrontBoundaryTest.php` | Query-free compatibility transports and a maximum of ten lines per public action |
| `tests/Feature/Search/SearchModuleFrontTest.php` | Hub per locale + alias 301, noindex by default, `?q=` consolidation, facets and typed pages, unknown type 404, suggestions, activation with sitemap/llms and back to noindex, thin pages, analytics and inbox card, on-demand reindex, per-page setting |
| `tests/Feature/Mcp/McpSearchToolTest.php` | `cms_search`: manifest parity, ranked results, facets/type filter, index policy, suggestions, required query |

> **Test note**: InnoDB only publishes its FULLTEXT index at commit time, so rows
> written inside a test transaction are not visible to `MATCH()`. The engine test
> suite exercises the contract through the literal pass (which is what answers in
> that situation) and the full-text pass is verified against committed data in the
> development environment.
