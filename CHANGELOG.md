# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-10-13

### Added
- **Core Architecture**:
  - Enterprise-grade modular CMS architecture with dynamic modules in `modules/`.
  - Multi-market matrix with localized routing, multi-currency, and bidirectional language support (LTR/RTL).
  - In-memory reactive localization and brand resolution.
  - Zero-host PHP containment policy using Docker Sail and containerized execution wrapper.
- **Model Context Protocol (MCP)**:
  - Native MCP Streamable HTTP server on `POST /mcp/cms` powered by `laravel/mcp`.
  - Bidirectional JSON-RPC and SSE transports for AI coding agents and autonomous workflows.
  - Granular token scopes (`mcp:access`, `mcp:read`, `mcp:write`, `mcp:destructive`) audited via Passport.
- **AI Assistant**:
  - Decoupled universal inference supporting OpenCode, OpenAI, Anthropic, Gemini, Grok, and local LLMs.
  - Contextual modal, block generator, and page assistant.
- **Backoffice & Frontoffice Theming**:
  - Dynamic backoffice theme switcher with `dbcms` and `termocms` skins.
  - Accessible `<x-admin.*>` component library as single source of truth for UI logic.
  - Frontoffice themes (`db-cyber`, `db-brutalist`, `db-editorial`, `db-enterprise`, `db-minimal`, `db-sunset`, `default-clean`).
- **SEO & Generative Engine Optimization (GEO)**:
  - Dynamic `/llms.txt` and `/llms-full.txt` endpoints for AI crawler knowledge indexing.
  - Automated Schema.org JSON-LD graph generation (`Organization`, `WebSite`, `Article`, `Product`).
  - Segmented XML sitemaps with regional hreflang clusters and dynamic `robots.txt`.
- **Installation & Deployment**:
  - 5-step web installer wizard (`/install`) with live database test, auto-creation, and environment sealing.
  - Unattended CLI installer (`php artisan cms:install`).
  - Self-contained standalone ZIP distribution builder (`bin/build-release-zip.sh`).
  - Opt-in VPS/Plesk delivery with releases/current/shared, containerized CLI, maintenance, migrations, pinned SSH host verification, and HTTP health checks. Failed promotion can restore the previous code link; database changes are not automatically reverted.
  - Multi-stage hardened production Docker container (`docker/production/Dockerfile`).

### Changed and fixed
- Native CMS orchestration shares actor identity, capability discovery, authorization and domain execution with AI, MCP and jobs; persisted execution steps and human approvals prevent model-provided confirmations.
- AI entry points inherit editor language/market context; structured translations preserve content blocks and entity relationships. Missing providers report unavailability instead of simulated generations or invented research scores.
- Separate AI consumption/administration permissions, explicit OAuth service accounts, private-resource authorization and hardened upload/import handling.
- Checkout revalidates product availability, price and stock transactionally. Card/Stripe simulation is disabled; manual payments remain unpaid until an actual payment is recorded. Order access is restricted to owner/session or a signed guest link.
- Editorial review preserves private drafts, version-bound decisions and complete revision snapshots; notifications use persisted delivery state and current authorization.
- Module removal retains data; independent search/GEO contributors, public cache isolation and targeted invalidation reduce cross-module coupling and private-state leakage.
- Domain/read services and immutable DTOs replace oversized controller responsibilities; architecture regressions, strict static analysis and containerized builds verify these boundaries.
- Version tagging is performed explicitly after committing and validating the release. The deprecated `app:version --tag` option fails before changing version files.

Local and remote release evidence is tracked separately in [the publication validation](docs/RELEASE_VALIDATION_2026-10-13.md). Providers and deployments require their own configured connections; this release does not certify external hosting or search rankings.

[1.0.0]: https://github.com/dbravoan/dbcms/releases/tag/v1.0.0
