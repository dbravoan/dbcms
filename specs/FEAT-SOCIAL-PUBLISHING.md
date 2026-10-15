# Feature Specification: SocialPublishing con configuración guiada

> Spec ID: `social-publishing` · Status: APPROVED · Date: 2026-10-13

## 1. Objetivo y límites
Módulo opcional inicialmente desactivado para carga manual/importación de CreativePackage, revisión humana, calendario, OAuth, envío fiable y resultados. Funciona sin CreativeStudio/AiAssistant/ContentAudit. Facebook páginas, Instagram Login profesional, LinkedIn orgánico y YouTube vídeo; TikTok exportación manual. Sin anuncios ni gestión central de instalaciones.

## 2. Contratos y estados
PublicationSnapshot readonly binds immutable revision, copy, ordered final artifact hashes, exact destination/UTM, provider metadata, connector payload version, stable verified account identity and authorization generation. SocialConnector owns capabilities, validate/prepare/publish/poll/reconcile/metrics with typed operation results. Connections encrypt/hide credentials; refreshed token does not replace approved identity. Revisions/approvals/attempts are immutable domain resources, never generic writable CRUD.

Delivery: scheduled -> preparing -> remote_processing/ready_to_publish -> publishing -> published; blocked/failed/cancelled/pending_confirmation are explicit outcomes. Cancellation request is independent from confirmed remote result. Persist per-phase mutation intent before provider calls, append receipts/attempts and retain correlation evidence. Duplicate jobs, stale leases or queue redelivery must not replay uncertain POSTs.

## 3. Preconditions, mutations and postconditions
- All services and workers check installation identity, live module state, current initiating-user permissions, connection/account permissions, valid approval and actual artifact hashes. Traffic destinations must still be public, correct locale/market and reachable.
- Human approval only from trusted web user session (not M2M/API/MCP or client-origin flag); reviewer authorized for approve:social-publishing. It records exact payload hash and stable account identity. Edits revoke pending approval; change of time alone may reschedule valid approval.
- APIs use typed read:social-publishing/write:social-publishing/manage:social-accounts; MCP respects common MCP abilities and matching RBAC. Normal scheduling of an already approved payload is a reversible local write, never an approval shortcut.
- Manual input and imported package use same artifact validation/storage, copy private immutable files into social ownership and preserve source provenance without model dependencies.
- Short transactions seal snapshots, schedule and durable outbox/claims. HTTP outside DB transactions. Each destination has independent result and operation uniqueness.
- Explicit OAuth state/PKCE where applicable, callback URL derived from current installation, minimum grants, account discovery and selection, encrypted refresh and revocation; secret changes invalidate connection verification. API listing no secret or access/refresh token.
- Retry reads safely with bounded backoff. Retry writes only with authoritative nonacceptance or supported idempotency. Timeout, 5xx, missing ID, worker crash after persisted mutation intent -> pending_confirmation; owned but uncorrelated remote material is insufficient proof.
- YouTube resumable transfer, processing and public visibility distinct. LinkedIn uses organic multi-image, not ad carousel. Meta account-effective capabilities validated before publication. TikTok export-only must not demand unused credentials or claim real publication.
- Cancel before mutation claim blocks send. Afterwards stop subsequent writes and reconcile already-started effects; confirmed publication remains published. Disable/uninstall pauses unstarted work, permits safe reconciliation of prior intent, retains data; reactivation cannot send overdue backlog automatically.
- UTC scheduling displays installation timezone, rejects nonexistent local times and disambiguates repeated daylight-saving time.
- UTM frozen before approval; social metric snapshots include source/window/freshness and missing values stay unavailable. Optional neutral CampaignAttributionGateway imports GA4 sessions/key events with configured property/readonly credentials and current consent instrumentation, no private form payloads.

## 4. Backoffice, REST and MCP
Admin screens accounts, manual/imported drafts, exact preview/review, approve-and-schedule, calendar and outcomes. x-admin components both brands, keyboard accessible. REST /api/v1/social-publishing exposes preparation/scheduling/cancel/results but no approval. Module-owned MCP capabilities delegate same services. OAuth and credential-management action permissions separate from editorial management. Errors 401/403/409/422, async 202, unavailable capabilities explicit, no false success.

## 5. Tutorials
Local versioned seven-step Spanish guides Facebook/Instagram/YouTube/LinkedIn/TikTok/GA4 with actual developer portals, external account/application/products, exact connector scopes/callbacks, client credentials, CMS authorize/select, explicit approved test and maintenance. Instagram Login must not mix Facebook Login instructions. TikTok teaches app registration/API limitations AND operational manual-export flow without pretending direct publication is enabled. Guides always open for authorized admins even absent credentials, disabled module/provider, failed connection. Static local sources/date/version; no runtime external/AI fetch. Guide progress per user/install is informational, not verification. Each selectable integration has tutorial/config/check actions; failures deep-link to relevant guide step. Connection checks do not post or incur generation charges.

## 6. SEO/security and rollout
No campaigns/approvals/credentials/private files in public content search/sitemaps/GEO. Additive migrations preserve existing installations; initial default_active false. Separate delivery/reconciliation workers + scheduler, per-installation Redis namespaces and artifact roots. Pilot backup/restoration and exact release SHA; current hosting preserved. Matrix preserved before retirement; no retirement of active dependencies until real pilot passes.

## 7. Acceptance and tests
Pest RED/GREEN for module defaults/standalone operation/inactive guide availability/all tutorial coverage, OAuth state/account swap/refresh/revoke, human-only approval/stale snapshot/hash/ordered files, scopes and revoked RBAC, due scheduler/outbox/concurrent duplicate claims, uncertain provider side effects and safe reconciliation, cancellation races, processing/visibility and format capabilities, private artifacts, UTM and unavailable metrics, timezone and module pauses, both themes. Connector HTTP fake tests prove contracts only; operational status requires controlled confirmed publication with authorized accounts. Pilot uses tutorials alone; paid/API credentials are not presently supplied.

## Machine-readable contract
```json
{"feature_id":"social-publishing","title":"SocialPublishing con configuración guiada","dtos":[{"name":"PublicationSnapshot","properties":{"revisionId":"string","payloadHash":"string","accountIdentity":"string"}},{"name":"SetupGuideDescriptor","properties":{"id":"string","steps":"list<GuideStep>"}}],"preconditions":["Current permissions and account authorization","Persisted exact human approval","Valid immutable final assets and public traffic destination"],"postconditions":["Independent truthful result per destination","Durable external intent before mutations","Safe uncertain reconciliation before retry","Tutorials always available"],"test_invariants":["API/MCP cannot approve","No duplicate uncertain writes","Cancellation preserves published evidence","Manual standalone input","No cross-installation leakage"],"passport_scopes":["read:social-publishing","write:social-publishing","manage:social-accounts"],"geo_metadata":{"public":false}}
```
