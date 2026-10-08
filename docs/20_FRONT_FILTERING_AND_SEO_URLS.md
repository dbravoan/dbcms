# 20 · Front Filtering, Search & SEO-friendly URLs

> **Status**: Implemented (core + Blog + Shop) — URL grammar **v2 (markerless)**
> **Scope**: every paginable / searchable / filterable front-office module
> **Related**: [`22_GENERAL_SEARCH.md`](22_GENERAL_SEARCH.md) · [`05_GEO_AND_SEO_ENGINEERING.md`](05_GEO_AND_SEO_ENGINEERING.md) · [`14_LOCALIZATION_AND_I18N.md`](14_LOCALIZATION_AND_I18N.md) · [`17_FRONTOFFICE_THEME_GUIDE.md`](17_FRONTOFFICE_THEME_GUIDE.md)

---

## 1. The standard (mandatory)

Filters, searches, facets and sorts are **never query strings**. They are
**localized path segments** resolved at runtime, so every combination is a
unique, shareable URL with its own dynamic SEO text and hreflang. Nothing is
statically generated.

Since v2 the canonical URL is **markerless**: the value speaks for itself and
the canonical form is the shortest one that re-parses unambiguously (the builder
validates every path with a round-trip before publishing it).

```
/{market}/{locale}/{module}/{value}+{value}/{reserved}/{range}…
/{market}/{locale}/{module}/{implicitValue}/{value}…          (implicit dimension, bare and hierarchical)
```

Examples:

| URL | Meaning |
| :--- | :--- |
| `/es/es/blog/laravel` | Blog filtered by the `laravel` tag |
| `/es/es/blog/laravel+vue` | …two tags, canonical separator `+` |
| `/es/es/blog/laravel/recientes` | …tag + sort (a reserved enum token) |
| `/es/es/blog/tecnologia/ia-geo/modelos-llm` | implicit category (bare, hierarchical) |
| `/es/es/blog/destacados` | boolean flag (reserved token) |
| `/es/es/tienda/entre-100-200-eur` | price range, natural localized connectors + market currency |
| `/es/es/tienda/desde-100-eur` · `/es/es/tienda/hasta-200-eur` | open range |
| `/es/es/blog/desde-2026-01-01/hasta-2026-06-30` | date range |
| `/es/es/tienda/precio-asc` | sort |
| `/es/es/blog/mi-termino` | search term (fallback, last segment, opt-in per module) |

**Resolution order of a bare segment** (declared, deterministic — never a guess):

1. explicit legacy markers (`etiqueta/laravel`) → parse, then **301** to canonical;
2. reserved tokens (enum options and boolean markers) — they always win;
3. localized shapes: dates (`desde-X`, `hasta-Y`) and ranges (`entre-X-Y-eur`);
4. inline qualifier (`etiqueta-laravel`) — only used when a bare value is ambiguous;
5. term vocabularies (hyphen backtracking, `+` joins values);
6. search fallback (last segment only, when the module declares a `text` dimension).

Unknown values and **genuinely ambiguous** payloads (two valid segmentations)
resolve to `null` → **404**, never a partial or ambiguous result. Connectors,
markers and range units are localized (`config/front_filters.php`), and the range
unit falls back to the **market currency**.

**Legacy compatibility**: v1 marker URLs (`/orden/recientes`, `/precio/100-200`),
compact ranges (`/100-200`), `_` joins and hyphen-joined values still parse; the
canonicalizer answers them with a **301** to the v2 canonical form, so no link
equity is lost.

### Non-canonical module prefixes → 301

Module prefixes are localizable (`/tienda` in Spanish, `/shop` in English,
`/botiga` in Catalan). Any other alias serves the same content, so
`CanonicalModulePrefix` answers it with a permanent redirect to the canonical
prefix of the active locale, preserving the rest of the path and the query
string (`/es/es/shop/precio-asc` → `/es/es/buscar…`-style consolidation). The
backoffice "view live" links use `LocalizationService::frontUrl()` so they never
emit an alias either.

---

## 2. Core (`App\Core\Filters`)

