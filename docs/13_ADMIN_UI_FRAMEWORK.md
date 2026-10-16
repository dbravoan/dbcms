# Admin UI Framework & Component Kit (`docs/13_ADMIN_UI_FRAMEWORK.md`)

> **Purpose**: a single, reusable backoffice UI framework so every module (and
> core screen) is built the same way. **Reuse before copy-paste.**
> **Single source of truth**: `resources/views/components/admin/` (`<x-admin.*>`).

---

## 1. Golden rules

1. **Never hand-roll a table, form header, modal, field or market/language picker.**
   Use the components below.
2. **One source of truth for components**: everything lives under
   `resources/views/components/admin/` and is consumed as `<x-admin.*>`.
   There is no parallel `resources/views/admin/components/` directory.
3. **Market ↔ language logic is only inside `market-picker` + `locale-tabs`.**
   Forms must not re-implement `isLanguageActive` / `toggleMarket`.
4. **Modules declare data, the framework renders it** (e.g. module settings via
   `module.json` types). Modules never import components from each other.

---

## 2. Component catalog

### Listing
| Component | Use |
| :--- | :--- |
| `<x-admin.datatable-header>` | Page title, search, filters slot, create button |
| `<x-admin.table>` | Table shell + mobile rows + empty state |
| `<x-admin.datatable-pagination>` | Pagination footer (`:paginator`) |
| `<x-admin.sortable-th>` | Sortable header cell (`column`, `label`, `class`) |
| `<x-admin.table-row-detail>` / `-item` | Mobile accordion detail |
| `<x-admin.empty-state>` | Empty state |
| `<x-admin.action-button>` | Row action (variants: primary/view/danger/warning) |
| `<x-admin.status-badge>` | Active/status pill |
| `<x-admin.chip>` | Generic pill (`variant`) |
| `<x-admin.market-chips>` / `<x-admin.locale-chips>` | Market / language chips for listings |

### Form
| Component | Use |
| :--- | :--- |
| `<x-admin.form-header>` | Form page header + back/live links |
| `.cyber-card` | Existing card wrapper (there is no `form-card` component) |
| `<x-admin.button>` | Shared submit/cancel/delete actions (there is no `form-actions` component) |
| `<x-admin.input>` `<x-admin.select>` | Typed fields with linked labels, help and validation |
| Native `<textarea>` and checkbox/toggle markup | Existing fields; there are no `textarea` or `toggle` components |
| `<x-admin.media-field>` | Media picker bound to the filemanager modal |
| `<x-admin.seo-meta-box>` | SEO/GEO metadata + SERP preview |

### Modal
| Component | Use |
| :--- | :--- |
| `<x-admin.modal>` | Generic modal (`show`, header/footer slots) |
| `<x-admin.confirm-modal>` / `<x-admin.delete-modal>` | Confirmations (global, listens `confirm-delete`) |

### Data (AJAX)
| Component | Use |
| :--- | :--- |
| `<x-admin.search-select>` | Whitelisted AJAX entity search (single/multiple); supports `contextual` |
| `<x-admin.market-select>` / `<x-admin.language-select>` | Domain wrappers over `search-select` |
| `<x-admin.market-picker>` | Market assignment checkboxes (emits `cms-markets-changed`) |
| `<x-admin.locale-tabs>` | Language tab bar (auto-filters by selected markets) |

### Shared visual contract

Both administrative themes declare `data-admin-shell`. `resources/css/admin-ui.css`
defines semantic controls, buttons, panels, alerts and focus states; each theme
supplies its own colors and typography. Use `.admin-control` for a native textarea,
`.admin-field-label` for its linked label and `.admin-field-hint` for help text.
`input` and `select` accept explicit IDs and preserve Alpine attributes. Validation
sets `aria-invalid` and links the error through `aria-describedby`.

Legacy native controls receive consistent spacing, borders and focus inside the
administrative shell. This compatibility layer excludes technical input types and
embedded search inputs; `.admin-control--embedded` leaves the enclosing combobox
responsible for its border and focus. Public forms do not receive the shell styles.
New views should use semantic components rather than rely on legacy color overrides.

