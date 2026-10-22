# Working on dbCMS

Read the applicable specification before implementing behavior. The current
architecture contract is [Core and extensions](specs/CORE_AND_EXTENSIONS.md);
[Testing](docs/TESTING.md) defines the verification lanes.

## Runtime and working state

- Never run PHP, Composer, Artisan or Pest on the host. Start official Sail with
  `./vendor/bin/sail up -d` (`--build` on first launch); use official Sail commands.
  Without vendor, follow `docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md` or `bin/project-init`.
- Inside an existing CI/bootstrap container run PHP/Composer directly; never nest
  Sail. Native VPS commands follow the deployment runbook.
- Preserve existing changes, `.env`, installed data, keys and runtime locks. Tests
  that change installation/update files run only in a disposable filesystem and
  database. `RefreshDatabase` does not isolate files.
- Delegate independent subsystem work when useful; give each agent explicit file
  ownership and reconcile shared contracts before merging their changes.

## Architecture

- Core owns installation, identity/permissions, localization, media, pages, menus,
  publication, sections, themes and technical SEO. Pages is `App\Content\Pages`,
  not a module. Section rendering is `App\Core\Sections\SectionRendererService`.
- Optional modules own their entities, migrations, routes, navigation and
  capabilities. Discovery reads manifests; installation changes persistent state.
  Boot must not discover by writing records or activate every directory found.
- Blocks and widgets share one definition/registry and availability decision.
  Charts, KPI/ROI and BeforeAfter are visual packages, not business modules.
- A disabled definition cannot execute or enter selectors. Preserve stored
  instances, show unavailable editor placeholders and restore them on activation.
- HTTP controllers validate/delegate/respond. Keep actions short; no SQL in Blade.
  Domain services own business decisions and transaction boundaries. Dispatch
  remote effects after commit when a write must be atomic.
- Introduce interfaces for actual substitution or subsystem boundaries and DTOs
  for meaningful typed inputs/outputs. Do not add forwarding wrappers, duplicate
  registries, universal interfaces or converters for retired contracts.
- This development version targets fresh installations. No legacy API aliases or
  historical payload conversion are required. Remove their tests with the retired
  behavior; preserve current authorization, publication and isolation controls.
- Use strict PHP types, PHPStan level 8 without baseline ignores and Pint. Views
  use shared semantic admin components and support both installed brands.

## Tests and delivery

- Every test must detect a concrete relevant failure. Reproduce a bug before its
  fix when that provides useful coverage. Do not write tests for counts, comments,
  incidental formatting or to justify unused abstractions.
- `make test`: pure contracts, small core/extension journeys and frontend behavior.
  `make test-integration`: isolated domain integration and static/build checks.
  `make test-release`: full isolated checks including installation and real races.
- Use minimal explicit fixtures. Seed the full showcase only when the showcase
  itself is under test. Mock external boundaries, never the decision being tested.
- Retain security, transaction and concurrency evidence; they are distinct
  boundaries. A source/AST guard is useful when violating examples prove it works.
- Make focused edits, inspect consumers including dynamic registration, and verify
  the affected behavior. Report what changed, measured checks and remaining limits.
  Do not claim a clean full suite from a filtered run.
- Keep current docs concise. Store dated audit and delivery evidence outside the
  public source tree; prior results do not establish the current implementation.
