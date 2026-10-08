# Feature Specification: Gallery and search public presentation boundaries

> **Spec ID**: `FEAT-GALLERY-SEARCH-FRONT-DOMAIN`  
> **Status**: IMPLEMENTED  
> **Author**: Domain specialist  
> **Date**: 2026-10-12

## 1. Domain Objective & Context
Move public gallery and general search queries, canonical decisions and presentation assembly behind module-owned contracts. Existing URLs, views, pagination, facets, analytics, public isolation and metadata remain compatible.

## 2. Algebraic Data Types & DTO Contracts
Readonly transport input contains the resolved public market, locale, absolute URL and scalar request values. Readonly `FrontPageData` represents exactly one view, canonical redirect, or missing resource. Domain services return DTOs, never HTTP responses. Controllers translate those outcomes in at most ten action lines.

## 3. Formal Invariants
- Gallery reads only enabled module content available in the market and locale.
- Search keeps source visibility, friendly paths, first-page indexability, facets and pagination.
- No controller directly imports content models, search engines, indexers or analytics services.
- Unknown entity types and unavailable albums remain 404; canonical redirects remain 301.
- Existing front edit bars and metadata receive the same payload keys.

## 4. REST API & Passport OAuth2 Contract
Only existing public GET transports are adapted. No new write permission, URL or scope is introduced.

## 5. Generative & Traditional SEO Specification
Canonical URLs, hreflang, ImageGallery and SearchResultsPage remain emitted from shared SEO services. Article/product visibility is delegated to the existing search engine contract.

## 6. Test Invariant Checklist
- [x] Architecture regression rejects controller persistence and search orchestration.
- [x] Immutable service outcomes and module contract binding.
- [x] Existing gallery/search/public discovery functional regressions.
