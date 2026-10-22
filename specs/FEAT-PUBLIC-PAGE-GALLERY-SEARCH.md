# Feature Specification: Public page and gallery search sources

> **Spec ID**: `FEAT-PUBLIC-PAGE-GALLERY-SEARCH`  
> **Status**: IMPLEMENTED  
> **Author**: Discovery specialist  

## 1. Domain Objective & Context
The original gap was missing page and gallery sources. The current implementation registers Pages from the core and Gallery from its optional module, shares published section semantics and rebuilds affected internal documents after committed changes.

## 2. Algebraic Data Types & DTO Contracts
Sources implement `SearchSourceInterface` and emit immutable `SearchDocument` per active market and language. A shared published-section service supplies the same text to semantic discovery and page search. Model observers carry entity identifiers to the existing indexer after commit.

## 3. Formal Invariants
- No missing locale or foreign market resource appears in results or facets.
- Draft sections are never indexed as public body content.
- Scheduled publication may be prepared in the internal index; the public engine revalidates current visibility and never returns it before the timestamp.
- Disabled module sources disappear from the registry and public results.
- Incremental indexing reads the final committed state, including audit approval, rollback and deletion.
- Observer parent identifiers use only the owning model's real foreign key; strict Eloquent models and partial records never read absent attributes.

## 4. REST API & Passport OAuth2 Contract
Existing search routes gain real `page` and `gallery` facets. No write authority is added.

## 5. Generative & Traditional SEO Specification
Documents carry canonical market/locale source URLs. Published block text is shared with GEO. Source labels identify pages and galleries without inventing ratings.

## 6. Test Invariant Checklist
- [x] Core Page source registered independently; Gallery available only while its module is enabled.
- [x] Published blocks and public album descriptions searchable.
- [x] Market/locale and schedule restrictions at the public engine.
- [x] Incremental source projection reflects translated changes.
- [x] Three writes in a batch cause one final entity projection.
- [x] A market context change rebuilds projections without inventing translations.
- [x] Failed replacement after a full batch preserves the previous index and original errors.
- [x] Page/Gallery observer identifiers remain valid with strict Eloquent models.
