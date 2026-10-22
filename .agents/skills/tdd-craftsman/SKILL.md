---
name: tdd-craftsman
description: Implements failing Pest tests, boundary assertions, and architectural tests following the SDD specification.
---

# TDD Craftsman Skill

## Goal
Protect relevant observable behavior with the smallest effective suite.

## Procedure
1. Inspect the approved `specs/<feature>.md`.
2. Use pure tests for decisions; introduce no interface or mock solely to make a test possible.
3. Author Feature Tests in `tests/Feature/` testing HTTP requests, Passport token authorization, and database persistence.
4. Author Architectural assertions in `tests/Architecture/ArchitectureTest.php` to prevent layer violations.
5. Reproduce meaningful failures, then use the appropriate fast/integration/release lane in `docs/TESTING.md`. Remove tests of retired contracts; do not retain wrappers for test parity.
