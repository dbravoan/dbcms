# 15 · Front Section Access → Backoffice Editor

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

---

## 2. Storage model (canonical contract)

A **section** is a container; inside it live 1..N **columns**, and inside each
column live 0..N **blocks**. A block is exactly what the registry offers (text,
hero, features…) or a **module widget** (blog, shop, gallery, form, charts) —
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
| `normalize($payload)` | `columns_data` (legacy) → `columns`; a scalar `columns` → `grid_columns`; a section with content and no columns → one column holding that content |
| `resolve($master, $content)` | **For every field, the localized content wins over the master** (before, the master — usually Spanish — won, which is why an English page rendered Spanish blocks) |
| `columns($payload)` / `blocks($payload)` | Normalized column list and the flattened blocks in visual order |
| `gridColumns($payload)` | Container CSS grid of a block: `grid_columns` with the legacy scalar `columns` as fallback (it used to collide with the section's column list, which is why several blocks rendered as a single empty column) |
| `plainText($payload)` | Text of every block, columns included (GEO/SEO surfaces, diffs, inspector) |
| `withLayout($payload, $layout)` | Resizes the column list; entering a multi-column layout moves the section content into the **first** column; **going back to one column stacks every block, in order, without losing any** |

Consumers, all through the same contract: `SectionRendererService`,
`SectionSyncService`, `SectionEditorService`, `PageDomainService`, the MCP
surfaces (`cms_content_sections_get/sync`, `cms_publish_page`), the AI assistant,
`/llms.txt`, the front-office inspector, the seeders and the
`sections:normalize` command (idempotent, safe in production).

Legacy payloads (`columns_data`, section-level content) keep rendering; the
command only removes the compatibility path from the stored data.

### Block kinds: full-width vs. column blocks

A block whose type manages its own interior grid (carousel, features, pricing,
testimonials and the blog/shop/gallery widgets) declares `isFullWidth()`. Full
width blocks:

- are **never offered inside a column** in the add-block modal (a grid inside a
  column renders as a grid inside a grid);
- always land at section level, even if the modal was opened from a column;
- keep rendering when they already live inside a column (legacy payloads), and
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
  on `init()`, resolves the block whose id matches (digits only, so `{id}` and
  `sec_{id}` both match), expands it, flashes a ring and scrolls the admin
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

Draft/publish of sections is done from the backoffice block manager form (the
entity's own `status` field), not from the public page.
