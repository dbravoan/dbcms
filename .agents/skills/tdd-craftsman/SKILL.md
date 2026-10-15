---
name: tdd-craftsman
description: Implements failing Pest tests, boundary assertions, and architectural tests following the SDD specification.
---

# TDD Craftsman Skill

## Goal
Build a robust, 100% boundary-tested suite before domain implementation code is written.

## Procedure
1. Inspect the approved `specs/<feature>.md`.
2. Author Unit Tests in `tests/Unit/Services/` targeting the Service Interface methods with mocked dependencies.
3. Author Feature Tests in `tests/Feature/` testing HTTP requests, Passport token authorization, and database persistence.
4. Author Architectural assertions in `tests/Architecture/ArchitectureTest.php` to prevent layer violations.
5. Run `./vendor/bin/sail pest` to confirm tests fail with descriptive errors (Red stage).
