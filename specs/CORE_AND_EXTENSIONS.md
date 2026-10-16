# Core and extensions

Status: approved for implementation, 2026-10-15.

## Product contract

The core is a working CMS: installation, identity/permissions, localization,
media, pages, menus, publication, sections, themes and technical SEO. Pages is
application code, not a removable module. Optional business modules contribute
entities, routes, navigation, capabilities and data sources. The core must boot,
install and publish a page when their directories are physically absent.

Discovery reads manifests. Explicit installation reconciles stored state and
executes only selected migrations. Registration is deterministic and does not
write module records. Missing requirements, duplicate identifiers and cycles
fail before activation. Deactivation preserves data and refuses active dependents.

## Visual extensions

Blocks and widgets share one registry, definition and availability decision.
Definitions own configurable fields, validation, translation paths, rendering,
assets and placement. Basic (text, hero, layout content), Charts (including
KPI/ROI) and BeforeAfter are bundled visual packages with the same activation
lifecycle. Search owns its optional search-box block. Modules can contribute blocks that require them.

Bundled packages cannot be uninstalled through administration. Uploaded ZIP
packages start disabled and cannot overwrite an installed package. Packages are
trusted administrator code, not sandboxed PHP. Archive validation and atomic
extraction are shared with module installation. A staged package must register against the current catalogue in a separate PHP process before its directory or disabled state is published; namespace, class and block-type collisions abort installation. No Composer/npm runs in HTTP.

Disabled definitions cannot be inserted or mutated. Existing instances remain
stored, appear as unavailable placeholders in the editor and do not render,
execute queries, enter AI selectors or contribute public semantic content.
Editing another part of a page preserves them; reactivation restores rendering.
An availability change rebuilds the search index synchronously within the toggle
transaction (module lifecycle service and the block-package availability event). This infrequent administrator operation
costs O(indexed documents) and avoids stale hidden text in search results.
Discovery, provider boot and installation of a disabled package do not rebuild it. Successful composition changes signal queue workers to restart after commit; rollback sends no restart signal.

## Canonical boundaries

No historical API aliases or data converters are required: this is an unused
development version for fresh installations. Shared writes preserve authorization,
transactions, publication review, market/locale isolation and after-commit effects.
A section without a translation is absent from that locale in the editor, public rendering and semantic corpus. Opening a form never creates demo blocks. Empty insertion initializes structural/schema defaults; demo content is an explicit insertion choice. Plain helpers need no forwarding interface. DTOs exist only at useful boundaries.
Tests and documentation follow these behaviors, not retired implementation shapes.

## Verification

- Core-only artifact: install, login, create/publish/read a localized page.
- Dependency graph: duplicate, absent, incompatible, cyclic and active dependent.
- ZIP: valid package, invalid archive, collision, failed registration rollback.
- Blocks: lifecycle, preserved disabled instances, API rejection and both themes.
- Content: permissions, editorial parity, rollback, concurrency where meaningful.
- Fast tests use small explicit fixtures, pure units and imported frontend code.
- Integration and release tests use isolated filesystems and databases.
- PHPStan level 8, Pint, frontend build and dependency audits stay separate checks.

The working installation, its files and keys must never be reset. No release,
deployment or external publishing is part of this work.


### Editorial snapshots and responsibilities

The ContentAudit extension implements the core editorial workflow boundary directly. Audit policy, revision history, entity presentation, heuristic quality and notifications are distinct responsibilities, with no legacy facade. Snapshots have exactly one accepted schema, version 3; hydration/restoration reject older or incomplete records before writing. No Post/Page/Product restore branches or model snapshot hooks remain. An optional mutation handler owns its unique domain records.

Invariants: staging never replaces live data; approval rejects stale live hashes; review tokens bind actor/target/content; private visibility intent survives approval; rollback restores full canonical rows and module relations under a transaction; external notifications occur after commit. Uninstallation rejects every installed dependent, including inactive dependents; deactivation rejects active dependents.
