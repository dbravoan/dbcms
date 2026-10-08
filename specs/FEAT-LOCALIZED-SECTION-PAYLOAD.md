# Feature Specification: Localized section payload parity

> **Spec ID**: `FEAT-LOCALIZED-SECTION-PAYLOAD`  
> **Status**: IMPLEMENTED  
> **Author**: `DomainEngineer` Agent  
> **Date**: 2026-10-11

## 1. Domain Objective & Context

Native planning and direct CMS capabilities must accept the localized section payload already supported by the shared persistence service. A locale-keyed object currently fails capability validation, and Blog's mutation handler casts array payloads to a string before deriving article text.

## 2. Algebraic Data Types & DTO Contracts

The existing `sections_json` argument accepts a locale-keyed object, a flat list of sections, or a JSON string encoding either shape. No new DTO or transport is introduced. JSON Schema `array` remains a list; localized maps are explicitly declared as `object`.

## 3. Formal Invariants

- Preconditions: the actor satisfies existing capability permissions and arguments satisfy the published schema.
- Processing: capability validation accepts the three supported section representations. Blog decodes strings and consumes arrays directly, without casting arrays to strings.
- Postconditions: equivalent localized maps and encoded JSON derive equivalent article text and SEO defaults for each locale. Explicit article text remains authoritative. Lists use the configured default locale. Persistence, transactions, authorization and approval requirements remain in the existing shared services.

## 4. REST API & Passport OAuth2 Contract

Existing `cms_entity_create` and `cms_entity_update` capabilities and their web/API/MCP adapters retain their names, responses and authorization. Their `sections_json` schema becomes `object|array|string`. Other list arguments, including `market_ids`, must still reject objects. No scopes are added to web sessions.

## 5. Generative & Traditional SEO Specification

Blog's existing summary and meta-description derivation must use the text of the corresponding locale. This change creates no public content, indexing routes or schemas.

## 6. Test Invariant Checklist (TDD)

- [x] Both capabilities accept localized objects in planning and execution validation, retaining list and JSON string support.
- [x] Other list arguments continue rejecting objects.
- [x] Blog produces equivalent ES/EN text and SEO defaults from objects and encoded JSON.
- [x] Flat lists retain their default-locale behavior, and explicit text is preserved.
- [x] Tests extend PHPUnit's base class and never bootstrap or mutate a database.

Verification: the focused Sail suite failed with four regressions before implementation, then passed five tests and 27 assertions. Pint passed on the four PHP files; PHPStan Level 8 reported no errors for those files.
