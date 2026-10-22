# 23 · MCP Backoffice Parity (agent capabilities)

> **Status**: Implemented (capability layer + entity & resource engines + platform actions)
> **Scope**: every module that registers entities, resources or actions
> **Related**: [`25_CONNECTING_AI_AGENTS_OVER_MCP.md`](25_CONNECTING_AI_AGENTS_OVER_MCP.md) (conectar cualquier IA) · [`24_MCP_REAL_CONNECTION_WALKTHROUGH.md`](24_MCP_REAL_CONNECTION_WALKTHROUGH.md) (prueba real paso a paso) · [`20_FRONT_FILTERING_AND_SEO_URLS.md`](20_FRONT_FILTERING_AND_SEO_URLS.md) · [`22_GENERAL_SEARCH.md`](22_GENERAL_SEARCH.md) · [`07_PASSPORT_OAUTH2_SPECIFICATION.md`](07_PASSPORT_OAUTH2_SPECIFICATION.md) · [`11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md`](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md)

---

## 1. The rule

**Registered capabilities expose CMS operations through shared services and
authorization.** The live catalog is the supported surface; a backoffice button
does not automatically create an MCP tool. Sharing services reduces duplication,
but does not prove universal screen parity or rule out future drift.

Two independent gates are enforced on every call:

| Gate | Question | Source |
| :--- | :--- | :--- |
| **OAuth ability** | may this *token* do it? | `mcp:read` / `mcp:write` / `mcp:destructive` (or a permitted `*` grant) |
| **Backoffice permission** | may this *user* do it? | the same permission the admin screen requires (`manage:shop`, `manage:seo`, `access:admin`…) |

Machine-to-machine tokens (`client_credentials`) require an explicit CMS service
account binding in `oauth_service_accounts` (client, user and allowed scopes).
The account has a chosen CMS role: OAuth scopes and current RBAC permissions
both apply. An unbound client is denied; the audit records user, token and client.

Publishing, deleting and external effects require approval of the concrete change
by a human in the backoffice. MCP returns `confirmation_required`, `execution_id`
and `review_url`; the agent presents that link. A model-supplied `confirm: true`
or `approval_id` grants no authority, and the API cannot approve executions.

---

## 2. Architecture

```
agent → MCP protocol adapter → common ExecutorCore (CapabilityExecutor)
                                │ availability: module + scope + RBAC
                                │ validate arguments and effect
                                ├ sensitive effect → persisted execution + human review
                                └ approved/safe operation → shared domain services + audit
```

Web, API, MCP and queued jobs use `App\Core\Capabilities`; transport adapters
share the same authorization engine. Consumers use the canonical classes
directly; historical module aliases have been removed.

| Piece | Responsibility |
| :--- | :--- |
| `App\Core\Capabilities\Contracts\CapabilityInterface` | capability name, schema, permission, ability and handler |
| `App\Core\Capabilities\CapabilityRegistry` | common registration, effect metadata and owning module |
| `App\Core\Capabilities\AvailableCapabilities` | live manifest filtered by identity, RBAC, scopes and active modules |
| `App\Core\Capabilities\CapabilityAuthorizer` | validates current identity, token/client revocation, service binding and permissions |
| `App\Core\Capabilities\CapabilityExecutor` | shared validation, authorization, sensitive-effect gate, execution and audit |
| `EntityReadService`, `EntityMutationService`, `EntityTranslationWriter` | Read and mutate available registered entities |
| `App\Services\Capabilities\ResourceCrudService` + `PlatformResourceCatalog` | validated platform resource operations |
| Core action catalogs and domain action services | Core non-CRUD operations; optional catalogs belong to their modules |
| `Modules\Mcp\Services\McpToolDispatcher` | protocol adapter; returns persisted human-review references |

### Engines instead of one tool per screen

