# Feature Specification: Shared installation domain

> **Spec ID**: `FEAT-INSTALLER-SHARED-DOMAIN`  
> **Status**: IMPLEMENTED  
> **Date**: 2026-10-12

## Objective
HTTP installation adapters delegate to shared contracts. CLI and HTTP reuse environment serialization/key validation, MySQL preparation, provisioning and administrator finalization. Preserve URLs and response formats while rejecting false provisioning success, DSN injection, account overwrites and partial finalization.

## Contracts
Immutable `DatabaseSetupDTO`, `InstallationAdminDTO` and `InstallationResultDTO`. Services own environment, database, provisioning, completion and read models. Controllers are invokable, have no persistence and contain at most ten action lines.

## Invariants
- Explicit MySQL connection succeeds before credentials persist; identifier is validated unchanged and auto-create preference is respected.
- Environment values retain literal characters via existing serializer and atomic private replacement; injected APP_KEY is preserved and verified.
- Every provisioning exit is checked, existing OAuth keys are preserved, demo is opt-in. Failed provisioning does not seal installation.
- Web installation cannot reset an existing user; CLI forced installation is explicit. Account, roles and configuration are finalized under a lock with transactional DB and restoration of local files on failure.
- Existing installer middleware/throttle/CSRF protections remain.

## Validation
- [x] RED/green HTTP safety and architecture regressions.
- [x] All existing installation tests in private filesystem and database.
- [x] Pint and PHPStan, no baseline.

No public SEO surface changes.