Structured AI translation uses the same modal, select and button components. Its
preview scrolls independently; errors, warnings and replacement confirmation follow
the theme palette. The review evidence is recorded in
[the functional audit](archive/CMS_MODULE_FUNCTIONAL_AUDIT_2026-10-11.md#revisión-visual-de-termocms-y-dbcms).

---

## 3. Market ↔ language contract

- `<x-admin.market-picker group="X">` emits the window event
  `cms-markets-changed` with `{ group, selectedMarketIds, activeLocales }`.
  `activeLocales` is `null` when no market is selected (= show all languages).
- `<x-admin.locale-tabs group="X">` listens to that event and **hides languages
  not assigned to the selected markets**, auto-switching the active locale when
  it becomes hidden. Use the **same `group`** on both components of a form so
  create/edit modals on the same page never interfere.
- `<x-admin.locale-tabs>` also emits `locale-tab-change` (scoped) and
  `cms-locale-switched` (window, consumed by the block manager).

Forms adopting them must only listen to `locale-tab-change` to update their own
`activeLocale`; everything else is handled by the components.

### 3.1 Inherited context for widget selectors (`contextual`)

The same contract is published on `window.cmsContext = { locale, marketIds }`:

- `market-picker` writes `marketIds` on every change.
- `locale-tabs` and `block-manager` write `locale` on every switch.

`<x-admin.search-select contextual>` reads that global context and forwards
`locale` + `market_ids[]` to `admin.api.entity-search`. The registry marks
sources with `'market_scoped' => true` (pages, posts, products, galleries,
categories and forms) so only entities assigned to the inherited markets are
returned. This is how a widget inside the block manager inherits the market and
language already chosen on the parent entity form — no per-widget wiring.

---

## 4. Module settings (decoupled)

`admin/modules/settings.blade.php` maps `module.json` setting types to the kit:

| `type` | Renders |
| :--- | :--- |
| `market_select` / `market_multiselect` | `market-select` |
| `language_select` / `language_multiselect` | `language-select` |
| `entity_select` / `entity_multiselect` (+ `"source"`) | `search-select` |
| `roles_multiselect` / `entities_multiselect` / `multiselect` | legacy multiselects |

A module only declares the type; it never depends on another module's code.

---

## 5. Building a module CRUD (recommended shape)

```
modules/{Module}/Resources/views/admin/
├── index.blade.php     # datatable-header + table + pagination
├── create.blade.php    # form-header + _form
├── edit.blade.php      # form-header + _form
└── _form.blade.php     # form-card sections + fields + form-actions
```

- Index: `<x-admin.datatable-header>` + `<x-admin.table>` (+ `<x-admin.datatable-pagination>`, `<x-admin.market-chips>`, `<x-admin.locale-chips>`).
- Forms: `<x-admin.form-header>` + `<x-admin.form-card>` + fields + `<x-admin.form-actions>`.
- If the entity is market/localized: `<x-admin.locale-tabs>` **before**
  `<x-admin.market-picker>`, with a shared `group` (enforced by
  `tests/Architecture/EntityFormLayoutTest.php`). This keeps the language tabs
  and market selector in the same place in every form, in every theme.
- Language **flags** are assigned in the backoffice (`languages.flag`,
  `admin.market.index`) and resolved by `Language::flag` (accessor with a
  code→emoji fallback). Never hardcode a flag map in a form: `locale-tabs`
  resolves them from the shared `$languageFlags` (or the DB fallback).
- Localized entities store text in `*_translations` tables keyed by `locale` (+
  optional `market_id` override) and markets in a `market_*` pivot; the module
  service normalizes the `translations[locale][...]` payload and syncs it. Never
  keep translatable text on the base table (the base table holds identity +
  structural flags only).
- Widget selectors embedded in the block manager use `contextual` to inherit the
  parent's locale/market (see §3.1).
- Delete: dispatch `confirm-delete` (handled by the global `delete-modal`).

---

## 6. Adoption status (audit)

**Applied**
- Listings: Páginas, Blog (posts), Tienda (productos), Galerías, Categorías Blog/Tienda, Menús → `datatable-header`, `table`, `market-chips`, `locale-chips`.
- Forms: Páginas, Blog, Tienda, Galerías, Categorías → `market-picker` + `locale-tabs` (no duplicated market/language JS).
- Módulo Formularios: 100% traducible (`custom_form_translations`, `form_field_translations` con `locale` + `market_id` opcional, pivot `market_form`); constructor de campos por idioma; listado con `market-chips`/`locale-chips`.
- AJAX selectors: block manager, filters, categorías (parent), workbench IA, mercados admin, module settings; widget selectors del block manager usan `contextual`.
- Migraciones limpias: sin migraciones `add_*`/parche; los pivotes y columnas viven en la creación base de cada módulo.
- Media: `media-field` (Ajustes), filemanager modal global.
- Single source of truth: duplicated `resources/views/admin/components/` removed.

**Pending (outliers to migrate, no behaviour change)**
- Raw `<table>` remaining are **not datatables** and are intentionally bespoke:
  - `admin/markets/index` → table inside the languages management modal.
  - `admin/menus/index` → nested menu-items table inside the links modal.
  - `modules/ContentAudit/.../index` → secondary changelog/diff table.
  - `modules/AiAssistant/.../workbench` → generation log table.
  - `admin/shop/orders/show` → order lines detail table (detail view).
- Form fields still hand-rolled in some module screens (should adopt `form-card`/`form-actions`/`input`).
- Raw overlays: only inside the reusable components themselves (`modal`, `filemanager-modal`, `confirm/delete-modal`, `icon-picker`) plus the AI copilot bridge and the frontoffice shop/geo widgets; the backoffice has no ad-hoc modals left.

---

## 7. Verification & anti-regression

- `tests/Architecture/AdminUiFrameworkTest.php` enforces:
  1. The duplicated `resources/views/admin/components` directory must not exist.
  2. Market/language JS (`isLanguageActive`, `toggleMarket`, `marketLanguagesMap`)
     may only live inside `components/admin/{market-picker,locale-tabs}.blade.php`.
- `tests/Feature/Ui/ReusableSelectorsTest.php` covers the kit components and the
  module-settings type mapping.
- `make:cms-module --crud` scaffolds the kit directly (see `docs/11` §12); the
  generator itself is covered by `tests/Feature/Cms/MakeCmsModuleCommandTest.php`
  (file presence, translatable patterns and `php -l` on every generated PHP file).
- Standard gates: `pest`, PHPStan Level 8, Pint, `view:cache`.