* **Entities** come from the schema registry
  ([`EntitySchema`](../app/Core/Content/EntitySchema.php)): posts, pages,
  products, blog/shop categories, galleries, forms — and any entity a module
  registers later. One engine gives list/get/create/update/delete with
  translations, publishing flags, authorship, unique slugs and the canonical URL
  per locale.
* **Resources** come from `App/Services/Capabilities/PlatformResourceCatalog.php`: markets, languages,
  media, menus, menu items, notifications, users, SEO landings, modules and tokens.
  Optional modules register resources such as form submissions, orders and the
  editorial audit queue in their own catalogs. Each declares its permission,
  writable attributes, validation rules, searchable columns and which operations
  are allowed.
* **Actions** cover what is not CRUD: module lifecycle, theme activation, site
  settings, maintenance mode, storage connection, caches, search index,
  editorial approval, block sections, media upload, notification housekeeping,
  token revocation and the audit trail itself.

---

## 3. Capability catalog

Call **`cms_capabilities`** to get the live catalog with permissions and
abilities. Summary:

| Domain | Tools |
| :--- | :--- |
| Discovery | `cms_capabilities`, `cms_entity_types`, `cms_resource_types` |
| Content (any entity) | `cms_entity_list`, `cms_entity_get`, `cms_entity_create`, `cms_entity_update`, `cms_entity_delete` |
| Platform data | `cms_resource_list`, `cms_resource_get`, `cms_resource_create`, `cms_resource_update`, `cms_resource_delete` |
| Modules & themes | `cms_module_toggle`, `cms_module_install`, `cms_module_uninstall`, `cms_module_settings_update`, `cms_theme_activate` |
| Site | `cms_settings_get`, `cms_settings_update`, `cms_maintenance_get`, `cms_maintenance_toggle`, `cms_cache_purge` |
| Storage | `cms_connection_get`, `cms_connection_update`, `cms_connection_test` |
| Editorial | `cms_audit_approve`, `cms_audit_reject`, `cms_audit_rollback`, `cms_content_sections_get`, `cms_content_sections_sync` |
| Media & inbox | `cms_media_upload`, `cms_notifications_mark_all_read` |
| Search & SEO | `cms_search_reindex`, `cms_search_stats`, `cms_seo_filter_upsert` |
| Security | `cms_token_revoke`, `cms_mcp_audit` |

Existing tool names remain available when their module and actor permissions
allow them; their execution now passes through the common authority checks.

The standalone AI workbench takes language and market from the instruction,
without duplicate selectors. Entity assistants inherit the active translation
and selected markets from the editor. Generation requests opt in with
`context_source: prompt|editor`; editor requests also carry `editor_market_ids`.
Successful results include `resolved_context`, which the draft save must reuse.
An ambiguous, inactive or conflicting target returns `status: needs_input`
with a clarification question, before content generation or persistence.
Existing API/MCP requests without the new flag keep their explicit context.
Only a configured text model can interpret these instructions; concrete tools
remain callable with explicit arguments independently of a language model.

### What "parity" means in practice

Draft creation writes through the common entity engine. A request to publish
first creates an execution awaiting human approval; MCP cannot approve it. The
editorial workflow also applies after authorized execution. Common mutations finish
the revision after translations, blocks and relations have been synchronized;
observers also cover compatible direct saves. Index and cache changes run after
commit, so private drafts keep the current public representation and timestamp.
Core consumers use `EditorialWorkflowInterface`; the optional module supplies its
implementation. Its absence never grants authority to approve or restore content.
`cms_audit_reject` also requires human confirmation because rejecting a live
revision can unpublish it. See [the editorial runbook](CONTENT_AUDIT_WORKFLOW.md).

---

## 4. Adding a capability

**A new content entity** — declare it in the module's `EntitySchema` (with
`permission` and `urlPrefix`) and register it; the entity tools pick it up
immediately.

**A new platform resource** — core resources belong in `PlatformResourceCatalog`;
optional module resources belong in their owner’s catalog and resource registry.
This declaration fragment assumes an owned `Coupon` model and the import
`App\Core\Capabilities\Resources\CapabilityResource`:

