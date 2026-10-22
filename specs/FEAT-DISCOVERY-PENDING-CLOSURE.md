# Feature Specification: Public discovery integrity

> **Spec ID**: `FEAT-DISCOVERY-PENDING-CLOSURE`  
> **Status**: IMPLEMENTED  
> **Author**: Discovery specialist  

## 1. Domain Objective & Context
Public metadata follows translated published content, sitemap documents remain bounded, scheduled visibility changes invalidate cached discovery at the boundary, and modules can supply public semantic content through explicit contracts.

## 2. Algebraic Data Types & DTO Contracts
Sitemap partitioning accepts complete URL entries and returns bounded documents. Public semantic contributors declare their owning module and return text for an already authorized public market/locale. Widget contributors receive only a published block payload and must apply their domain visibility rules.

## 3. Formal Invariants
- A translation or published section edit updates lastmod/dateModified without creating a content approval or changing publication state.
- Each sitemap child contains at most 50,000 URLs and 50 MiB of uncompressed XML. Oversized single entries fail explicitly.
- Future publication never appears before its timestamp. Cached HTML and discovery observe the visibility boundary even if no scheduler runs.
- Disabled modules contribute no semantic text. No contributor can supply authority, credentials, or private draft data.

## 4. REST API & Passport OAuth2 Contract
Existing public read-only discovery routes remain compatible. Numbered child sitemap routes are additive. No write authority is added.

## 5. Generative & Traditional SEO Specification
Canonical sources remain in the corpus. Optional module/widget contributions are explicit, public and bounded. Search ranking and external citations are not asserted.

## 6. Test Invariant Checklist
- [x] Related content timestamps.
- [x] Sitemap count and byte partitions.
- [x] Scheduled boundary without writes or general TTL expiration.
- [x] Disabled semantic contributors excluded.