| Class | Responsibility |
| :--- | :--- |
| `FilterDimension` | Declares a dimension: type (`terms`/`text`/`range`/`date`/`enum`/`bool`), localized marker/qualifier, vocabulary, unit, canonical order, `implicit`, `bound` (`from`/`to`), `marketCurrency`, front-office `control()` |
| `FilterSet` | Immutable resolved request + stable `signature()` |
| `FilterPathParser` | Markerless resolution order + vocabulary-aware backtracking → `FilterSet` or `null` |
| `FilterUrlBuilder` | Shortest unambiguous canonical path (round-trip validated) + cross-locale translation (hreflang) |
| `FilterQueryRedirector` | Generic filter **form** endpoint: dimension keys (`tag[]`, `sort`, `price_min`/`price_max`, `date_from`, `search`) → canonical path, so the browser never keeps a query string |
| `FilterFacets` | Facet groups + their declared control, active chips, hidden state and canonical toggle URLs |
| `FilterSeoResolver` | Runtime-composed text + curated override + index/robots policy |
| `Contracts\FilterableModule` | Contract a module implements to be filterable |

`FilterPage` (+ `filter_pages` table) stores admin-curated texts per
`module + market + locale + signature`.

### Front-office controls

Every dimension declares the control that picks it (`FilterDimension::control()`,
derived from its type and overridable):

| Control | Used by | Behaviour |
| :--- | :--- | :--- |
| `checkboxes` / `radios` | terms (multi/single) | crawlable links marked with `aria-pressed`: multi-select accumulates values in the canonical URL, no JavaScript |
| `select` | enum (sort) | real `<select>` inside the GET form, auto-submits through `data-auto-submit` |
| `switch` | bool | real checkbox in the GET form |
| `range` | range | `min`/`max` number inputs + the localized unit |
| `date` | date | native date picker |
| `search` | text | search input (Enter submits, no JavaScript) |

`<x-cms-filter.bar>` is the entry point: it prepares the data (groups, active
chips, hidden state, action, clear URL) and renders the panel. Link-based
selections travel as hidden inputs, so submitting the free-form part never loses
them, and the submission answers **301** to the canonical URL through
`FilterQueryRedirector` — one implementation for every module of the CMS.

UX contract of the panel (identical in every theme):

- **Mobile collapse without JavaScript**: the panel is a `<details>` that is
  expanded by default (so crawlers and no-JS visitors always see the facets) and
  folded on small screens by `app.js`, where a summary row with the active-filter
  count takes over. On desktop it is always expanded.
- **Sticky apply bar** on mobile, so the action is always reachable while
  scrolling a long vocabulary.
- **"Ver todas"** for vocabularies longer than `maxVisible` (default 12).
- **Active filters** as removable chips plus a "Limpiar todo" link.
- **CSP-safe interactivity**: no inline handlers anywhere (`onchange=`/
  `onclick=` are blocked by the Content-Security-Policy). Auto-submitting selects
  and switches use `data-auto-submit`, handled once in `resources/js/app.js`.
- **Accessibility**: `<fieldset>`/`<legend>` per group, `aria-pressed` on toggle
  links, `sr-only` labels for every free-form field, `aria-live` on the active
  count and `aria-busy` on submit.

### Filter skins (one panel, one look per theme)

The panel markup is shared; the **look is theme-owned**, so the widget never
looks pasted on a theme:

1. `themes/{theme}/filter.json` → class tokens merged over the neutral defaults
   in `config/front_filter_ui.php` (behaviour keys: `icon`, `columns`,
   `collapse`, `maxVisible`; visual keys: `panel`, `summary`, `legend`, `option`,
   `optionActive`, `chip`, `field`, `submit`…).
2. `themes/{theme}/filter/panel.blade.php` → full structural override when a
   theme wants a different composition.

`FilterPanelSkin` resolves it and `FrontViewResolver::filterPanel()` picks the
view, so a new theme only ships a `filter.json`. Because the tokens live outside
Blade, `resources/css/app.css` declares them as Tailwind sources
(`@source "../../themes/*/filter.json"`); otherwise an arbitrary token
(`shadow-[3px_3px_0_0_#000]`) would never reach the bundle.



---

## 3. SEO / GEO policy

