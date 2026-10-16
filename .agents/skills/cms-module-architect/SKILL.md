---
name: cms-module-architect
description: Architects, scaffolds, implements, and tests robust, decoupled CMS modules following Clean Architecture, multi-market normalization, and theme parity.
---

# CMS Module Architect & Extension Engineer Skill

## Goal
Guide AI coding agents and engineers in designing, scaffolding, implementing, and verifying decoupled CMS modules in `modules/` adhering to Clean Architecture, Zero DB Duplication multi-market conventions, and strict PHPStan Level 8 typing.

---

## 1. Automated Scaffolding
Always initiate module generation using the canonical Artisan command inside Docker Sail:
```bash
./vendor/bin/sail artisan make:cms-module <ModuleName> [--pageable]
```
Example:
```bash
./vendor/bin/sail artisan make:cms-module Events --pageable
```
This scaffolds:
- `modules/Events/module.json`
- `modules/Events/EventsModuleServiceProvider.php`
- `modules/Events/Models/Events.php` & `EventsTranslation.php`
- `modules/Events/Contracts/Services/EventsServiceInterface.php`
- `modules/Events/Services/EventsDomainService.php`
- Invokable controllers in `modules/Events/Http/Controllers/Admin/`
- `modules/Events/Http/Controllers/EventsFrontController.php`
- `modules/Events/Database/Migrations/*_create_events_tables.php`
- `modules/Events/Resources/views/` (front and backoffice)

---

## 2. Core Architectural Invariants

### 2.1. Multi-Market & Multilingual DB Normalization (Zero DB Duplication)
Modules with localized content MUST NOT duplicate records across markets. Instead:
1. **Base Table** (`events`): Contains shared invariant data (`is_active`, `order`, `created_at`).
2. **Translation Table** (`event_translations`): Contains localized text (`title`, `slug`, `content`, `locale`).
   - Unique index: `unique(['event_id', 'locale'])`.
   - Composite lookup index: `index(['locale', 'slug'])`.
3. **Market Pivot Table** (`event_market`): Associates entities with active target markets.
   - Primary key: `primary(['event_id', 'market_id'])`.

### 2.2. Thin Controllers & Fat Domain Services
- **Controllers**: Maximum 10 lines per `__invoke()`. Never run raw queries or direct `Model::create()` in controllers.
- **Domain Services**: Use interfaces at actual substitution or package boundaries. Do not add forwarding interfaces or services without a consumer.
  - Wrap all mutation methods in `DB::transaction()`.
  - Dispatch only events with consumers; keep module events in their owner.

### 2.3. Theme Cascade Resolution
Modules must support theme customizations. Frontoffice controllers resolve the
view name against the active theme and fall back to the module view:

```php
$activeTheme = (string) config('cms.theme', 'db-cyber');
$viewName = view()->exists("themes::{$activeTheme}.{$module}.show")
    ? "themes::{$activeTheme}.{$module}.show"
    : '{$module}::show';

return view($viewName, compact('event'));
```

Resolution order:
1. Active theme override: `themes/{activeTheme}/{module}/{viewName}.blade.php`
2. Module default view: `{module}::{viewName}`
3. Clean theme fallback: `themes/default-clean/{module}/{viewName}.blade.php`

See [`docs/17_FRONTOFFICE_THEME_GUIDE.md`](../../../../docs/17_FRONTOFFICE_THEME_GUIDE.md).

### 2.5. Single-Level Descriptive Invokable Controllers
Backoffice controllers must be invokable classes located in a single flat directory `Http/Controllers/Admin/`:
- `{Entities}GetController.php` (List/Datatable index)
- `{Entity}CreateGetController.php` (Create form view)
- `{Entity}PostController.php` (Store action)
- `{Entity}EditGetController.php` (Edit form view)
- `{Entity}PutController.php` (Update action)
- `{Entity}DeleteController.php` (Destroy action)

Avoid deep action subdirectories. Use clear, self-describing names.

### 2.6. Canonical Backoffice UI Kit (`<x-admin.*>`) and Dual Brand Themes
Every module's admin views MUST strictly consume the canonical Blade UI kit to guarantee identical design, layout, accessibility, and behavior across all modules:
- **Datatable & Listing (`Resources/views/admin/index.blade.php`)**:
  - `<x-admin.datatable-header title="..." subtitle="..." search-placeholder="...">`: Title, search, count, and action buttons.
  - `<x-admin.table :empty="$items->isEmpty()" emptyTitle="..." emptyDescription="..." :emptyActionRoute="...">`: Responsive table with `<x-slot:head>` and `<x-slot:pagination>`.
  - `<x-admin.empty-state>`: Triggered automatically by `<x-admin.table>`.
  - `<x-admin.status-badge>`: Standardized pills for active/published status.
- **Form Views (`create.blade.php` / `edit.blade.php`)**:
  - `<x-admin.form-card title="..." subtitle="..." icon="...">`: Standard card wrapper.
  - `<x-admin.input name="..." label="..." required>`: Text, email, password, number inputs.
  - `<x-admin.select name="..." label="..." required>`: Standardized dropdowns.
  - `<x-admin.textarea name="..." label="...">`: Textareas with error binding.
  - `<x-admin.toggle name="..." label="...">`: Alpine.js interactive switches.
  - `<x-admin.form-actions cancelRoute="..." submitLabel="...">`: Uniform buttons.
- **Destructive Actions / Deletions**:
  - Never use raw `confirm()` dialogs.
  - Dispatch the global event: `@click="$dispatch('confirm-action', { action: '{{ route(...) }}', method: 'DELETE', title: '...', message: '...' })"`.
- **Dual Brand Flavor Invariance**:
  - All `<x-admin.*>` components dynamically adapt to `CMS_BRAND=dbcms` (Cyberpunk dark) and `CMS_BRAND=termocms` (Medical corporate white). Never hardcode theme colors in module Blade views.
- **Sidebar Navigation Integration**:
  - Implement `getAdminNavigation(): array` in `{ModuleName}ServiceProvider`. Active modules automatically appear under *Extensiones y Módulos* in the backoffice sidebar.
- **Canonical Architecture Reference**:
  - Detailed patterns, external database connections (static & dynamic in runtime), and multi-entity CRUD blueprints are documented in [`docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md`](../../../../docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md).

---

## 3. Lifecycle and verification

Read `specs/CORE_AND_EXTENSIONS.md`. Declare `requires` in `module.json`;
registration/discovery reads metadata without installing or activating packages.
Explicit installation reconciles the selected graph and runs its migrations.
An optional module must be physically removable without breaking the core.
Pages is core; reusable charts and comparison blocks are visual packages.

Use an explicit `TEST_MODULES` composition and minimal domain fixtures. Test the
real installation lifecycle separately from feature fixtures. Run `make test`,
the affected domain cases in the disposable QA runtime, and `make test-integration`.
Package, installer, updater or concurrency changes also require `make test-release`.
Keep PHPStan level 8 and Pint as separate gates; see `docs/TESTING.md`.
