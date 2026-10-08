# 17 · Front-office Theme Guide

> **Scope**: `themes/{id}` — the public (front-office) website skin.
> **Related**: [`14_LOCALIZATION_AND_I18N.md`](14_LOCALIZATION_AND_I18N.md) ·
> [`16_BACKOFFICE_THEME_GUIDE.md`](16_BACKOFFICE_THEME_GUIDE.md) ·
> [`18_THEME_MANIFEST_AND_BRAND_REFERENCE.md`](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md)
>
> A front theme declares **no brand**: the brand identity belongs to the active backoffice theme
> manifest and is injected into the layout as `$adminTheme` (see [`18`](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md)).

---

## 1. How front-office themes work

- Themes live in **`themes/{identifier}`** (project root).
- `App\Services\Cms\ThemeManagerService` discovers them by directory and reads `theme.json`.
- The active theme is `config('cms.theme')` (env `CMS_THEME`, default `db-cyber`), switchable from
  the backoffice **Themes** page (`admin.themes.index`).
- Views resolve as **`themes.{id}.{module}.{view}`** (e.g. `themes.db-cyber.blog.show`). If a theme
  lacks a view, the module fallback is used (`pages::show`, `blog::show`, `shop::show`,
  `gallery::show`, …).
- Themes are **independent and self-contained**: a theme must never reference another theme's views.
- A **complete** theme ships 16 files (layout, partials, pages/blog/gallery/shop views and the
  `theme.json`); coverage is enforced dynamically by `ThemeSystemIntegrityTest` /
  `ThemeParityAndIntegrityTest`, which discover themes from disk.

---

## 2. File structure (16 required files)

```
themes/{id}/
├── theme.json
├── filter.json                       # filter-panel skin tokens (see §8.1)
├── layouts/app.blade.php
├── partials/
│   ├── header.blade.php
│   └── footer.blade.php
├── pages/show.blade.php
├── blog/
│   ├── index.blade.php
│   ├── category.blade.php
│   └── show.blade.php
├── gallery/
│   ├── index.blade.php
│   └── show.blade.php
└── shop/
    ├── index.blade.php
    ├── show.blade.php
    ├── cart.blade.php
    ├── checkout.blade.php
    ├── order_success.blade.php
    └── widgets/featured_products.blade.php
```

`tests/Feature/Cms/ThemeSystemIntegrityTest.php` enforces this list.

---

## 3. `theme.json` schema

```json
{
    "name": "dbCMS Cyber Theme (>_db)",
    "identifier": "db-cyber",
    "version": "1.0.0",
    "description": "Cyber-terminal aesthetic with neon accents.",
    "author": "dbCMS Design Team"
}
```

---

## 4. Layout requirements

`layouts/app.blade.php` **must** provide:

| Hook | Why |
| :--- | :--- |
| `@yield('content')` | Page body. |
| `@stack('scripts')` | Scripts pushed by blocks/widgets. |
| `@stack('admin_scripts')` | Scripts required by the embedded section editor (search-select, block manager). |
| `@include('components.frontoffice-admin-bar')` | Admin bar + SEO/GEO drawer + section editor. |
| `@include('themes.components.cookie-consent-modal')` | GDPR banner. |
| `@include('themes.components.visitor-geo-modal')` | First-visit geo/locale modal. |
| `@include('themes.components.scroll-magic')` | Scroll animations / video scrub engine. |
| `@include('themes.components.seo-head')` | Meta/OG/JSON-LD. |
| `class="theme-{{ $adminTheme }}"` on `<html>` | Adapts the admin bar to the active backoffice theme. |

The `FrontendLayoutComposer` shares the chrome variables: `$localization`, `$currentMarket`,
`$currentLocale`, `$marketCode`, `$headerMenu`, `$footerQuick`, `$footerLegal`, `$hasMarkets`,
`$hasLangs`, `$galPrefix`, `$flags`, `$cmsBrandName`, `$brandDescription`, `$rightsReserved`,
`$adminTheme`, ….

---

## 5. View data per module

| View | Variables |
| :--- | :--- |
| `pages/show` | `$page`, `$translation`, `$renderedSections`, `$hreflangs`, `$alternateUrls`, `$breadcrumbs`, `$currentMarket` |
| `blog/index` | `$posts` (paginator), `$categories` |
| `blog/category` | `$category`, `$posts`, `$categories` |
| `blog/show` | `$post`, `$translation`, `$renderedSections` |
| `gallery/index` | `$galleries` (paginator), `$breadcrumbs`, `$alternateUrls` |
| `gallery/show` | `$gallery`, `$translation`, `$items`, `$breadcrumbs`, `$hreflangs`, `$alternateUrls` |
| `shop/index` | `$products` (paginator), `$categories` |
| `shop/show` | `$product`, `$translation`, `$renderedSections`, `$relatedProducts`, `$isShop`, `$showPrices` |
| `shop/cart`, `shop/checkout` | `$items` |
| `shop/order_success` | `$order` |

---

## 6. Localized slugs (i18n)

- **Module prefixes** are localized (`LocalizationService::$modulePrefixes` + module settings
  `slug_default` / `slug_es` / `slug_en` / `slug_ca`). Blog, Shop and Gallery all expose these
  settings and build their route patterns from `getAllModuleSlugs($module)`.
- **Item slugs** are stored per locale in `{entity}_translations.slug` (posts, products, galleries).
- Always build URLs with the localized prefix:

