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

The disposable CI MySQL data directory uses `tmpfs` to avoid disk flush costs
when tests rebuild schemas. It consumes host memory and is discarded with the
container. Sail and production databases keep their existing persistent storage;
transaction, installation and concurrency checks still use real MySQL.

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

Derived projects use the selected-profile contract in `bin/ci-derived-tests`.
The selection follows their actual source manifest and dependency closure;
source-catalogue tests for removed themes, modules or deployment controls belong
to the source distribution. Core security, installation and publication remain
required for every child. Full-catalogue projects retain the comprehensive derived
selection. Keep this map aligned when adding tests; never infer a business profile
from a name or silently hide a failure. `bin/test-frontend.mjs` runs all frontend
suites whose optional sources exist. Unmapped suites always run.

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

## Local preparation and fast GitHub checks

Heavy verification is mandatory locally before delivery. `bin/prepare-delivery` exports the exact committed SHA into a disposable Docker filesystem/database, runs the release lane for both product releases and deployments; reducing an ordinary deployment to integration requires a future trusted deployed baseline covering the full change range, then constructs and signs the artifacts. It has no skip-quality option. The evidence and artifact hashes bind the prepared source SHA.

GitHub CI runs basic delivery contracts and configuration checks. Publication and deployment verify the signed local evidence, hashes, exact main CI identity and runtime bootstrap. Those three workflows do not repeat full suites, audits, PHPStan, Pint, frontend compilation or ZIP packaging. Docker publication is a separate native multiarchitecture workflow: its image build does install production dependencies and compile frontend assets.

Delivery tooling is also tested with Python standard-library tests: valid bundle acceptance, wrong/missing signature, mismatched SHA/kind/version, failed/insufficient quality, changed bytes and unsafe archive entries. These cases protect actual acceptance and extraction, not source formatting.

Record local quality duration separately from remote CI, download, transfer, migration, health and Docker build. Initial remote goals are CI below three minutes and deployment runner preparation below two minutes, excluding queue and server operations. Compare cold and warm caches explicitly and report actual measurements.

## Cold database readiness

The MySQL healthchecks require a TCP connection authenticated as the configured
application user and a successful `SELECT 1` in its database. A socket-only
initialization server, denied credentials or an absent database remain unhealthy.
Passwords are passed through the client environment, not command arguments or
healthcheck output.

Run the focused cold-start regression from a machine with Docker available:

```bash
python3 bin/test-mysql-readiness.py
```

It starts fresh private containers for all three Compose profiles, holds their
initialization at a temporary socket-only server, proves rejection, then releases
initialization and checks valid/invalid credentials and database selection. It
mounts only its temporary fixture and removes its own containers. It does not
require host PHP or restart the installed Sail services. This host-Docker check
is separate from the in-container application release lane.