- **Canonical** = the builder's canonical path. Any other permutation of the
  same filters **301s** to it.
- **Ambiguity, unknown values, over-limit paths and 0 results → 404.** Never
  serve ambiguous or empty indexable pages.
- **Indexability**: only **curated** (`filter_pages`) combinations that also
  pass the quality gate (`min_results`, `max_dimensions`) are `index, follow`.
  Everything else is `noindex, follow` (accessible and shareable, not indexed).
- Texts are **composed at runtime** from the localized vocabulary when not
  curated; a curated row overrides title/heading/description/intro.
- **Limits** (`config/front_filters.limits`): `max_dimensions = 3`,
  `max_values_per_dimension = 3`, `max_path_length = 80`, `min_results = 3`.
- **hreflang**: `FilterUrlBuilder::translatedPath()` translates markers **and**
  term values across locales (vocabulary entries carry a shared `key`).
- Values are lowercase, accent-free, hyphen-joined slugs.

Pagination stays as `?page=` for now (out of scope); it is not part of the
canonical filter path.

---

## 4. Adding filters to a module

1. Implement `App\Core\Filters\Contracts\FilterableModule`:

```php
final class ProductFilters implements FilterableModule
{
    public function filterModuleKey(): string { return 'shop'; }
    public function filterModuleLabel(string $locale): string { return 'Tienda'; }

    public function filterDimensions(): array
    {
        return [
            FilterDimension::terms('category', order: 10, implicit: true, vocabulary: fn ($l) => $this->categories($l)),
            FilterDimension::terms('tag', multi: true, order: 20, vocabulary: fn ($l) => $this->tags($l)),
            FilterDimension::text('search', order: 30),
            FilterDimension::range('price', order: 40, units: ['es' => 'eur']),
            FilterDimension::enum('sort', ['novedades' => ['es' => 'Novedades']], order: 50),
            FilterDimension::date('date_from', order: 60),
            FilterDimension::bool('featured', order: 70),
        ];
    }
}
```

2. Resolve the path in the module front controller (catch-all `{slug}` route):

```php
$set = (new FilterPathParser)->parse('shop', $market->code, $locale, trim($slug, '/'), $filters->filterDimensions());

if ($set !== null) {
    return $this->filtered($request, $localization, $filters, $set);
}
```

3. In `filtered()`: build the canonical path, **301** non-canonical permutations,
apply the `FilterSet` to the query, **404** on 0 results, resolve SEO and render.

**Rule:** front controllers must never read `request('q')`,
`request('category')`, `request('page')`, `request('sort')`… They consume a
`FilterSet` (enforced by `tests/Architecture/FrontFiltersTest.php`).

---

## 5. Tests

- `tests/Unit/Core/Filters/FilterPathParserTest.php` — parsing, ambiguity,
  vocabulary hyphens, limits, canonical round-trip, cross-locale translation.
- `tests/Feature/Blog/BlogFriendlyFiltersTest.php` — 200 noindex, 404 unknown,
  301 non-canonical, index when curated.
- `tests/Feature/Shop/ShopFriendlyFiltersTest.php` — same for Shop.
- `tests/Architecture/FrontFiltersTest.php` — no filter query params in front
  controllers.

---

## 6. Canonical taxonomy rule (implicit dimensions)

A dimension flagged `implicit` (the blog/shop category) owns the **bare leading
segment**:

| URL | Served by | Indexable |
| :--- | :--- | :--- |
| `/es/es/blog/tecnologia/ia-geo/modelos-llm` | the category archive (entity) | yes (taxonomy, sitemap) |
| `/es/es/blog/tecnologia/ia-geo/modelos-llm/orden/recientes` | the filter page | only if curated |
| `/es/es/blog/categoria/modelos-llm` | — | **301** to the bare canonical form |

The sitemap, llms.txt and the facet links all publish the bare hierarchical path,
so a taxonomy page has exactly one URL. Catalogue dimensions (tag, price, sort,
search, dates…) keep their explicit localized markers.

## 7. Crawlable facets