```php
new CapabilityResource(
    key: 'coupon',
    label: 'Cupón',
    model: Coupon::class,
    permission: 'manage:shop',
    fillable: ['code', 'percent', 'expires_at'],
    rules: ['code' => ['required', 'string', 'max:32'], 'percent' => ['required', 'integer', 'min:1', 'max:100']],
    searchable: ['code'],
    allowCreate: true,
    allowDelete: true,
);
```

**A new action** — register it in the catalog of its owning domain or module,
with the owning service as handler. Declare authorization, effect and optional
dependencies. Core actions belong to platform administration, accounts, content
sections, media or filter landing services.

Catalog metadata stays beside its owner; `tests/Feature/Mcp/McpBackofficeCapabilitiesTest.php` proves the
contract end to end.

---

## 5. Safety rules (non-negotiable)

1. **Permission ceiling** — a capability can never require less than its admin
   screen (`permissionFor()` also covers per-argument checks, so the generic
   entity/resource engines resolve the permission of the requested type).
2. **Persisted human approval** for sensitive effects: the human reviews the
   exact arguments and diff at `review_url`. Transport booleans cannot approve
   an execution or bypass current permissions.
3. **Owned effects** — handlers delegate database and filesystem effects to domain
   services. Media upload validates type/size; file writes are legitimate owned
   effects, not an alternate raw-filesystem tool.
4. **Audit argument redaction** — the audit trail masks argument keys that
   contain `password`, `secret`, `token`, `api_key`…
5. **Audit attempts** — normal success, denial and failure branches attempt to log
   the acting user, token, client and redacted arguments. Audit persistence errors
   are caught and logged as warnings; a row and duration are not guaranteed for
   every branch, including pending remote operations.

---

## 6. Operación

```bash
# tokens
./vendor/bin/sail artisan cms:issue-mcp-token --user=1 --scopes=mcp:access,mcp:read,mcp:write,mcp:destructive
./vendor/bin/sail artisan cms:create-m2m-client --role=editor --scopes=mcp:access,mcp:read,mcp:write,mcp:destructive,m2m:service

# agentes
curl -X POST https://cms/mcp/cms -H "Authorization: Bearer <token>" \
     -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
     -d '{"jsonrpc":"2.0","id":1,"method":"tools/call",
          "params":{"name":"cms_entity_list","arguments":{"type":"post","per_page":5}}}'
```

`--role=editor` must identify an existing role with the requested permissions;
choose the minimum role explicitly. Provisioning never expands that role.

From the backoffice: **Administración → Servidor MCP & Agentes** issues an
OAuth token and documents the `/mcp/cms` endpoint and scopes. Complete MCP
`initialize` before using `tools/list` or `tools/call`; see the
[connection walkthrough](24_MCP_REAL_CONNECTION_WALKTHROUGH.md).

Audit reading: `cms_mcp_audit` (agent) or the `capability_audit_logs` table.

---

## 7. Acceptance checks

Run against an isolated fixture database with a valid Passport token. These are
expected contracts, not a fixed tool count or a transcript of this installation:

| Check | Result |
| :--- | :--- |
| `tools/list` | available capabilities only; count varies with modules, role and scopes |
| Create a post with two locales | draft, author = actor, unique slugs, `urls` per locale; publication requires human review |
| List/search entities, list markets | paginated, filtered |
| Toggle a module off/on | availability follows installation/activation and required approval |
| Sensitive effects | persist execution and return review link before mutation |
| Delete, including a model-supplied approval flag | returns `confirmation_required`, `execution_id`, `review_url`; no deletion before human approval |
| Read-only token trying to write | refused by the scope gate; inspect the returned denial |
| Write token of a user without `manage:modules` | refused by the permission gate |
| Machine client with full scopes | denied without explicit account binding and required role permissions |
| Audit trail | verify available success/denial/failure records and redacted arguments; persistence remains best-effort |
