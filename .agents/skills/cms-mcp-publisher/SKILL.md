---
name: cms-mcp-publisher
description: Research and draft authorized dbCMS content through registered MCP capabilities, with editorial heuristics and human review before publication.
---

# CMS editorial workflow through MCP

Follow [connection and authority](../../../docs/25_CONNECTING_AI_AGENTS_OVER_MCP.md),
[capability contracts](../../../docs/23_MCP_BACKOFFICE_PARITY.md) and
[editorial review](../../../docs/CONTENT_AUDIT_WORKFLOW.md). This recipe uses the
live registered catalog; it does not install tools or grant publishing authority.
The pipeline has separate operations, not one transaction spanning research,
providers and publication. Owned related database writes remain transactional.

## Inspect the destination

Discover `tools/list` and, when available, `cms_capabilities`, `cms_entity_types`
and `cms_resource_types`. Read active markets/languages with authorized resource
reads and the entity’s taxonomy through its registered types. Confirm IDs and
locale; never assume that category 1 or market 1 belongs to the requested content.
Optional modules and permissions determine which tools are visible and executable.

## Research and prepare the draft

Use `cms_search` when Search is available, for example
`{"query":"arquitectura modular","type":"post","market":"es","locale":"es"}`.
With AiAssistant available, `cms_research_topic_context` searches the CMS public
index for internal links; it is not an external web-research service. Verify
external factual claims separately with suitable primary sources.

`cms_get_editorial_prompt` supplies the application’s current editorial guide.
Write Spanish headings in sentence case, use opening punctuation and vary sentence
rhythm naturally. `cms_validate_editorial_tone` accepts `title` and `content`;
`cms_humanize_prose` accepts `content` and an optional declared style. These are
heuristics/local text transformations, not a detector of authorship or a guarantee
of factual quality. Do not loop indefinitely to force a score. The reported
`is_human_grade` threshold is 85 with no detected clichés; configured editorial
enforcement and human review are separate controls.

`cms_audit_seo_geo` accepts `title`, `content`, optional `meta_description` and
`primary_keyword` with `manage:seo`. Inspect findings; its scores do not prove
indexing, citations, legal accuracy or source reliability.

## Save, review and request publication

Read the current `cms_entity_create` schema and entity fields. This illustrative
argument shape assumes a registered Blog post and a market ID already confirmed
by a read; replace all content and IDs before calling it:

```json
{
  "type": "post",
  "market_ids": [1],
  "translations": {
    "es": {
      "title": "Título revisado",
      "slug": "titulo-revisado",
      "summary": "Resumen revisado",
      "content": "## Contexto\nContenido respaldado por fuentes."
    }
  },
  "audit_action": "draft"
}
```

Inspect the operation result and use its entity ID for `cms_entity_get`. To request
publication, use `cms_entity_update` with the confirmed type/ID and `publish:true`
only when the user authorized that request. Present `execution_id`/`review_url`
when returned and wait for the human’s decision in the backoffice. `confirm:true`
and model-provided approval IDs do not approve anything. Editorial approval may
still be required independently of execution approval.

Verify final published state, eligible public URL and actual result before
reporting success. A draft save, quality score or HTTP 200 alone is insufficient.
Do not send messages or publish in external applications without user authorization.
