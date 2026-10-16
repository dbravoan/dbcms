# Testing and quality gates

Tests protect current behavior. A test needs a concrete failure it detects; case
counts and line coverage are not objectives. The approved architecture is
[Core and extensions](../specs/CORE_AND_EXTENSIONS.md).

## Daily feedback

Start official Sail explicitly before using the local runtime:

```bash
./vendor/bin/sail up -d
make test
make test-filter FILTER='canonical'
```

`make test` runs `bin/test-suite fast`: pure contracts, the module dependency
graph, a small HTTP core journey and frontend behavior. The HTTP fixture creates
one language, market and administrator; it does not seed the demonstration site.
It uses the isolated MySQL `testing` database. Never point a test connection at
the working installation. `make test-filter` filters the PHP fast configuration.

`tests/Pure` contains Pest tests without a Laravel application. Existing pure
PHPUnit classes can remain beside their domain while being listed in the fast
configuration. Framework-dependent tests must declare the application/DB setup
they need. `Tests\TestCase` reads `TEST_MODULES` from an explicit composition
trait (for example `uses(Tests\Support\McpCoreComposition::class)` in Pest).
It selects those providers before Laravel boot and records only their test state
after migration. Changing composition resets the migration cache; it never scans test
names to infer requirements. `DemoComposition` is reserved for demo behavior.
Static checks belong in the fast lane only when their assertions
protect a current contract and demonstrate that a violating example fails.

## Integration and release

These commands use a disposable code copy and database, including for tests
that change `.env`, installer locks or maintenance state:

```bash
make test-integration
make test-release
docker compose -p dbcms-quality -f docker/ci/compose.yml down --volumes --remove-orphans
```

Inside that container, `bin/ci-check integration` runs dependency audits, asset
build, Pint, PHPStan and integration suites. `release` additionally runs the real
checkout race, physical derived-installation smoke and standalone Apache updater
checks, plus the complete backoffice surface in both brands. `bin/test-core-distribution.sh` also copies an artifact with its own vendor
and without `modules/`, installs into a new temporary database, then exercises
HTTP login, publication and public reading. Its database admin credentials are
limited to the disposable CI server (`DBCMS_TEST_DB_ADMIN_USER/PASSWORD`). Release validation must cover the exact commit being published.
The full CI composition enables `WITH_CREATIVE_TOOLS=true` to verify real PNG
and MP4 rendering. The production image keeps these tools optional and disabled
by default; core installation and publication do not require them.
`DBCMS_TEST_ISOLATED=1` is set by the disposable CI runtime; setting the flag does
not itself isolate files or databases.
The checkout race additionally verifies `DBCMS_TEST_DATABASE` against both the
configured and actual MySQL connection before migrating. CI explicitly selects
`testing`; standalone disposable runs use `dbcms_test_` plus twelve hexadecimal
characters. Its committed Shop fixture does not depend on another test's tables.
Installer tests use the same database guard, migrations without a surrounding
transaction and private environment/storage directories per case. They exercise
the real environment writer, database reconnection and installation middleware.
Other framework tests explicitly disable only the installation middleware in
their harness and use an isolated `uploads` disk; production has no test-mode
branches for either behavior.

Changes to an installation, deployment, updater or concurrency boundary require
its release checks before merge. Selected domain tests are useful during a change;
the integration gate remains comprehensive until dependency selection is proved
complete. Unknown dependencies, bootstrap, global providers, schemas and shared
fixtures require the complete integration gate. Do not silently exclude failing
legacy cases: remove their retired contract or rewrite the test for its owner.

Derived projects use the selected-profile contract in `bin/ci-derived-tests`;
source-catalogue tests requiring intentionally removed themes/deployment files
belong to the source distribution. The physically minimal `Blog` + `Basic` +
`ia-practica` + `dbcms` profile runs fast contracts and explicit core security,
authentication, installer, Blog and starter journeys; its release lane also runs
the physical AI learning distribution journey. The full module catalogue retains
the comprehensive derived selection. Other partial compositions fail explicitly
until they have a supported selection. Keep these selections aligned when adding
tests. `bin/test-frontend.mjs` runs all frontend suites whose optional package sources exist in that artifact. Its
explicit dependency map omits only absent packages; unmapped suites always run.

## Test review

For every existing or proposed case record the behavior/risk, real entry point,
observable outcome and smallest fixture. Preserve unit decisions, transactions
and real concurrent execution as different boundaries. Merge cases only when
they exercise the same boundary and risk. Use datasets for meaningful variation.

Mock remote dependencies, not the decision being verified. Prefer imported
production JavaScript and rendered DOM over regex extraction from Blade.
OAuth tests configure their own in-memory keys through `PassportTestKeys`; CI
does not generate shared key files to satisfy an undeclared test prerequisite.
Source/AST checks remain valuable for package, dependency and security rules;
comments, incidental formatting and historical wrapper counts are not contracts.
Skips must identify an intentionally separate runtime, not missing demo data.

## Measurements

The historical logs under `storage/app/runtime-verification/quality-workflow-20261012`
show partial runs of 64 cases in 17.58 s, 67 in 20.88 s and 31 in 32.43 s. They do
not measure the current full suite. The old PHPUnit cache contains only 34 cases.

Measure the same commit/container with separate cold startup, dependency/build,
fixture and test timings. Record per-case JUnit data, memory and queries where
relevant. Three initial runs provide a median and observed maximum, not a reliable
95th percentile. Set absolute budgets after measurement; retain a comparable
baseline and explain regressions instead of promising an unmeasured duration.

On 2026-10-16, three prepared-runtime executions of `bin/test-suite fast` took
7.712, 7.688 and 8.396 s, including PHP and frontend: median 7.712 s, observed
maximum 8.396 s. Each passed 38 PHP cases (639 assertions) and 53 frontend cases.
Build, audits and static analysis are separate. There is no comparable before
measurement or basis for estimating percentiles. See the
[follow-up verification](archive/REFACTOR_FOLLOWUP_2026-10-16.md) for scope and
release evidence.
