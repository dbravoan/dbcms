# Backoffice presentation inventory

This inventory describes composition and intentional
exceptions; it does not claim that every route has been visited in a browser.
The contract is [Admin UI framework](13_ADMIN_UI_FRAMEWORK.md) and
[theme parity](../specs/FEAT-ADMIN-UI-THEME-PARITY.md).

## Shared composition

Both installed brands use the same heading, card, control, action, table, modal,
selector and navigation components. dbCMS retains its dark green identity;
termoCMS retains its light teal identity. `resources/css/admin-ui.css` owns shared
geometry and interaction states through `--admin-*` tokens. Theme assets supply
palette and typography. Layouts declare `data-admin-shell`; administrative styles
must not color public content or the forms it contains.

A form keeps its request URL/method, names, hidden inputs, validation, Alpine
bindings and extension stacks. `form-card` forwards attributes to its section;
`form-actions` supports the default submit or existing actions in its slot with
`:submit="false"`. Localized entity forms keep `locale-tabs` before
`market-picker` and use the same group. Native controls in dynamic/specialized
editors use the kit's `admin-control` and labels rather than public `cms-*` tokens.

## Core screens

Paths below are relative to `resources/views/admin/`. Where listed, `create`,
`edit` and `_form` are separate actual views in the named directory.

| Family | Actual screens | Composition / specialized behavior |
| --- | --- | --- |
| Access and shell | `auth/login`, `layout` | Shared controls; brand shell, mobile navigation and notifications. Login has its own compact access composition. |
| Overview | `dashboard` | Shared cards and module-contributed metrics/actions/details; metrics may be links and retain native link semantics. |
| Pages | `pages/index`, `pages/create`, `pages/edit`, `pages/_form` | Shared datatable/form heading and card sections; localized block editor, publication and SEO sidebar. |
| Blog | `blog/posts/index`, `create`, `edit`, `_form`; `blog/categories/index`, `_form` | Shared listing/cards/actions; category form is embedded in its existing modal. |
| Shop | `shop/products/index`, `create`, `edit`, `_form`; `shop/categories/index`, `_form`; `shop/orders/index`, `show`; `shop/inquiries/index` | Shared listings and forms. Order lines are a detail table, not a paginated listing. |
| Media collections | `galleries/index`, `create`, `edit`, `_form` | Shared listing/form; album selection, media ordering and localized content remain specialized. |
| Menus | `menus/index` | Shared listing/modal surfaces; nested menu-item editing retains its hierarchy and secondary table. |
| Localization | `markets/index` | Shared fields/selectors/modal surfaces. Language management is a secondary table inside the market tool. |
| Site configuration | `settings/index` | Shared page heading, card sections, field/action styles; existing settings tabs retain their bindings and submission. |
| Connections | `connections/index` | Shared page heading/status, cards and controls. Provider-specific credential visibility and testing remain unchanged. |
| SEO | `filters/index`, `filters/_form`, `seo/audit` | Shared headings, surfaces and field/actions. Search-module controls remain conditional; audit findings are a specialized report. |
| Tokens | `tokens/index`, `tokens/create` | Shared headings, cards and controls. Personal/M2M tabs and one-time secret display/copy remain specialized. |
| Extensions | `modules/index`, `modules/settings`, `modules/partials/setting-field`, `block-packages/index` | Shared cards/controls/action styles; capability settings, enablement and package installation retain their lifecycle forms. |
| Themes | `themes/index` | Shared surfaces and actions; theme thumbnails and palette demonstrations represent the themes being selected. |
| Integrations | `integrations/index`, `integrations/show` | Shared headings/cards and integration guide steps; setup instructions retain code/clipboard behavior. |
| Orchestration | `orchestration/panel`, `orchestration/review` | Shared cards/controls; previews, authorization and review decisions retain their workflow. |
| Updates | `updates/index` | Shared headings/cards/action styles; progress, support findings and update confirmations remain specialized. |
| Users | `users/index` | Shared listing and existing user/role dialogs. |
| Shell contributions | `partials/notification-email-preference`, `partials/update-check` | Embedded preference/update controls inherit the shell and kit. |

