# Document 05: Generative Engine Optimization (GEO) & Search Engine Optimization (SEO)

## Verifiable SEO and semantic discovery

The CMS provides metadata, public content discovery and optional text surfaces
for compatible consumers. These features do not establish ranking, factuality
or observed AI citations. [Google's official guidance](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide)
applies normal search requirements to generative search and does not require or
use llms.txt for improved visibility.

1. Public HTML has canonical URLs, localized alternates and content metadata.
2. `/llms.txt` follows an [external proposal](https://llmstxt.org/), not an RFC;
   `/llms-full.txt` is this CMS's corpus endpoint for compatible consumers.
3. JSON-LD describes existing published data, without invented ratings or claims.
4. `ContextChunkingService` limits Unicode characters and estimates tokens as
   characters/4. It is not a model tokenizer or a verified embeddings/RAG system.

Legacy editorial scores are labelled heuristics. They do not measure ranking,
factual accuracy or citations; the API explicitly reports `citations_measured=false`.

## Implementation Architecture

```
                  +--------------------------------+
                  | Public HTTP Request / Crawler  |
                  +--------------------------------+
                                  |
            +---------------------+---------------------+
            |                                           |
            v                                           v
   Traditional Web Crawler                     AI Retrieval Engine
   (Googlebot, Bingbot)                       (Perplexity, Gemini, GPTBot)
            |                                           |
            v                                           v
+-----------------------+                   +-----------------------+
|  JsonLdSchemaService  |                   | LlmsTxtGenerator      |
|  - Schema.org Graphs  |                   | - /llms.txt           |
|  - OpenGraph / Meta   |                   | - /llms-full.txt      |
|  - Sitemap.xml        |                   | - ContextChunking     |
+-----------------------+                   +-----------------------+
```

## Schema.org Graph Integration
`SeoMetaService` and `JsonLdSchemaService` build the shared head. Module-owned
builders provide the published entity's data: Blog uses Article/BlogPosting/
NewsArticle with a real headline, available dates and public author name;
Gallery uses ImageGallery and existing ImageObject URLs/captions. Page uses
WebPage, Shop has Product metadata, and collections have CollectionPage/ItemList.
Site metadata can expose WebSite/Organization based on configured data. This is
not universal graph coverage for every model or a guarantee of rich results.

`PublicContentDiscoveryInterface` centralizes module, publication, market and
exact-translation visibility for sitemap, RSS, llms indexes/corpora and public
search queries. Root and regional corpora cover Page, Post, Product and Gallery;
embedded form blocks currently contribute their public title, not submissions.

## SEO is per language; the market decides visibility

The editorial SEO (`meta_title`, `meta_description`, `schema_type`) lives on the
**translation row** of each entity, exactly like the title, the slug and the
sections: one value per language, edited in the language tab of the form and
saved together with the rest of that language.

| Concern | Where it lives | Decides… |
| :--- | :--- | :--- |
| Title, slug, body, **SEO** | `*_translations.content_json` / row, per `locale` | *what* is published |
| Market association (`market_ids`) | pivot table (`market_page`, `market_post`…) | *where* it is published |

The resolver (`MarketScoped`) treats an entity with **no market assigned as
global** (visible everywhere) and an entity assigned to markets as visible only
in those. The same locale value is served in every market it is assigned to, and
the `hreflang` cluster is built per market+locale pair
(`LocalizationService::buildHreflangCluster`).

```php
// One save persists the SEO of every language (the form posts them together).
$translationsInput[$code]['meta_title'] ?? null;
```

No per-market meta overrides. Editorial web forms submit nested
`translations[locale][field]` values. Flat historical field aliases are not part
of the contract for new installations.

Also geo-reactive: `/llms.txt` and the sitemap read the active markets and
locales from the database, so a market or language created in the backoffice
enters the published surfaces without a code change.
