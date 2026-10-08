# 16 · Backoffice Theme Guide

> **Scope**: `resources/views/themes/admin/{id}` — the backoffice (admin panel) skin.
> **Related**: [`18_THEME_MANIFEST_AND_BRAND_REFERENCE.md`](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md)
> · [`13_ADMIN_UI_FRAMEWORK.md`](13_ADMIN_UI_FRAMEWORK.md) · [`17_FRONTOFFICE_THEME_GUIDE.md`](17_FRONTOFFICE_THEME_GUIDE.md)
>
> The **brand identity** (name, tagline, logo, icon, edition, accent, tone) lives in the theme
> manifest and is resolved by `BrandService`. Read the canonical reference in
> [`18`](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md) before customising. Config files must stay
> brand-agnostic (enforced by `tests/Architecture/BrandIsolationTest.php`).

---

## 1. How backoffice themes work

- Themes live in **`resources/views/themes/admin/{identifier}`**.
- `App\Services\Cms\AdminThemeService` **discovers them by directory** (no registration needed).
- The active theme is `config('cms.admin_theme')`, derived from the **single env var `CMS_BRAND`**
  (e.g. `CMS_BRAND=termocms` → `resources/views/themes/admin/termocms`).
- `AdminThemeService::registerThemeViewPaths()` **prepends** the theme directory to the view finder,
  so any `admin.*` view resolves to the theme first and falls back to the base
  `resources/views/admin/*` when the theme does not provide it.
- The active theme also exposes a `theme-{id}` CSS class (e.g. `theme-termocms`) used by the
  front-office admin bar to adapt its chrome.

> **Rule**: the theme directory name **must equal** `CMS_BRAND`.---

## 2. File structure

```
resources/views/themes/admin/{id}/
├── theme.json                     # optional manifest (name/description/author/version)
├── admin/
│   ├── layout.blade.php           # REQUIRED — the shell (head, nav, header, content)
│   ├── dashboard.blade.php        # optional — dashboard override
│   └── auth/login.blade.php       # optional — login override
├── errors/
│   └── layout.blade.php           # optional — error page shell
├── modules/
│   └── {module}/…                 # optional — per-module view overrides
└── assets/
    └── {id}.css                   # optional — theme stylesheet (served via theme_asset)
```

| File | Required | Purpose |
| :--- | :--- | :--- |
| `admin/layout.blade.php` | **Yes** | Wraps every backoffice screen. Must expose `@yield('title')`, `@yield('content')`, `@stack('admin_scripts')` and `@stack('scripts')`, and set the `theme-{id}` class on `<html>`. |
| `theme.json` | No | Manifest; without it the theme name falls back to `ucfirst(id)`. |
| `admin/dashboard.blade.php` | No | Overrides the base dashboard. |
| `admin/auth/login.blade.php` | No | Overrides the login screen. |
| `errors/layout.blade.php` | No | Overrides the shared error shell. |
| `modules/{module}/…` | No | Highest-priority override for a module's views. |
| `assets/*` | No | CSS/logos served by the `theme.asset` route. |

---

## 3. `theme.json` schema

```json
{
    "name": "Acme Backoffice",
    "identifier": "acme",
    "version": "1.0.0",
    "description": "Light corporate backoffice with teal accents.",
    "author": "Acme Design Team",
    "brand": {
        "name": "Acme",
        "tagline": "Solutions that scale",
        "logo": "acme-logo.png",
        "icon": "acme-favicon.png",
        "edition": "Corporate",
        "accent": "#0f766e",
        "mode": "light"
    }
}
```

All keys are optional; `AdminThemeService::getThemeManifest()` fills sensible defaults and
`BrandService` resolves the effective identity (env override → manifest → default).

> Full schema, resolution order and token contract: [`18`](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md).

---

## 4. View resolution & overrides

Resolution order (first match wins):

1. `resources/views/themes/admin/{active}/…` (prepended location)
2. `resources/views/themes/admin/{active}/modules/{module}/…` (module override, when the module
   registers its theme paths)
3. Base `resources/views/admin/…` and module views `{module}::…`

Modules can also ship their own per-theme variants under
`modules/{Module}/Resources/views/themes/{active}/…`, registered automatically by
`BaseModuleServiceProvider`.

**Shared components**: any theme can override the anonymous `admin` components by placing them in
`resources/views/themes/admin/{id}/admin/components/` (registered as the `admin` component path).

---

## 5. Assets

Do **not** copy assets into `public/`. Reference them with the `theme_asset()` helper:

```blade
<link rel="stylesheet" href="{{ theme_asset('acme.css') }}?v={{ config('cms.version') }}">
<img src="{{ theme_asset('logo.png') }}" alt="Acme">
```

`theme_asset($path, $theme = null)` resolves the active theme (or an explicit one) and returns the
URL of the `GET /theme-assets/{theme}/{path}` route, which serves files from the theme's `assets/`
directory with the correct `Content-Type` and blocks path traversal / unknown themes.

---

## 6. Theming the front-office admin bar

The front `<html>` receives `class="theme-{activeAdminTheme}"` (shared globally as `$adminTheme`).
The admin bar chrome is driven entirely by `--admin-bar-*` CSS tokens; a theme opts in by shipping
`assets/admin-bar.css`:

```css
html.theme-acme {
    --admin-bar-bg: rgba(255, 255, 255, 0.96);
    --admin-bar-accent: #0f766e;
    --admin-bar-accent-contrast: #FFFFFF;
    --admin-bar-text: #0f172a;
    --admin-bar-font: 'Plus Jakarta Sans', system-ui, sans-serif;
}
```

> **Do not** patch selectors with `!important`. Declare tokens — the surface, drawer, quick-edit pill
> and collapsed trigger all follow automatically. Token list and details in
> [`18`](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md) §5.

---

## 7. Generating a new theme

```bash
./bin/sail-run php artisan make:admin-theme acme
```

Creates `theme.json`, `admin/layout.blade.php`, `admin/dashboard.blade.php`,
`admin/auth/login.blade.php`, `errors/layout.blade.php` and `assets/acme.css`.

Activate it with `CMS_BRAND=acme` in `.env`.

---

## 8. Checklist

- [ ] Directory name equals `CMS_BRAND`.
- [ ] `admin/layout.blade.php` sets `theme-{id}` on `<html>` and exposes the four hooks.
- [ ] Uses `theme_asset()` for assets (no `public/` copies).
- [ ] `<html lang>` uses `app()->getLocale()`.
- [ ] Brand comes from the manifest via `$cmsBrand` / `brand()` (never hardcoded).
- [ ] `theme.json` present, with a complete `brand` block.
- [ ] `assets/admin-bar.css` declares the `--admin-bar-*` tokens for the theme palette.
- [ ] No brand literal or theme `if` added to `config/*.php`.
- [ ] `./bin/sail-run php vendor/bin/pest` (incl. `BrandIsolationTest`) and `view:cache` pass.
