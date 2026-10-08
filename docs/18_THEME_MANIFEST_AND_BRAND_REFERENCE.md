# 18 · Theme Manifest & Brand Reference

> **Scope**: the shared contract for **backoffice** and **front-office** themes — where identity
> lives, how it is resolved, and how to extend it without hardcoding.
> **Related**: [`16_BACKOFFICE_THEME_GUIDE.md`](16_BACKOFFICE_THEME_GUIDE.md) ·
> [`17_FRONTOFFICE_THEME_GUIDE.md`](17_FRONTOFFICE_THEME_GUIDE.md) ·
> [`13_ADMIN_UI_FRAMEWORK.md`](13_ADMIN_UI_FRAMEWORK.md)

---

## 1. The golden rule

| Concern | Lives in | Never in |
| :--- | :--- | :--- |
| **Brand identity** (name, tagline, logo, icon, edition, accent, tone) | The **backoffice theme manifest** `resources/views/themes/admin/{id}/theme.json` → `brand` | config, views, controllers |
| **Runtime / deployment config** (which theme, site name, flags, integrations) | `.env` → `config/*.php` | theme manifests |
| **Front-office presentation** | `themes/{id}/**` | backoffice files |

> Config files are **brand-agnostic**: they must never contain a brand literal nor branch on a
> theme identifier. This is enforced by `tests/Architecture/BrandIsolationTest.php`.

---

## 2. Environment variables (`config/cms.php`)

| Variable | Default | Purpose |
| :--- | :--- | :--- |
| `CMS_BRAND` | `dbcms` | **Backoffice theme id** — must match `resources/views/themes/admin/{id}`. |
| `CMS_THEME` | `db-cyber` | Front-office theme id — must match `themes/{id}`. |
| `APP_NAME` | `CMS` | Site/product name (SEO, OpenGraph, legal surfaces). |
| `CMS_TAGLINE` | *(empty)* | Global tagline override. Empty → use the theme's `brand.tagline`. |
| `CMS_CORE_MODULES` | `pages` | Comma-separated modules that can never be uninstalled. |
| `CMS_ADMIN_LOCALES` | `es,en` | Available backoffice locales. |

`config('cms.admin_theme')` is the **single canonical key** for the active backoffice theme.
There is no `config('cms.brand')` anymore.

---

## 3. Backoffice theme manifest

```jsonc
// resources/views/themes/admin/{id}/theme.json
{
    "name": "Acme Backoffice",        // human title of the theme
    "identifier": "acme",             // must equal the directory name
    "version": "1.0.0",
    "description": "Light corporate backoffice.",
    "author": "Acme Design Team",
    "brand": {
        "name": "Acme",               // brand name shown in UI/logo (≠ APP_NAME)
        "tagline": "Solutions that scale",
        "logo": "acme-logo.png",      // file under assets/ (nullable)
        "icon": "acme-favicon.png",   // file under assets/ (nullable)
        "edition": "Corporate",       // optional badge (nullable → hidden)
        "accent": "#0f766e",          // brand accent (#RGB / #RRGGBB)
        "mode": "light"               // "light" | "dark"
    }
}
```

All keys are optional; missing `brand` values fall back to sensible defaults.

### How brand values are resolved

```
env override (CMS_TAGLINE)  →  theme.json brand.*  →  generic default
```

`App\Services\Cms\BrandService` (bound to `BrandServiceInterface`) is the **only** accessor:

```php
$brand = app(BrandServiceInterface::class)->current(); // App\DTOs\BrandIdentity
$brand->name; $brand->tagline; $brand->logo; $brand->edition; $brand->accent; $brand->mode;
brand()->name; // helper shortcut
```

`$cmsBrand` (a `BrandIdentity`) is shared with **every view** by a single global view composer.

### Active theme validation

If `CMS_BRAND` points to a directory that does not exist, the service logs a warning and falls
back to `dbcms`; if even that is missing it returns a neutral identity (base views). It never
throws.

---

## 4. Front-office theme manifest

```jsonc
// themes/{id}/theme.json
{
    "name": "Acme Clean",
    "identifier": "acme-clean",
    "version": "1.0.0",
    "description": "Minimal public theme.",
    "author": "Acme Design Team"
}
```

