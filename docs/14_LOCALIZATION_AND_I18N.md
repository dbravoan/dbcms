# Localization & i18n (`docs/14_LOCALIZATION_AND_I18N.md`)

All `/admin/...` URL examples below use the default prefix; replace it with the
installation’s configured `ADMIN_PATH`. Filesystem paths named `admin` are unrelated.

How the backoffice and the public application handle languages (ES / EN).

## 1. Two independent language contexts

| Context | Driver | Source of truth |
| :--- | :--- | :--- |
| **Public application** | Market + locale routing (`/{market}/{locale}/...`) | `ResolveMarketAndLocaleMiddleware` → `App::setLocale()` |
| **Backoffice (admin UI)** | Per-user preference | `SetBackofficeLocale` → `BackofficeLocaleService` |

The backoffice language is **never** derived from the visitor market. The same
admin always sees the panel in the language they chose, regardless of the market
they are browsing.

### Content languages vs. panel language (reactivity rule)

Two different things are deliberately kept apart:

| Concern | Source | Reacts to a new language in the backoffice? |
| :--- | :--- | :--- |
| **Content languages** (tabs, slugs, menus, SEO, forms, markets) | The `languages` table, through `LocalizationService` | **Yes** — activate `fr` and assign it to the relevant markets; existing content is not translated automatically |
| **Backoffice UI language** (the top-bar switcher) | `CMS_ADMIN_LOCALES` + `lang/{code}` files | Configure `CMS_ADMIN_LOCALES` and supply panel translations (`lang/fr/`); availability itself does not inspect those files |

The single reactive entry point is `App\Services\Cms\LocalizationService`:

| Method | Returns |
| :--- | :--- |
| `activeLanguages()` | Every active `Language`, default first (per-request memo) |
| `activeLocales()` | The active locale codes, default first |
| `defaultLocale()` | The default public language code |
| `activeMarkets()` / `defaultMarket()` | Every active `Market` (same contract) |
| `forgetCache()` | Invalidates the memo; `Language`/`Market` call it on save/delete |

**No feature may hardcode `es`/`en`/`ca`.** Anything language- or
market-dependent goes through this registry (or the `activeLanguages` /
`activeMarkets` variables shared by `AdminLayoutComposer`), so the backoffice is
reactive by construction.

### Localized module settings

Module settings that are per language declare the placeholder `slug_{locale}`
in `modules/*/module.json`; `ModuleSettingsService::getSchema()` expands it into
one concrete field per active language (`slug_es`, `slug_fr`, …) with
`scope: locale`. Because the expansion happens in the single access point, the
settings screen, the PUT validation, `setMany()`, `get()` and the MCP surface all
inherit it. The UI renders them as language tabs (`setting-field` partial), one
save for every language. `{market}` works the same way for per-market settings.

## 2. Backoffice language resolution

`App\Services\Cms\BackofficeLocaleService::resolve()` uses this precedence:

1. `auth()->user()->locale` (per-user preference).
2. `session('backoffice_locale')` (also for an authenticated user without a valid preference).
3. `config('cms.default_admin_locale')` (default `es`).

`config/cms.php`:

```php
'admin_locales' => array_values(array_filter(array_map('trim', explode(',', (string) env('CMS_ADMIN_LOCALES', 'es,en'))))),
'default_admin_locale' => env('CMS_DEFAULT_ADMIN_LOCALE', 'es'),
```

- `users.locale` (nullable string 10) lives in the **base** users migration.
- `App\Http\Middleware\SetBackofficeLocale` runs on the whole `admin/*` prefix
  group (including login) and overrides the application locale.

### Switching

| Route | Name | Use |
| :--- | :--- | :--- |
| `POST /admin/locale` | `admin.locale.update` | Authenticated switcher (persists to the user) |
| `GET /admin/locale/{locale}` | `admin.locale.switch` | Quick switch / guests on the login screen |

`<x-admin.locale-switcher />` renders the selector and is included in both admin
layouts (`dbcms`, `termocms`) and both login screens. It reads
`$currentAdminLocale` / `$availableAdminLocales`, shared by `AdminLayoutComposer`.

## 3. Translation files

- **Backoffice**: structured PHP files under `lang/{es,en}/` (e.g.
  `lang/es/admin.php`, `lang/en/admin.php`). Keys are nested and namespaced:
  `admin.actions.save`, `admin.nav.items.pages`, `admin.table.empty_title`, …
- **Public app**: JSON files `lang/{es,en,ca}.json` (source-string keys).

Rules:

- Keep `es` and `en` in **perfect key parity** (enforced by
  `tests/Architecture/TranslationParityTest.php`).
- Never hardcode user-facing strings in backoffice views/components; wrap them
  with `__('admin.…')`.
- Inside Alpine/JS strings use `@js(__('admin.…'))`.

## 4. Adoption status

**Surfaces using translation keys**

This is an implementation inventory, not a guarantee that every string in every
locale has been translated or linguistically reviewed. `/admin` examples use the
default `ADMIN_PATH`; use the configured prefix on an installed site.

- Infrastructure: user preference, middleware, service, switcher, routes, config.
- `AdminNavigationService` (all section titles, item labels and create tooltips).
- Both admin layouts and login screens (chrome, alerts, notifications, footer).
- Dashboard (both `dbcms` and `termocms` variants).
- Core screens: markets/languages, modules, general settings, users, tokens
  (index + create), menus, seo/audit.
