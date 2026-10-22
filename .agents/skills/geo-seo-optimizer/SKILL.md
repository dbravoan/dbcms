---
name: geo-seo-optimizer
description: Generates dynamic Schema.org JSON-LD, OpenGraph tags, sitemaps, and /llms.txt for AI engines.
---

# GEO & SEO Optimizer Skill

## Goal
Maximize discoverability for both search engines (Google/Bing) and AI retrieval engines (Perplexity, Gemini, ChatGPT Search).

## Procedure
1. Read [GEO and SEO](../../../docs/05_GEO_AND_SEO_ENGINEERING.md) and
   [public contributors](../../../docs/GEO_PUBLIC_CONTRIBUTORS.md).
2. Use the owning public entity definition and shared discovery predicates for
   publication, availability, market and locale. Administrative entities stay private.
3. Extend `App\Services\Seo\JsonLdSchemaService` where the visible entity needs
   structured metadata. Optional semantic content registers through the existing
   `App\Core\Content\PublicSemanticRegistry`; widgets use its owned contributor contract.
4. Reuse `App\Services\Geo\LlmsTxtGeneratorService` and existing public routes;
   do not register duplicate endpoints or write static files into `public/`.
5. Verify eligible URLs, structured data, timestamps and disabled-owner behavior
   in the relevant isolated lane. External indexing and AI citation require
   separate observation; a local heuristic or valid schema guarantees neither.
