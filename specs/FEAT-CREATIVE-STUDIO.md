# Feature Specification: CreativeStudio con configuración guiada

> Spec ID: `creative-studio` · Status: IMPLEMENTED (static contract review; external paid pilot pending)

## 1. Objetivo y límites
Módulo administrativo opcional, inicialmente desactivado, para campañas desde contenido público autorizado o brief libre. Produce texto, imágenes, carruseles y vídeo corto con marca, revisiones inmutables y exportación privada. Reutiliza AiAssistant; no incorpora credenciales duplicadas, cuentas sociales, usuarios propios ni contenido público indexable.

## 2. Contratos y datos
`Modules\Marketing\DTOs\CreativePackage` readonly: packageId, revisionId, campaignId nullable, locale, market, text, destinationUrl nullable, ordered ArtifactDescriptor[], provenance, version=1; canonical fingerprint, toArray/fromArray. ArtifactDescriptor readonly: id, private relative path, sha256, mimeType, sizeBytes, width/height/durationSeconds nullable, metadata. GenerationReceipt readonly: operationId, state, provider, model, remote request ID, usage, estimated/confirmed cost in integer minor units, currency.

Campaign owns mode, objective, audience, locale/market, published-source reference and allowlisted context snapshot, dates and budget. Revisions seal copy, destination, ordered immutable files, visual-brand/template snapshot and provenance. New edits create revisions, never overwrite exported bytes. Module DTOs own inputs and services implement module contracts. A neutral CreativePackageProviderInterface allows authorized handoff without cross-module model imports.

## 3. Preconditions, mutations and postconditions
- Live module state, authenticated user, manage:creative-studio, required source/media/AI permissions, and valid active locale/market are checked on each operation and again by queued workers.
- API uses read:creative-studio/write:creative-studio enum scopes; MCP additionally uses canonical MCP scopes and RBAC. No remote operation creates human social approval.
- Source context includes only explicitly selected published editorial content and approved input media. Users, private submissions, unrelated datasets and credentials never enter prompts.
- Mutations and budget reservations use short transactions. Remote calls and rendering execute after durable intent outside transactions. Queue payloads carry IDs, not credentials.
- Paid generation is disabled without provider, configured estimate and budget. Reserve all provider calls atomically at installation and campaign levels; uncertain acceptance retains reservation and never resubmits blindly.
- One generation per campaign by default, installation concurrency configurable; atomic claims and durable checkpoints survive queue/cache failures. Duplicate jobs no-op without clobbering active work.
- Provider adapters remain AiAssistant-owned. Private output sink preserves format and receipt metadata. Only actual adapter capabilities appear; references cannot be advertised when unsupported.
- GD renders controlled image templates (1080x1080, 1080x1350); FFmpeg/FFprobe compose and inspect vertical 1080x1920 video (default 30s), fixed local inputs and bounded processes. Font/logo/template versions are sealed. Output dimensions/codecs come from actual file inspection.
- Streamed private artifact storage validates MIME and path; copies protect approved material from mutable CMS media. Authorized previews/downloads and ZIP export preserve JPEG/PNG/MP4 and include ordered manifest/text.
- Disable/uninstall pauses unstarted work and preserves records/files. Reenable requires explicit resume; absence of AiAssistant preserves history/manual composition/export.

## 4. Backoffice, REST and MCP
Canonical admin components and both brands; campaigns/workbench, immutable revisions, brand, generation status, budgets, previews and export. REST namespace /api/v1/creative-studio returns 202 for queued generation, 409 for stale/conflicting operation, 403 for missing permission, 422 for invalid input; no credential exposure. Own registered capabilities share application services and validation.

## 5. Tutorial requirement
Local versioned seven-step guides for all selectable providers (OpenAI, Gemini, Anthropic, DeepSeek, OpenCode, Higgsfield, Magnific, Ollama, vLLM, custom compatible) and media composition. Each has preparation, external account/project, API/models/billing, credentials, CMS connection, explicit test and maintenance/troubleshooting. Sources/date/version, exact supported modalities and real credential fields. Guides register in IntegrationGuideRegistry and remain readable under admin authentication when the module/provider is inactive or unconfigured. Persistent progress per user/install does not prove verification. No external fetch needed to view guides; paid sample generation is explicit and budgeted, separate from connection checks. AiAssistant remains the credential owner.

## 6. SEO and privacy
Campaigns/briefs/revisions/assets have no public discovery, sitemap, llms or JSON-LD contribution. Existing published source metadata remains unchanged.

## 7. Acceptance and tests
RED/GREEN Pest before domain implementation: activation defaults, guide access inactive, standalone export, permissions/scopes, authorized context, revision immutability, private streaming/import/export, image/video probing, concurrent budgets and claims, uncertain paid intent, durable recovery, module pause, theme parity. Full Sail Pest/PHPStan8/Pint and frontend build gate. Actual paid success requires configured credentials and a separately recorded controlled test, not HTTP fakes.

## Machine-readable contract
```json
{"feature_id":"creative-studio","title":"CreativeStudio con configuración guiada","dtos":[{"name":"CreativePackage","properties":{"revisionId":"string","artifacts":"list<ArtifactDescriptor>"}},{"name":"GenerationReceipt","properties":{"operationId":"string","state":"string","estimatedCost":"?int","confirmedCost":"?int"}}],"preconditions":["Active module and current RBAC/scopes","Paid operation reserved within configured budgets","Authorized selected public content"],"postconditions":["Immutable private revision and artifacts","Durable operation and truthful receipt","Tutorials accessible independently of configuration"],"test_invariants":["No paid blind resubmission","No cross-installation leakage","No private SEO exposure","No cross-module model dependency"],"passport_scopes":["read:creative-studio","write:creative-studio","ai:use"],"geo_metadata":{"public":false}}
```
