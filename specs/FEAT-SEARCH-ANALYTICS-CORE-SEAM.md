# Feature Specification: Optional search analytics behind a core contract

> **Spec ID**: `FEAT-SEARCH-ANALYTICS-CORE-SEAM`  
> **Status**: IMPLEMENTED  
> **Author**: Domain specialist  
> **Date**: 2026-10-12

## 1. Domain Objective & Context
Core CMS actions must not import a Search module model. An optional core analytics contract preserves the existing statistics response while the module owns persistence and availability.

## 2. Algebraic Data Types & DTO Contracts
`SearchAnalyticsInterface::topTerms(?string $locale, int $limit)` returns a list of term/hits/results scalar records. A neutral implementation returns an empty list. The Search adapter reads stored counters only when its module is enabled.

## 3. Formal Invariants
- No module model or concrete analytics service is imported by core CMS actions.
- Disabled or absent Search contributes no stored term analytics.
- Locale filtering and existing response fields remain compatible; no authority or scope is expanded.

## 4. REST API & Passport OAuth2 Contract
The existing capability retains its authorization gate and payload format. The interface introduces no new public endpoint.

## 5. Generative & Traditional SEO Specification
No search-engine or AI performance score is invented. Analytics remain actual recorded counters.

## 6. Test Invariant Checklist
- [x] Neutral implementation requires no module or database.
- [x] Enabled adapter preserves scalar shape and locale filtering.
- [x] Disabled module returns no previous term data.