## Module screens and contributions

Paths are relative to each module's `Resources/views/`. Public `blocks/`, portal,
checkout and frontoffice views are outside this backoffice migration.

| Module | Actual admin screens / contributions | Composition / specialized behavior |
| --- | --- | --- |
| AiAssistant | `admin/settings`, `admin/workbench`; `admin/partials/block_generate`, `copilot_bridge`, `create_fill`, `editorial_guide_link`, `entity_translation`, `orchestration`, `seo_generate` | Shared headings/surfaces, fields, buttons and dialogs. Provider testing, generation log, translation preview/replacement, polling and copilot review remain specialized. The copilot bridge retains its overlay lifecycle. |
| Analytics | `admin/settings` | Embedded module settings with the shared fields and semantic surfaces. |
| Blog | `admin/dashboard/actions`, `details`, `metrics` | Dashboard contributions inherit kit surfaces; their link/navigation behavior remains native. |
| ContentAudit | `admin/index`, `admin/review`, `admin/partials/audit-card`, `diff-viewer` | Shared cards and controls; revision changelog/diff tables and approval decisions remain specialized. |
| CreativeStudio | `admin/index`, `admin/show` | Shared listing, headings, cards, fields and actions; private campaign media, revision composition and preview/export controls remain specialized. |
| Forms | `admin/index`, `create`, `edit`, `_form`, `_builder`, `submissions`; `admin/dashboard/actions`, `details`, `metrics` | Shared listing/form surfaces and actions; localized field builder, field ordering and submission inspection retain their behavior. |
| Maintenance | `admin/index`, `admin/settings`, `admin/front-status` | Shared cards, labels/controls/actions; activation status, scheduling, preview device and custom HTML remain specialized. `front-status` is injected into the public frontoffice admin bar and sits outside `data-admin-shell`; it is not a backoffice screen. |
| Mcp | `admin/guide` | Shared semantic controls/surfaces in the module guide; one-time token generation/copy and external-client instructions remain specialized. |
| Shop | `admin/dashboard/metrics` | Shared metric card surface with native link semantics. |
| SocialPublishing | `admin/index`, `admin/preview`, `admin/select-account` | Shared cards, fields and actions; OAuth account selection, per-account review, scheduling and creative preview retain their workflows. |

## Editors and generated screens

`components/admin/blocks/_block_form_body` renders schema fields with admin
controls, including JSON/list/repeater fields. It preserves unavailable blocks,
nested columns, schema defaults and explicit mutation events. Public presentation
tokens are not editor control tokens.

`block-packages/Basic/Resources/views/editor/rich-text` retains Markdown/HTML,
edit/split/preview, snippet insertion, statistics, HTML formatting and media
selection. Its surfaces and active states use admin tokens; inserted HTML snippets
remain content. Other package editors continue using the same dynamic field kit.

The CRUD view stubs under `stubs/cms-module/admin_*view.stub` use shared headings,
form cards, controls, locale/market ordering and actions. New generated CRUDs
therefore begin with the same composition as installed screens.

## Verification boundaries

Rendered component regressions cover both brand configurations, forwarded Alpine
state, named/checked switches, disabled submission, textarea validation and
explicit descriptions, and disabled link keyboard focus. Existing UI/editor tests
cover locale/market ordering, reusable selectors, translation, nesting and media.
Compilation/build and focused tests provide structural/behavioral evidence. Browser
screenshots are separate visual evidence and must name the screens actually visited.

The populated surface matrix exercises post/product audit review, a campaign
without revisions/tasks/assets and a text-only social preview without deliveries
or metrics. Nested menu items and the other audit entity variants require their
own workflow/browser checks. Catalogue DOM tests inspect production Alpine
templates and payload preservation; they do not simulate hydration of every
registered block. These limits must remain distinct from browser review evidence.
