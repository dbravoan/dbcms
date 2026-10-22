# 15 · Front Section Access → Backoffice Editor

URL examples below use `/admin` as the default configured `ADMIN_PATH`; use the
actual installation prefix. Filesystem/view paths named `admin` do not change.

> **Status**: Implemented (replaces the former inline front editor)
> **Scope**: Pages, Blog posts, Shop products
> **Related**: [`13_ADMIN_UI_FRAMEWORK.md`](13_ADMIN_UI_FRAMEWORK.md) · [`14_LOCALIZATION_AND_I18N.md`](14_LOCALIZATION_AND_I18N.md) · [`18_THEME_MANIFEST_AND_BRAND_REFERENCE.md`](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md)

---

## 1. Objective

From a **public page**, an authenticated backoffice user can jump straight to the
**exact block** that renders that section in the backoffice editor. Editing
itself always happens in the backoffice block manager; the public page only
provides a precise deep-link.

```
Public page (admin session, "Click to Edit" active)
  └─ <section data-cms-section="{id}">   ← rendered by SectionRendererService
        └─ pill "Editar sección"  →  /admin/{pages|blog/posts|shop/products}/{id}/edit?section={sectionId}
              └─ <x-admin.block-manager :target="request('section')">
                    └─ focusTargetBlock(): expand + scroll + ring the block
```

There is **no inline editing on the public page** and no front-facing sections
API anymore.

To implement a new block, follow [creating blocks and visual packages](26_CREATING_BLOCKS_AND_VISUAL_PACKAGES_GUIDE.md).
For blocks backed by a module's entities and services, follow the
[module walkthrough](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md). Both use this same
section editor and storage model.

---

## 2. Storage model (canonical contract)

A **section** can be a standalone block or a container with 1..N **columns**,
each containing 0..N **blocks**. Without a column list, `blocks()` returns the
section itself; `normalize()` does not add columns to every simple payload. A block is exactly what the registry offers (text,
hero, features…) or a **module block** (blog, shop, gallery, form) or visual package block (charts) —
nothing in a section knows which module produced a block.

```
section
 ├── columns_layout : 1 | 2 | 1-2 | 2-1 | 3 | 4
 ├── columns        : [ { id, blocks: [ {…block payload…} ] } ]   ← canonical key
 └── style/animation of the row (background, borders, dividers…)
```

| Element | Column | Purpose |
| :--- | :--- | :--- |
| Section row | `{page,post,product,filter_page}_sections` | Container + row styling |
| `styling_json` | on the section row | Master payload: layout, row styling, defaults |
| `columns` inside `styling_json` | — | Column list (each with its own `blocks`) |
| `*_section_translations.content_json` | per locale | **Localized** content of the section **and of its blocks** |
| `status` / `published_at` | on the section row | Draft \| published |
| `is_active` | on the section row | Soft visibility toggle |

**Single source of truth**: `App\Core\Sections\SectionLayout`.

| Method | Contract |
| :--- | :--- |
| `normalize($payload)` | Normalizes the canonical `columns` list and its blocks; it does not convert historical keys or scalar column counts |
| `resolve($master, $content)` | Localized top-level keys replace master keys via `array_replace`, then columns are normalized; this is not a recursive merge of block fields or column lists |
| `columns($payload)` / `blocks($payload)` | Normalized column list and the flattened blocks in visual order |
| `gridColumns($payload)` | Reads the block interior grid from `grid_columns`, or the supplied default; section `columns` is a list and is never a grid count |
| `plainText($payload)` | Text from supported scalar keys, items and slides in visual order; callers enforce public availability and visibility |

Consumers, all through the same contract: `SectionRendererService`,
`SectionSyncService`, `SectionEditorService`, `PageDomainService`, the MCP
surfaces (`cms_content_sections_get`, `cms_content_sections_sync`, `cms_entity_create`, `cms_entity_update`), the AI assistant,
`/llms.txt`, the front-office inspector and the seeders.

This version uses the canonical payload for new installations. There is no
historical-payload conversion command. Unknown or disabled definitions remain
stored as unavailable editor placeholders; they do not execute or render.

### Block kinds: full-width vs. column blocks

A block whose type manages its own interior grid (carousel, features, pricing,
testimonials and the blog/shop/gallery widgets) declares `isFullWidth()`. Full
width blocks:

- are **never offered inside a column** in the add-block modal (a grid inside a
  column renders as a grid inside a grid);
- always land at section level, even if the modal was opened from a column;
- keep rendering when an available definition already lives inside a stored column, and
  the editor offers **"Promote to section"** to lift them out.

### Background behaviour (Section and Column scopes)

`bg_behavior` is part of the row/column styling (`normal` \| `parallax` \|
`fixed`) and only exists in the **Section** and **Column** appearance scopes —
those are the surfaces that actually paint a background:

| Value | Render |
| :--- | :--- |
| `normal` | CSS background, scrolls with the page |
| `fixed` | CSS background with `background-attachment: fixed` |
| `parallax` | A real `<img class="cms-bg-layer">` layer; the wrapper gets `data-scroll-scrub="parallax"` and the shared scroll engine translates it |

Column styling travels inside each column as `style` (background, borders,
padding, behaviour), preserved by `SectionLayout::normalize()` so the editor
round-trips it.

---

## 3. Front side

- `SectionRendererService::renderSection()` marks every rendered section with
  `data-cms-section="{id}"` (numeric entity id; robust against reordering).
- `frontoffice-admin-bar` keeps the **"Click to Edit"** mode, the hover outline
  and the per-section pill. When the mode is active, each `[data-cms-section]`
  gets a pill whose `href` is `{editUrl}?section={id}`.
- Entity-level wrappers that contain section targets are skipped so each section
  shows exactly one, precise pill. Entities without sections fall back to the
  main content area (`{editUrl}`).

Permissions come from the backoffice (the target editor enforces them); the bar
is only rendered for authenticated staff.

---

## 4. Backoffice side

- The page/post/product `_form` passes `request('section')` to the block manager
  as the `target` prop.
- `block-manager` receives the target as its last Alpine constructor argument and,
  on `init()`, matches the exact string ID, first in the active locale and then
  in the other locale payloads. It switches locale if needed, expands the block,
  flashes a ring and scrolls the admin
  `<main>` (`overflow-y-auto`) to it. The scroll retries once, because images and
  fonts keep growing the layout right after init.

---

## 5. Removed (former inline editor)

- `resources/views/components/frontoffice/section-editor.blade.php` and its
  floating trigger/overlay.
- `SectionEditorService::frontPayloadFor()` plus the front-facing section API
  (`/admin/api/sections/*` and its controllers).
- The `frontSectionEditor` view payload from the front controllers.
- Orphan `front_bar.edit_sections*` / draft / publish language keys.

The backoffice block manager submits content through `sections_json`. Shared
mutation calls `SectionSyncService::sync()` with `published` or `draft` derived
from the entity's `is_published`/`is_active`; the service writes section `status`
and `published_at`. Per-block JSON values do not independently choose that
status. The editorial workflow still governs the whole entity, and the public
page only provides the deep-link.
