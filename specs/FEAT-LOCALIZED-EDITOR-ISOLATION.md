# Feature Specification: Preserve the editor's actual translations

> **Spec ID**: `FEAT-LOCALIZED-EDITOR-ISOLATION`  
> **Status**: IMPLEMENTED  
> **Author**: `DomainEngineer` Agent  
> **Date**: 2026-10-11

## 1. Domain Objective & Context

Saving an English-only post currently creates an empty Spanish entity translation from hidden form fields and replicates English section content into Spanish. A localized section dictionary must represent the supplied translations, without silently inventing others.

## 2. Algebraic Data Types & DTO Contracts

Retain the existing entity mutation and locale-keyed section contracts. A localized map contains only actual locale/block pairs; a missing block or empty locale list creates no translation. Legacy flat section lists keep their existing shared-content behavior.

## 3. Formal Invariants

- Preconditions: existing actor authorization and mutation validation succeed.
- Processing: the Post form and shared editor reader include section content only for translations that exist. The shared reader loads missing translation relationships before resolving content. Shared CRUD skips creating new translations when all declared meaningful fields are blank; schema-type defaults do not count as content. Existing translations continue accepting explicit updates.
- Postconditions: saving English content in the UK market does not create Spanish content or change market membership. Unequal localized section counts do not duplicate another language's blocks. Explicit empty section lists do not delete entity translations.

## 4. REST API & Passport OAuth2 Contract

Use existing Post form/controller, domain service and shared capability handlers. No route, OAuth scope or approval changes. The blank-translation guard applies to shared CRUD, protecting every transport.

## 5. Generative & Traditional SEO Specification

SEO fields remain attached to the actual translation. A schema type alone must not create a blank translated page or article with a generated commercial slug.

## 6. Test Invariant Checklist (TDD)

- [x] Real backoffice update of an English-only draft with empty Spanish placeholders preserves English-only entity and section translations.
- [x] Rendering its editor does not clone an English section into Spanish.
- [x] Shared editor and real MCP reads agree about actual translations, including sections loaded without eager-loaded translation rows.
- [x] Localized maps with unequal block counts persist only supplied pairs.
- [x] Existing translated entity content is preserved when another locale's section list is explicitly empty.
- [x] Existing translations accept explicit blank-field updates, and flat section lists retain compatibility.
- [x] Tests run only against a guarded ephemeral MySQL database.

Verification: the initial six-case suite had four failures before implementation. The final localized editor/reader suite passes seven tests and 30 assertions; together with front section editor tests it passes 12 tests and 42 assertions. Pint passes on changed PHP files; PHPStan Level 8 reports no errors in the three shared services. The front deep-link fixture now includes its real Spanish section translation, retaining its actual-ID assertion and additionally rejecting an invented English translation. Public rendering fallback remains unchanged.
