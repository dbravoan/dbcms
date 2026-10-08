---
name: geo-seo-optimizer
description: Generates dynamic Schema.org JSON-LD, OpenGraph tags, sitemaps, and /llms.txt for AI engines.
---

# GEO & SEO Optimizer Skill

## Goal
Maximize discoverability for both search engines (Google/Bing) and AI retrieval engines (Perplexity, Gemini, ChatGPT Search).

## Procedure
1. Create or update Schema.org generator methods in `App\Services\Seo\JsonLdSchemaService`.
2. Ensure new entities provide clean markdown representations in `App\Services\Geo\LlmsTxtGeneratorService`.
3. Add routes to `routes/web.php` or `routes/api.php` for `/llms.txt`, `/llms-full.txt`, and `/sitemap.xml`.
4. Validate structured JSON-LD against Schema.org standards.
