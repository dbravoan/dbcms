# Feature Specification: Validate direct execution before reservation

> **Spec ID**: `FEAT-DIRECT-EXECUTION-VALIDATION`  
> **Status**: IMPLEMENTED  
> **Author**: `DomainEngineer` Agent  
> **Date**: 2026-10-11

## 1. Domain Objective & Context

An invalid direct capability request currently reserves an execution before plan validation. Retrying that request can return an execution with no steps as completed. Validate the registered capability and its arguments before calculating the reservation or writing execution state.

## 2. Algebraic Data Types & DTO Contracts

Keep the existing direct capability name, arguments, `ActorContext`, `ExecutionPlan` and step contracts. Unknown capabilities and arguments rejected by planning validation raise the existing `ValidationException`.

## 3. Formal Invariants

- Preconditions: a direct request names a registered capability and satisfies its planning argument schema.
- Processing: validation precedes fingerprint calculation and `firstOrCreate`; valid requests follow the existing authorization, idempotency, execution and approval services.
- Postconditions: invalid requests and their retries create neither execution records nor steps and cannot become completed executions. Model-supplied confirmation never grants authority.

## 4. REST API & Passport OAuth2 Contract

Existing adapters, capability names and permissions remain unchanged. Web sessions retain their real identity and require no fabricated OAuth scopes. Publishing still waits for a valid backoffice approval of the concrete change.

## 5. Generative & Traditional SEO Specification

No public content, SEO metadata or indexing route changes. Invalid requests have no persisted content effects.

## 6. Test Invariant Checklist (TDD)

- [x] Missing, wrongly typed, unknown arguments and unknown capabilities fail on both initial request and retry.
- [x] Invalid direct requests create no execution or step records.
- [x] Existing execution, idempotency and approval regression tests remain green.
- [x] Feature verification runs in a guarded, ephemeral MySQL database, never the development database.

Verification: four focused cases failed before implementation; the complete native execution suite then passed 24 tests and 93 assertions in ephemeral MySQL 8.0. Pint passed on both changed PHP files; PHPStan Level 8 reported no errors in the execution service.