```blade
<a href="{{ $localization->url('/'.$galPrefix.'/'.$trans?->slug, $currentMarket) }}">…</a>
```

- UI copy uses JSON translation keys: `__('Spanish source string')`, with `lang/{es,en,ca}.json`
  kept in key parity. See [`14_LOCALIZATION_AND_I18N.md`](14_LOCALIZATION_AND_I18N.md).

---

## 7. Widgets

Theme widget overrides resolve as **`themes.{id}.{module}.widgets.{view}`**, e.g.
`themes/db-cyber/shop/widgets/featured_products.blade.php`. The legacy
`themes/{id}/widgets/{module}/{view}` path is still supported, then the module default
`{module}::widgets.{view}`.

---

## 8. Shared components

Global front components live in **`resources/views/themes/components/`**:
`seo-head`, `cookie-consent-modal`, `visitor-geo-modal`, `scroll-magic`,
`search-box` (the compact general-search form for headers).

The admin bar lives in `resources/views/components/frontoffice-admin-bar.blade.php`.
Front sections deep-link to the backoffice editor (see
[`15_FRONT_SECTION_EDITING.md`](15_FRONT_SECTION_EDITING.md)).

---

## 8.1 Filter panel (facet navigation)

Filters, facets and sorts are rendered by **one** shared component so every theme
gets the same accessible, canonical, no-JS-first behaviour:

```blade
{{-- blog/index (and shop/index for the shop dimensions) --}}
<x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />
```

The controller of every listing (blog index/category, shop index) already passes
`filterModule`, `filterSet` and `currentMarket`, so the panel renders wherever you
include it — and renders nothing when there is no filterable module (safe to
include unconditionally). It emits canonical facet URLs, so the theme never builds
filter links by hand.

### Dressing it: `filter.json`

The look is theme-owned. Ship `themes/{id}/filter.json` with the tokens your
aesthetic needs; anything you omit falls back to the neutral defaults in
`config/front_filter_ui.php`:

```json
{
  "tokens": {
    "icon": "check",
    "columns": 3,
    "panel": "border-2 border-black bg-white shadow-[6px_6px_0_0_#000]",
    "legend": "text-[11px] font-black uppercase",
    "legendPrefix": "#",
    "option": "border-2 border-black bg-white px-2.5 py-1.5 shadow-[3px_3px_0_0_#000]",
    "optionActive": "border-2 border-black bg-[#00F0FF] shadow-none translate-x-[2px] translate-y-[2px]",
    "chip": "border-2 border-black bg-[#FF5CA8] text-white",
    "field": "border-2 border-black bg-white focus:shadow-[3px_3px_0_0_#000]",
    "submit": "border-2 border-black bg-black text-[#FFE600] shadow-[4px_4px_0_0_#FFE600]"
  }
}
```

Behaviour keys: `icon` (`check`/`dot`/`plus`/`none`), `columns` (1-4),
`collapse` (mobile folding), `maxVisible` (options before "Ver todas").
Visual keys cover `panel`, `accent`, `summary*`, `bodyWrap`, `groupsWrap`,
`group`, `legend`, `legendPrefix`, `options`, `option`, `optionActive`, `box`,
`boxCheck`, `radio`, `radioDot`, `moreSummary`, `chipsWrap`, `chip`,
`chipRemove`, `clearAll`, `field`, `numberRow`, `numberInput`, `dash`, `unit`,
`switchRow`, `switchInput`, `submitWrap`, `submit`, `submitIcon`.

Two rules to remember:

1. **Tailwind must scan your tokens.** The skins live outside Blade, so
   `resources/css/app.css` declares `@source "../../themes/*/filter.json"`. If you
   add a new token *file* or move the tokens elsewhere, add its source too —
   otherwise arbitrary utilities (`shadow-[3px_3px_0_0_#000]`) silently never
   compile (`npm run build`).
2. **No inline handlers.** The CSP blocks `onchange=`/`onclick=`; use the
   `data-auto-submit`, `data-dispatch` and `data-dismiss` attributes handled in
   `resources/js/app.js`.

For a fundamentally different composition (a sidebar, a horizontal rail…) copy
`resources/views/components/cms-filter/panel.blade.php` into
`themes/{id}/filter/panel.blade.php`: it wins over the shared implementation and
receives `$panel` (action, chips, groups, hidden state, counts), `$ui` (your
tokens) and `$theme`.

---

## 9. Generating a new theme

```bash
./bin/sail-run php artisan make:front-theme acme
```

Creates a complete, renderable skeleton (16 files) under `themes/acme`. Activate it from the
backoffice **Themes** page or with `CMS_THEME=acme`.

---

## 10. Checklist

- [ ] All 16 files present + `filter.json` with the panel skin.
- [ ] Layout exposes the 4 hooks + includes the 5 shared components + `theme-{{ $adminTheme }}`.
- [ ] No references to other themes (`themes.db-<other>`).
- [ ] Uses `$localization->url()` with the localized prefix for internal links.
- [ ] Renders in `es`, `en` and `ca` (home + module views).
- [ ] `theme.json` present with `name`/`identifier`.
- [ ] The filter panel is included in `blog/index`, `blog/category` and `shop/index`.
- [ ] The panel renders in the theme's own look (`data-filter-panel="{id}"`) and its tokens are in the compiled CSS (`npm run build`).
- [ ] `./bin/sail-run php vendor/bin/pest` and `view:cache` pass.
