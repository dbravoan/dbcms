# Testing dbCMS behavior

[TESTING](../../../../docs/TESTING.md) is the canonical lane/runtime guide.
Generic `/api/posts`, `App\Models\Post` and Sanctum examples are not current CMS
contracts. Blog owns its model/translations; Passport scopes supplement RBAC.
Copy fixtures and routes only after reading their actual owner.

## Choose the boundary

Use a pure test for a pure decision. For a domain/HTTP test, create only the
required explicit composition, actor permissions, entity, locale and market.
Assert the relevant observable mutation/denial/result; do not substitute a
record-count assertion for publication, authorization or relation integrity.
A factory’s defaults do not prove public eligibility or a valid review token.

Mock remote providers with HTTP fakes and assert the intended boundary call.
Do not fake the decision under test or blanket-fake events when testing
transactional publication/indexing. API tests must use their real scope contract;
session authentication alone does not represent a revoked Passport token.

## Isolate files as well as the database

`RefreshDatabase` may migrate once and use transactions on later tests; it does
not mean migrations run before every case and does not isolate files. Do not
combine it mechanically with `DatabaseTransactions`. Soft-delete assertions
require a model that actually uses soft deletes; a soft-deleted row remains in
the table, so `assertDatabaseMissing` is not equivalent to `assertSoftDeleted`.

Installation, update, keys, runtime locks and real race tests require disposable
code and an exclusive database. Setting `DBCMS_TEST_ISOLATED=1` asserts that
isolation already exists; it does not create it. A test database attached to a
working filesystem is insufficient. Never reset the installed development DB.

## Run and report

From the host, with Docker available:

```bash
make test
make test-filter FILTER='canonical'
make test-integration
# Full isolated delivery/install/update/concurrency checks:
make test-release
```

Inside an existing CI/bootstrap container use PHP/Composer directly; do not nest
Sail. Official Sail is used for local PHP tools after explicit startup. Use the
repository’s disposable lanes for tests that mutate installation files.

Read `tests/Pest.php`, `tests/Support/` and the area’s existing suite for concrete
fixtures. Security, transaction and real concurrency evidence cover distinct
failures. A filtered pass is not a clean full suite; record the command, actual
result, code state and external-service limitations. Dependency audits and builds
are executed checks, not conclusions inferred from reading a test file.
