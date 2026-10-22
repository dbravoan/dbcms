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
4. Semantic contributors produce text from the same available, published content
   as public rendering. This is not an embeddings or RAG pipeline.

Legacy editorial scores are labelled heuristics. They do not measure ranking,
factual accuracy or citations; the API explicitly reports `citations_measured=false`.

## Implementation Architecture

| Surface | Owner |
| --- | --- |
| Canonical, alternate and OpenGraph HTML tags | `SeoMetaService` and the shared SEO head |
| Schema.org JSON-LD and public site metadata API | `JsonLdSchemaService` and module-owned builders |
| Root sitemap index and segments | `SitemapIndexService` |
| Regional sitemap documents | `SitemapService` |
| Public llms index and corpus | `LlmsTxtGeneratorService` with public semantic contributors |
| Public document cache expiry/invalidation | `PublicDocumentCache` using the page publication boundary |

Invokable HTTP controllers delegate to these owners. See
[public contributors](GEO_PUBLIC_CONTRIBUTORS.md) for extending semantic output.

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
in those. The standard editorial forms address translations by locale, and
the `hreflang` cluster is built per market+locale pair
(`LocalizationService::buildHreflangCluster`).

```php
// One save persists the SEO of every language (the form posts them together).
$translationsInput[$code]['meta_title'] ?? null;
```

There is no dedicated per-market SEO override editor. Page/Blog translation
tables include nullable `market_id`, while product translations are unique by
entity/locale; do not infer a universal override-resolution contract from the
column alone. Editorial web forms submit nested
`translations[locale][field]` values. Flat historical field aliases are not part
of the contract for new installations.

Also geo-reactive: `/llms.txt` and the sitemap read the active markets and
locales from the database, so an active market/language association can enter the published surfaces without
a code change when matching public content exists; creation alone does not
translate content or make it public.