- Admin UI Kit: `datatable-header`, `datatable-pagination`, `table`,
  `empty-state`, `modal`, `delete-modal`, `confirm-modal`, `form-header`,
  `form-actions`, `search-select`, `market-picker`, `locale-tabs`,
  `status-badge`, `seo-meta-box`, `media-field`, `multiselect`,
  `role-multiselect`, `entity-multiselect`, `onboarding-tutorial`,
  `filemanager-modal`, `wysiwyg`. The former `icon-picker` was removed; flags use
  `emoji-picker`.

- Core entity forms and listings: Pages, Blog (posts + categories), Shop
  (products + categories + orders), Galleries, Forms (index/submissions/builder/create/edit).
- Module admin screens: ContentAudit, Maintenance, Mcp, AiAssistant.
- Front-office admin bar (SEO/GEO score, tips, tooltips).
- `make:cms-module` templates use the shared `admin.*` keys for generic labels.
- Login screens (base + both themes): form labels, tagline and the dev-only
  demo-roles/credentials block.
- Page `<title>` tags for every backoffice screen via `admin.page_titles.*`.

> **Module routes carry the backoffice locale.** `BaseModuleServiceProvider`
> registers module admin routes with `SetBackofficeLocale`, so module screens
> (Blog, Shop, Gallery, Forms…) follow the per-user admin language
> exactly like core screens. Without it, module routes fell back to the public
> market locale.

**Pending (minor / dev-only)**

- Demo content only: placeholder author names and sample postal addresses in
  some theme checkout footers (not UI copy).
- Any newly generated module's module-specific labels (developer-customizable).

### 4.1 Front-office (public themes)

Public templates use **JSON translation keys** (`lang/{es,en,ca}.json`) via
`__('…')`. The Spanish source string is the key, so `es` needs no extra entry
(Laravel returns the key when a JSON entry is missing); `en` and `ca` provide the
translations. All three JSON files are kept in key parity by
`tests/Feature/Localization/FrontOfficeLocalizationTest.php`.

Translated surfaces:

- Header/footer chrome across the distributed public themes (nav fallbacks, cart title,
  market/language switcher, footer legal links).
- Shop: index/show/cart/checkout/order-success + `widget_products` block.
- Blog: index/category/show.
- Gallery: index/show.
- Pages: empty-state.
- Module fallback views (`modules/{Blog,Shop,Gallery}/Resources/views`) and core
  Pages views (`app/Content/Pages/Resources/views`).
- Page `<title>` and `meta_description` for shop/blog/gallery.
- Shared components (`cookie-consent-modal`, `visitor-geo-modal`) were already
  localized through the `$currentLocale` match pattern.

## 5. Verification

- `tests/Feature/Localization/BackofficeLocalizationTest.php`: default locale,
  per-user persistence, invalid-locale fallback, guest switch.
- `tests/Architecture/TranslationParityTest.php`: `es`/`en` key parity (PHP + JSON),
  navigation uses keys, switcher present in both themes.
- Standard gates: `pest`, PHPStan Level 8, Pint, `view:cache`.

## 6. Flags, emoji & locale lifecycle

Markets and languages carry an **emoji** (a flag, a supranational flag or the
globe `🌐`) assigned from the backoffice (*Markets & Locales*). It is rendered
wherever a locale is listed (admin chips, tabs, block manager, front switcher).

- Storage: `markets.flag` and `languages.flag` (nullable, 16 chars).
- Component: `<x-admin.emoji-picker name="flag" :value="…" />` — searchable grid
  (countries + `🌐` + supranational groups) plus a free emoji input. Used by the
  market create/edit and language create/edit forms.
- Fallbacks (`App\Core\Localization\FlagCatalog`):
  - markets → the country flag derived from the ISO code (`fr` → 🇫🇷);
  - languages → an explicit language map first (`en` → 🇬🇧, `ca` → 🏴), then the
    country derivation, then `🌐`. Language codes are **not** country codes, so
    they never rely on the derivation alone.
- The front switcher reads the flags from the database
  (`FrontendLayoutComposer::flags()`, cached and version-invalidated with the
  page cache) instead of a hardcoded map.

### Lifecycle rules (enable / disable / delete)

| Action | Rule |
| :--- | :--- |
| Toggle market | The **primary** market cannot be disabled. |
| Toggle language | The **primary** language cannot be disabled. |
| Delete market | Blocked for the primary market and for any market still referenced by content (`App\Core\Localization\LocalizationUsage` inspects every table with a `market_id` column; several cascade on delete). |
| Delete language | Blocked for the primary language and for any language used as a market `default_locale`; otherwise it is detached from every market and removed. |

Deletions are confirmed through the shared `<x-admin.delete-modal />`
(`$dispatch('confirm-delete', …)`).

## 7. Public content readers and alternate URLs

Blog and Pages receive a readonly `PublicContentContext` with the resolved market,
locale, path segments, query filters and page. The HTTP adapter constructs it;
module readers do not create requests or response objects. They apply publication,
market and translation visibility before composing metadata.

`LocalizationServiceInterface::buildAlternateUrlsMapForPath()` accepts path
segments and optional localized slugs directly. The older request-based helper
delegates to it. A translated hierarchical slug replaces the complete content
path, preserving the localized module prefix; it does not append the parent twice.
Filter alternates continue using the module filter contracts. See
[public filtering](20_FRONT_FILTERING_AND_SEO_URLS.md).
