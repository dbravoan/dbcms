# Feature Specification: AI prompt and editor context

> **Spec ID**: `FEAT-AI-PROMPT-EDITOR-CONTEXT`  
> **Status**: IMPLEMENTED  
> **Author**: Superagent  

## 1. Domain Objective & Context

Remove language and market selectors from the standalone AI workbench. Users express the intended language and market in their instruction. An assistant invoked in an entity editor inherits the editor's active translation and selected markets; the entity form keeps its selectors. Resolve context through the shared AI execution path, not UI-only defaults.

## 2. Algebraic Data Types & DTO Contracts

Add a readonly module DTO and a context-resolution service contract. Generation requests opt in with `context_source: prompt|editor`. Editor requests can supply the current locale and selected market IDs. The resolver returns validated context or a clarification question. Successful results include `resolved_context` so preview and draft persistence use the same locale and market.

Compatibility: existing API/MCP requests that explicitly supply locale/market without the new flag retain their behavior. Identity, permissions, credentials and approvals never come from the model.

## 3. Formal Invariants

- Authorization and provider availability are checked before inference.
- Prompt resolution uses the selected text provider/model, with no silent fallback.
- Only active CMS languages and markets can be selected; the CMS validates model output.
- Without explicit prompt overrides, standalone requests use declared CMS/actor defaults and editors use their current context.
- Ambiguous, unavailable or conflicting editor targets request clarification before generation or persistence. Do not silently pick one of multiple explicit targets.
- Editor AI assistance never changes the entity's language tabs or market assignments.
- Draft save sends the exact context returned with the generated preview, including market; publication continues to require human approval.
- No HTTP inside a database transaction.

## 4. REST API & Passport OAuth2 Contract

Extend existing synchronous/queued generation contracts additively. Preserve routes, permission `use:ai` and existing OAuth restrictions. Clarifications carry structured status/question and are visible in queued polling as well as synchronous responses. The client can refine the prompt and retry; no content is persisted for an unresolved context.

## 5. Generative & Traditional SEO Specification

Generated locale/market context must agree with persisted translations and markets. This feature does not publish, change sitemaps, or imply SEO rankings. SEO generation inherits the same context rules.

The copilot and workbench show quality scores only when the result contains an explicit numeric audit score. Missing scores remain null and hide the quality label; resuming a task or recommending blocks never invents a score.

## 6. Test Invariant Checklist

- Standalone rendered workbench has no language/market selectors in either theme.
- Explicit English/UK prompt resolves to active `en`/`uk` and saves that context.
- Missing overrides preserve declared defaults; ambiguous or inactive targets produce a clarification.
- Editor actions inherit the active locale and selected markets; conflicting instructions cannot silently redirect the target.
- Permission denial prevents any resolver/provider HTTP call.
- Generation/preview/draft persistence agree on context.
- Legacy explicit requests remain compatible; jobs preserve context and clarification results.
- Missing audit scores stay null during generation and resumption; block counts cannot become quality scores. Valid measured zero scores remain visible.
- Verification runs in Docker, with database tests isolated from development.
