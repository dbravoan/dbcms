# Feature Specification: Verified cross-distribution core updates

> **Spec ID**: `core-updates`
>
> **Status**: IMPLEMENTED (static review; acceptance requires isolated execution)
>

## 1. Domain Objective & Context
All descendants of public dbravoan/dbcms discover stable signed releases without credentials. Standalone installations can apply approved compatible updates in the browser
only when the target declares rollback compatibility, stays within the major
version and passes runtime provenance/hosting checks; otherwise they receive a
managed source proposal. The 3.0.1 baseline declares no rollback compatibility; 3.1.0 declares 3.0.1, and 3.1.1 declares 3.0.1 and 3.1.0 as published source origins; Git/CI installations export a reviewed source proposal. Templates and Composer create-project are copies, not core package dependencies. Preserve customization, secrets, data, module states and stable tags.

## 2. Algebraic Data Types & DTO Contracts
`ReleaseManifest` is an immutable validated signed payload; `App\\DTOs\\UpdateReleaseCandidate` is immutable public catalogue metadata shared by web and CLI. Schema 1 fields: `version`, `source_sha`, `min_php`, `extensions` (list), `supported_from` (list of exact versions), `rollback_compatible` (bool), `migrations` (`core`: list of paths, `modules`: map module identifier to list of paths), `distributions` (`source`/`runtime`: asset basename, sha256, size, files map relative path to sha256), and optional signed `next_keys` for authorized rotation. Detached signature JSON: `key_id`, `signature` (base64 RSA SHA256 over exact manifest bytes). Files are admitted by UpdatePathPolicy; runtime caches, private configuration/data and updater execution journal never enter replacement inventory.

`UpdatePreparationServiceInterface::prepare(string $version, int $actorId): array` creates a private run with id/status/plan_hash/conflicts/mode. `UpdateExecutionServiceInterface` exposes status, approve, advance, cancel. `UpdateCatalogServiceInterface` exposes status/check. Run IDs UUID; hashes SHA256; all public arrays have documented shapes. Root-owned path and private storage configured server-side, never supplied by model/request.

## 3. Formal Invariants
- Authenticated human with manage:updates, recent password confirmation before approval, CSRF. Approval binds actor, target, signed manifest, current file plan, backup.
- Signed official manifests only. No downgrade, unknown key, archive traversal/symlink, changed hash, duplicate entry, or unverified baseline.
- Three-way source comparison. Unknown baseline requires adoption. Local changes untouched when target unchanged; conflicts block replacement. Git projects produce source review exports, never mutate checkout via HTTP.
- Discovery is read-only except cache/notifications, TTL 12 hours. Errors retain last known status and never imply current. Core notification source independent of optional modules.
- Backups private, verified; unsupported consistent DB backup requires externally evidenced backup. No DB transaction across HTTP/file operations.
- Durable lock + journal, independent pre-Laravel recovery path; batch replacement resumes old/new hashes only. Barrier covers web/API/CLI/jobs. Host control failures block web application. Maintain fail-closed on uncertain migrations and recovery.
- Only pending core/installed-module migrations; no seeders, demo, reinstall, key generation or module activation. No automatic DB rollback. Version committed after fresh-process health verification.

## 4. REST & CLI Contract
Backoffice under configured admin path: GET updates, POST updates/check|prepare|adopt|confirm-password, GET updates/runs/{id}, POST updates/runs/{id}/approve|advance|cancel, GET updates/runs/{id}/proposal. Own run plus current manage:updates required. Responses: 403 denied, 409 conflict/not ready, 422 invalid input, 423 recent password required. Discovery outages are returned as catalogue state with the previous successful check; the update barrier returns 503. Core update application is intentionally not an OAuth/MCP/AI capability. The command is `cms:updates` with operations `check|prepare|adopt|status|approve|advance|cancel|proposal`; managed deployments preserve explicit human workflow. CLI approval is interactive and verifies the selected human account password. Each phase is advanced in a new invocation.

## 5. SEO/GEO
No updater pages, manifests, backups or journal in public search/sitemap/GEO. Public content unchanged. Update barrier sends private no-store 503 with Retry-After.

## 6. Test Invariants
- Stable public GitHub discovery, TTL, outage, deduplicated core notifications, permission and ownership.
- Authenticity, key rotation, downgrades, archive limits, SHA/inventory tampering.
- Three-way customization conflicts, dependencies, template origin independence, unknown legacy baseline.
- Real isolated MySQL backup and restore; external evidence; repeated/concurrent/interrupted apply and recovery without vendor.
- Migration uncertainty, installed modules only, no seeders, preserve APP_KEY/Passport/media.
- HTTP standalone update and managed review package; Apache root/public routing; dual admin themes.
- Mandatory local Pest, browser, PHPStan8, Pint, dependency audits and build; signed release preparation and exact-SHA quick CI before publication, under [v3 delivery](V3_DELIVERY.md).
