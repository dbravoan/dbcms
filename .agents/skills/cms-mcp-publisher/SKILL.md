---
name: cms-mcp-publisher
description: Orchestrates multi-subagent research, natural anti-AI Spanish editorial writing, SEO/GEO auditing, and transnational publishing via dbCMS MCP tools.
---

# CMS MCP Publisher & Voice-to-Publish Orchestration Skill

## Goal
Enable autonomous agents and subagent teams to execute end-to-end publishing pipelines—from human voice/chat intent to live frontoffice articles, pages, or products—leveraging dbCMS Model Context Protocol (MCP) tools, anti-AI natural Spanish editorial heuristics, and atomic database transactions.

---

## The Voice-to-Publish Pipeline (DAG)

```
[Comando de Voz del Humano]
            |
            v
[Superagente Orquestador]
            |
  +---------+---------+---------+---------+
  |                   |                   |
  v                   v                   v
1. INSPECCIÓN      2. INVESTIGACIÓN   3. REDACCIÓN Y ANTI-IA
`cms_inspect_`      `cms_search_`       `cms_get_editorial_prompt`
`system`            `content`           `cms_validate_editorial_tone`
                    `cms_research_`     `cms_humanize_prose`
                    `topic_context`
  +---------+---------+---------+---------+
                      |
                      v
             4. AUDITORÍA GEO/SEO
              `cms_audit_seo_geo`
                      |
                      v
             5. PUBLICACIÓN ATÓMICA
              `cms_publish_post` / `page` / `product`
```

---

## Step-by-Step Standard Operating Procedure (SOP)

### Step 1: System State & Topology Inspection
Before creating content, always discover existing taxonomies, active markets, and locales:
- Call `cms_inspect_system`.
- Read active markets (e.g. `es`, `us`) and primary languages (`es`, `en`, `ca`).
- Identify target categories (e.g. `category_id: 1` para Tecnología).

### Step 2: Cross-Entity Research & Internal Link Discovery
Avoid duplicate content and build internal authority:
- Call `cms_search_content` with keyword query (e.g. `{"query": "inteligencia artificial", "entity_type": "post"}`).
- Call `cms_research_topic_context` with topic (e.g. `{"topic": "factura electrónica", "market": "es", "locale": "es"}`).
- Note suggested internal links (`/blog/post-slug`) to weave naturally into the article text.

### Step 3: Anti-AI Editorial Drafting & Humanization
Retrieve the official guidelines and enforce native Spanish prose:
1. Call `cms_get_editorial_prompt` to obtain current writing rules.
2. Draft title in **Spanish sentence case** (lowercase except first letter and proper names). Max 60 characters.
3. Vary sentence length (**burstiness**): alternate punchy 4-word sentences with 25-word compound explanations.
4. Include inverted punctuation (`¿?`, `¡!`).
5. Include structured H2 headings and a direct definition / FAQ block for AI engine citability (GEO).
6. **Zero AI clichés**: No "en el vertiginoso mundo de", "un tapiz de", "es crucial destacar", "en conclusión", etc.
7. Call `cms_validate_editorial_tone` with `{"title": "...", "content": "..."}`.
   - If `anti_ai_score < 85` or clichés are found: call `cms_humanize_prose` to rewrite flagged segments.
   - Repeat until `is_human_grade === true`.

### Step 4: SEO & GEO Quality Gate
- Call `cms_audit_seo_geo` with `{"title": "...", "content": "...", "meta_description": "...", "primary_keyword": "..."}`.
- Ensure `overall_score >= 80`. Verify title (40-60 chars) and meta description (120-160 chars).

### Step 5: Transactional Publication & Verification
- Call `cms_publish_post`:
  ```json
  {
    "title": "La factura electrónica obligatoria para pymes: calendario y claves",
    "content": "## Claves de la nueva normativa...",
    "summary": "Analizamos los plazos, requisitos técnicos y sanciones...",
    "locale": "es",
    "market_codes": ["es"],
    "category_id": 1,
    "is_published": true
  }
  ```
- Verify response: `success: true`, receive `post_id` and live `url`.
- Return clear human confirmation with preview link and quality metrics.