Front-office manifests declare **no `brand`**: the brand belongs to the backoffice. The public
layout receives the active backoffice theme via the global `$adminTheme` view variable and is
expected to expose it as a CSS class on `<html>`:

```blade
<html class="theme-{{ $adminTheme }}">   <!-- e.g. theme-termocms -->
```

This class lets shared chrome (the frontoffice admin bar, the embedded section editor) adopt the
backoffice palette. A complete front theme must ship **16 files**:

```
themes/{id}/
├── theme.json
├── layouts/app.blade.php            # theme-{adminTheme}, @yield('content'), @stack('scripts'),
│                                    # @stack('admin_scripts') + shared partials (see §6)
├── partials/header.blade.php
├── partials/footer.blade.php
├── pages/show.blade.php
├── blog/{index,category,show}.blade.php
├── gallery/{index,show}.blade.php
├── shop/{index,show,cart,checkout,order_success}.blade.php
└── shop/widgets/featured_products.blade.php
```

Coverage is enforced dynamically by `ThemeSystemIntegrityTest` and `ThemeParityAndIntegrityTest`
(they discover themes from disk, so a new theme is covered automatically).

---

## 5. Styling the frontoffice admin bar (theme authors)

The admin bar chrome is driven **entirely** by `--admin-bar-*` CSS tokens. The component ships
dbCMS defaults on `:root`; a backoffice theme can override them by adding
`resources/views/themes/admin/{id}/assets/admin-bar.css`:

```css
html.theme-acme {
    --admin-bar-bg: rgba(255, 255, 255, 0.96);
    --admin-bar-border: #E2E8F0;
    --admin-bar-text: #0F172A;
    --admin-bar-muted: #64748B;
    --admin-bar-accent: #0f766e;
    --admin-bar-accent-contrast: #FFFFFF;
    --admin-bar-chip-bg: rgba(15, 118, 110, 0.10);
    --admin-bar-chip-border: rgba(15, 118, 110, 0.30);
    --admin-bar-font: 'Plus Jakarta Sans', system-ui, sans-serif;
    --admin-bar-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
}
```

Full token list: `--admin-bar-bg`, `--admin-bar-panel`, `--admin-bar-panel-alt`,
`--admin-bar-border`, `--admin-bar-border-soft`, `--admin-bar-border-strong`,
`--admin-bar-text`, `--admin-bar-muted`, `--admin-bar-accent`, `--admin-bar-accent-hover`,
`--admin-bar-accent-contrast`, `--admin-bar-chip-bg`, `--admin-bar-chip-border`,
`--admin-bar-hover-bg`, `--admin-bar-glow`, `--admin-bar-pill-bg`, `--admin-bar-pill-text`,
`--admin-bar-pill-border`, `--admin-bar-pill-hover-bg`, `--admin-bar-pill-hover-text`,
`--admin-bar-font`, `--admin-bar-shadow`.

**Do not** patch individual selectors with `!important`; declare tokens instead so every element
(surface, drawer, quick-edit, collapsed trigger) stays in sync.

---

## 6. Shared front-office partials

Every front layout must include:

```blade
@include('themes.components.seo-head')
@include('themes.components.visitor-geo-modal')
@include('themes.components.cookie-consent-modal')
@include('components.frontoffice-admin-bar')
@include('themes.components.scroll-magic')
```

---

## 7. Installing / switching

- **Web installer** (step 4) and **`cms:install --brand= --theme=`** select and persist the theme.
  When only **one** brand (or front theme) exists on disk, the selector is hidden and that value is
  auto-selected.
- Switch the front theme from the backoffice **Themes** page (writes `CMS_THEME`), or edit `.env`.
- Switch the backoffice theme by setting `CMS_BRAND` (the brand identity travels with the theme).

---

## 8. Checklist for a new brand/theme

- [ ] Backoffice theme under `resources/views/themes/admin/{id}` with a `brand` block.
- [ ] No brand literal or theme `if` added to `config/*.php`.
- [ ] `assets/admin-bar.css` added if the palette differs from dbCMS.
- [ ] Brand read via `brand()` / `$cmsBrand`, never from a hardcoded string.
- [ ] `./bin/sail-run php vendor/bin/pest` (incl. `BrandIsolationTest`) and `view:cache` pass.