`<x-cms-filter.bar :module="$filterModule" :set="$filterSet" :market="$currentMarket" />`
renders the active filters (removable chips) and the available facet values as
real `<a href>` links to the canonical friendly URL of the modified filter set —
never a query string — so crawlers can traverse the whole filter space. It is
wired into the blog/shop indexes (module views + active theme).

Legacy query links keep working through a **301**: `/blog?category=…`, `/blog?q=…`,
`/tienda?category=…` and `/tienda?q=…` consolidate into the friendly URL.

## 8. hreflang, JSON-LD and GEO surfaces

- **hreflang**: `FilterAlternateUrls` expresses the same filters in every active
  locale of the market, translating markers **and** term values (e.g.
  `orden` → `sort`), plus `x-default` for the market default locale.
- **JSON-LD**: `JsonLdSchemaService::generateCollectionGraph()` emits
  WebPage + CollectionPage + ItemList + BreadcrumbList for filter landings and
  taxonomy archives, so engines (and agents) read them as curated listings.
- **Sitemap**: only curated landings that pass the quality gate are published
  (`scopedFilterPages`), with their own hreflang cluster.
- **llms.txt**: curated landings appear under *Curated Filters & Collections*.

### Indexar en Google (backoffice)

*Filtros y búsquedas* (`admin/seo/filters`, permission `manage:seo`) is where the
editor decides **which filter pages Google may index** and with which text.

- **One click from the site**: on any filter page an admin sees *Indexar en
  Google* in the front-office bar. It opens the screen **pre-filled** — module,
  market, locale, address and the composed title/description — so nothing has to
  be typed and the editor sees exactly what will be stored.
- **From the panel**: the same form accepts the address as it appears on the
  site; the server resolves it with the module's own dimensions and stores the
  canonical path + signature (an invalid or ambiguous address is rejected).
- **Any combination can be indexed** (category, tag, price, sort, search…). When
  the address contains a search or a sort, the screen shows Google's
  recommendation as a warning — informative, never blocking.
- **The intro is composed with the CMS block manager**: the same sections +
  translations shape as pages, posts and products (`SectionSyncService`), so any
  active block type (text, hero, features, gallery, forms…) renders above the
  results with the swap of a single block. `App\Core\Filters\FilterIntroRenderer`
  renders it through the shared `SectionRendererService`.
- Runtime policy: only indexed pages with enough results (≥3) and ≤3 dimensions
  are `index, follow`; the rest stay `noindex, follow`. A page that drops below
  the threshold stops being indexed on its own (and leaves the sitemap/llms.txt).

## 9. Agent navigation (WebMCP / MCP)

The MCP server exposes the filter space so any AI agent can navigate it without
guessing parameters:

| Tool | Purpose |
| :--- | :--- |
| `cms_list_filter_dimensions` | Dimensions per module with localized markers, types and vocabulary. |
| `cms_resolve_filter_url` | Path/URL → canonical URL, hreflang, result count, index policy and SEO text. |
| `cms_browse_content` | Browses blog/shop through a friendly path **or** a filters map, returning items + canonical URL. |

All three reuse the same `FilterPathResolver` / `FilterUrlBuilder` /
`FilterSeoResolver` as the front-office, so an agent's navigation is identical to
a user's (and it always learns the canonical, policy-correct URL).

## 10. Blog and Pages read boundaries

HTTP controllers map input to readonly `PublicContentContext` and map
`FrontPageData` to the existing response. Blog owns catalogue, article and public
reader contracts; Pages owns its public reader. These services compose canonical
redirects, filters, metadata and published blocks without HTTP request/response
classes. Inactive or foreign-market categories do not appear in article
breadcrumbs. Home fallback content must belong to the current market.

The pure `buildAlternateUrlsMapForPath()` localization contract preserves full
translated hierarchical slugs. Blog pagination passes the context page explicitly;
the same filter contracts remain available to MCP. Comment submission has a
separate domain contract: the parent must be approved and belong to the same
public post, and moderation notifications are dispatched after commit.

`PublicContentReadArchitectureTest`, `PublicContentReadContractTest` and
`PublicContentReaderNativeTest` cover these boundaries alongside the existing
Blog and Pages functional suites (31 tests and 148 assertions in the focal
verification on 12 October 2026; PHPStan reported no errors).
