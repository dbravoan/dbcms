# Document 09: Directory Structure & Architectural File Index (The "Why")

This document details the architectural rationale ("the WHY") behind every directory, module, config file, and markdown document in **dbCMS**.

```
dbcms/
├── .agents/                          # Antigravity agent configuration directory
│   ├── rules/                        # Contextual rules loaded hierarchical/on-demand
│   │   ├── 01_architecture_clean_code.md     # Mandates strict types, PHPStan lvl 8, immutability
│   │   ├── 02_thin_controllers_fat_services.md # Enforces thin controllers, fat domain services
│   │   ├── 03_tdd_and_pest.md                # Mandates TDD red-green cycle and Pest Arch
│   │   ├── 04_token_optimization_protocol.md # Implements KV-cache prefix invariance and small diffs
│   │   ├── 05_sail_zero_host_php.md          # Prohibits host PHP, forces Docker Sail execution
│   │   ├── 06_geo_and_seo_standards.md       # Enforces JSON-LD schemas and /llms.txt generation
│   │   └── 07_passport_oauth2_security.md    # Mandates OAuth2 scope verification and key isolation
│   └── skills/                       # On-demand multi-step runbooks (Progressive Disclosure)
│       ├── accessibility/             # WCAG 2.2 audit and remediation runbook
│       ├── cms-clean-architecture/    # Clean Architecture rules for the CMS (invokable controllers)
│       ├── cms-mcp-publisher/         # Research, anti-ai tone and transactional publishing skill
│       ├── cms-module-architect/      # Canonical scaffolding and decoupling guide for CMS modules
│       ├── domain-service-engineer/   # Transactional domain services and events
│       ├── frontend-design/           # Distinctive, production-grade frontend interface design
│       ├── geo-seo-optimizer/         # Schema.org graphs and /llms.txt generator runbook
│       ├── laravel-patterns/          # Laravel architecture patterns and production app recipes
│       ├── laravel-specialist/        # Generic Laravel 10+ feature recipes
│       ├── lts-upgrade-monitor/       # Laravel LTS advisories and safe migration proposals
│       ├── seo/                        # Search engine visibility and ranking runbook
│       ├── spec-driven-planner/       # Formal specs, DTO schemas and API contracts
│       ├── superagent-orchestrator/   # Meta-orchestrator for the subagent workflow
│       ├── tdd-craftsman/             # Failing Pest unit/feature/arch tests harness
│       └── token-economist/           # Token consumption audit and KV-cache optimization
│
├── .github/workflows/                # GitHub Actions automated CI/CD pipelines
│   ├── ci.yml                        # Calls containerized quality gate
│   ├── quality.yml                   # Isolated CI: audits, assets, Pint, PHPStan 8, Pest, production smoke
│   ├── deploy.yml                    # Opt-in VPS/Plesk releases, Docker CLI, strict health gate and symlink rollback
│   ├── release.yml                   # Tagged releases: exact-SHA CI proof, version sync, archives + multi-arch image
│   └── dependabot.yml                # Automated dependency update PRs
│
├── app/                              # Microkernel & Core Application Domain
│   ├── Console/Commands/             # Core Artisan CLI commands (app:version, make:cms-module, etc.)
│   ├── Contracts/                    # Dependency Inversion Principle (DIP) Interfaces
│   │   └── Services/                 # Domain Service Interfaces (Localization, Geo, Seo, Themes)
│   ├── Core/                         # CMS Core Engine (ModuleManager, Blocks, Widgets)
│   │   ├── Capabilities/             # Common execution, authorization and actor-filtered capability catalog
│   │   ├── Modules/                  # ModuleManager, BaseModuleServiceProvider, ModuleInterface
│   │   ├── Filters/                  # Friendly filter URLs: dimensions, parser, builder, facets, panel skin, SEO policy
│   │   ├── Forms/                    # Shared form renderer + field-type registry
│   │   └── Search/                   # Cross-entity search: documents, sources, indexer, ranked engine
│   ├── DTOs/                         # Immutable Data Transfer Objects (PHP 8.3 readonly class)
│   ├── Enums/                        # Backed Enums (OAuthScope, SchemaOrgType)
│   ├── Exceptions/                   # Semantic Domain Exceptions
│   ├── Http/                         # Presentation Layer (Thin Controllers, Middleware, Requests)
│   │   ├── Controllers/Admin/        # Core Backoffice controllers (Dashboard, Users, Markets, Media, Menus, Modules)
│   │   ├── Middleware/               # Auth, RBAC, ResolveMarketAndLocale, CachePublicPage, CanonicalModulePrefix
│   │   └── ViewComposers/            # Shared view logic (AdminLayoutComposer)
│   ├── Models/                       # Core Eloquent Models (User, Role, Market, Language, CmsModule)
│   ├── Providers/                    # Core Service Providers (AppServiceProvider, EventServiceProvider)
│   └── Services/                     # Core Domain Services (Localization, Geo, Seo, AntiAi, Theme)
│
├── bin/                              # Operational Tooling and Zero-Host Guard Scripts
│   ├── guard-host-php.sh             # Hard firewall preventing execution on host PHP
│   ├── sail-run                      # Safe bridge wrapper into the Docker Sail container
│   ├── ci-check                     # Container-only quality gate
│   ├── deploy-vps-release.sh         # Existing VPS/Plesk hosting, persistent shared data and code rollback
│   ├── vps-artisan.sh                # Container-only CLI for existing host web deployments
│   ├── deploy-container.sh           # Optional Docker hosting: explicit migrations and image promotion
│   └── check-lts-updates.sh          # Legacy support check tooling
│
├── config/front_filter_ui.php         # Neutral filter-panel skin tokens (themes override via filter.json)
├── docs/                             # Academic & Architectural Documentation (The "Why")
│   ├── 01_ARCHITECTURE_AND_CLEAN_CODE.md
│   ├── INSTALLATION_AND_MODULE_SETTINGS_BOUNDARIES.md
│   ├── GEO_PUBLIC_CONTRIBUTORS.md
│   ├── MODULE_NOTIFICATION_AND_PACKAGE_SECURITY.md
│   ├── CONTENT_AUDIT_WORKFLOW.md
│   ├── PENDING_CLOSURE_AND_QUALITY_VALIDATION_2026-10-12.md
│   ├── ARCHITECTURE_MAINTENANCE_GUIDE.md
│   ├── ADR_EXTRACTED_APPLICATION_BOUNDARIES.md
│   ├── 02_SPEC_DRIVEN_DESIGN_SDD.md
│   ├── 03_TOKEN_OPTIMIZATION_AND_KV_CACHE.md
│   ├── 05_GEO_AND_SEO_ENGINEERING.md
│   ├── 06_ZERO_HOST_PHP_SAIL_RUNBOOK.md
│   ├── 07_PASSPORT_OAUTH2_SPECIFICATION.md
│   ├── 08_LTS_UPGRADE_AGENT_SPEC.md
│   ├── 09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md
│   ├── 11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md
│   ├── 12_AI_PROVIDERS_AND_MODEL_CATALOG.md # Data-driven providers, dynamic model catalog & OpenCode
│   ├── 13_ADMIN_UI_FRAMEWORK.md      # Reusable backoffice component kit & conventions
│   ├── 14_LOCALIZATION_AND_I18N.md   # Backoffice & app localization (es/en), language switcher
│   ├── 15_FRONT_SECTION_EDITING.md   # Front-embedded block manager, drafts & sections API
│   ├── 16_BACKOFFICE_THEME_GUIDE.md  # Authoring backoffice themes (resources/views/themes/admin)
│   ├── 17_FRONTOFFICE_THEME_GUIDE.md # Authoring front-office themes (themes/), slugs & widgets
│   ├── 18_THEME_MANIFEST_AND_BRAND_REFERENCE.md # theme.json schema, brand resolution & tokens
│   ├── 19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md # Build, OPcache, caching, headers, sessions, permissions
│   ├── 20_FRONT_FILTERING_AND_SEO_URLS.md # Friendly, canonical, SEO-aware filter/search URLs (standard)
│   ├── 21_FORM_RENDERING_AND_FIELD_TYPES.md # Shared form renderer, field-type registry and captcha drivers
│   ├── 22_GENERAL_SEARCH.md          # Cross-entity search: engine, module, indexability, injection, WebMCP
│   ├── 23_MCP_BACKOFFICE_PARITY.md   # Agent capabilities: entities, resources, actions, gates and audit
│   ├── 24_MCP_REAL_CONNECTION_WALKTHROUGH.md # Reproducible MCP examples and human-review contracts
│   ├── 25_CONNECTING_AI_AGENTS_OVER_MCP.md # Connect any AI over MCP: transports, clients, prompts, behaviour
│   ├── prompts/                      # Editorial system prompts (ES + EN)
│   ├── DEVELOPER_CONVENTIONS_AND_STANDARDS.md # Canonical guide for human and AI developers
│   ├── DEPLOYMENT_AND_VERSIONING.md  # Canonical SemVer deployment and release guide
│   ├── NATIVE_ORCHESTRATION_RUNBOOK.md # Activation, RBAC, approvals, checkpoints and recovery
│   ├── MARKETING_MODULES_RUNBOOK.md  # Optional marketing activation, isolated workers, budgets, OAuth/GA4, recovery and pending Matrix pilot
│   ├── NATIVE_ORCHESTRATION_VERIFICATION.md # Verification evidence for the native engine and audit fixes
│   ├── CMS_RUNTIME_VERIFICATION_2026-10-10.md # Local Sail, browser and real MCP verification with screenshots
│   ├── CMS_MODULE_FUNCTIONAL_AUDIT_2026-10-11.md # Module matrix, QA entities, fixes, evidence and verification limits
│   ├── AI_EDITOR_ENTRYPOINTS.md      # Contextual text, block, media and cover entry points
│   ├── AI_EDITOR_TRANSLATION.md      # Structured entity translation, review, permissions and provider limits
│   ├── FINAL_SYSTEM_AUDIT_2026-10-12.md # Final isolated checks, backoffice coverage, defects and launch limitations
│   ├── GITHUB_TEMPLATE_REGULASE_RUNBOOK.md # Existing dbCMS repo template, clean RegulaSE bootstrap and updates
│   ├── CORE_UPDATES_RUNBOOK.md       # Signed core updates, adoption, backups, conflicts and recovery
│   ├── RELEASE_VALIDATION_2026-10-13.md # Release version regression, local evidence and GitHub publication checks
│   ├── RELEASE_1.2.0_INTEGRATION.md  # Integration provenance, immutable baselines, compatibility review and pending validation matrix
│   ├── REGULASE_PROJECT_BLUEPRINT.md # European quality/normative editorial scope, sources and launch gates
│   ├── MODULE_AUDIT_SECURITY_CONNECTIVITY_2026-10-11.md # Auth, tracking, connections and architecture evidence
│   ├── MODULE_AUDIT_SEO_GEO_AEO_2026-10-11.md # Discovery surfaces, localization, schemas and heuristic limits
│   ├── MODULE_AUDIT_SHOP_2026-10-11.md # Cart, checkout, signed orders, inquiries and widget contracts
│   ├── MODULE_AUDIT_FORMS_2026-10-11.md # Shared form mutations, localized fields and editorial revisions
│   ├── LTS_UPGRADE_PROPOSAL.md        # Generated annual-release support assessment; no implicit CVE audit
│   └── DEPLOYMENT_GUIDE.md           # VPS/Plesk hosting, Docker CLI and optional container runtime, persistent keys and rollback limits
│
├── mcp/                              # Model Context Protocol (MCP) server definitions
│   └── mcp_config.json               # Native tool definitions for external LLMs
│
├── modules/                          # Pluggable Autonomous Bounded Contexts (DDD)
│   ├── Pages/                        # [TRONCAL / CORE] Composite Page Builder & Root routing
│   │   ├── module.json               # Manifest with is_core: true, widgets, permissions
│   │   ├── Database/Migrations/      # Pages, PageTranslations, PageSections schemas
│   │   ├── Http/Controllers/Admin/   # Modular Backoffice controllers (Invokable Verbs)
│   │   ├── Models/                   # Page, PageTranslation, PageSection
│   │   ├── Routes/admin.php          # Autonomous admin routes (/admin/pages)
│   │   └── Services/                 # PageDomainService, SectionRendererService
│   ├── Ads/                          # AdSense auto-ads, banner placements and market targeting
│   ├── AiAssistant/                  # Multi-provider AI Copilot, prompt templates & universal field injection
│   ├── Analytics/                    # Google Analytics 4, Tag Manager and GDPR consent banner
│   ├── BeforeAfter/                  # Interactive before/after comparison slider
│   ├── Blog/                         # Multi-market Blog engine, categories, comments
│   ├── Captcha/                      # Dynamic SVG/math captcha with encrypted tokens
│   ├── Charts/                       # Animated KPIs, comparison stats and interactive charts
│   ├── ContentAudit/                 # Zero-knowledge quality review, revisions, diff engine
│   ├── Forms/                        # Dynamic Form Builder and leads inbox
│   ├── Gallery/                      # Multi-album media galleries
│   ├── Maintenance/                  # Site maintenance mode controller & live canvas simulator
│   ├── Mcp/                          # Protocol adapters over the shared capability executor
│   ├── Meta/                         # Meta Pixel and server-side CAPI lead dispatching
│   ├── Search/                       # General search module: hub, results, analytics and activation inbox
│   └── Shop/                         # Catalog, products, orders, shopping cart
│
├── resources/                        # Views, Blade Components, and Frontend Assets
│   ├── views/
│   │   ├── admin/                    # Core Backoffice views (dashboard, users, markets, modules)
│   │   ├── components/               # Reusable <x-admin.*> components and frontoffice admin bar
│   │   └── welcome-cms.blade.php     # Resilient clean install / microkernel landing page
│
├── routes/                           # Routing Definitions
│   ├── ai.php                        # Native MCP server (Streamable HTTP, laravel/mcp)
│   ├── api.php                       # OAuth2 REST API routes (v1)
│   ├── web.php                       # Core web routes, backoffice root, and dynamic market fallbacks
│   └── console.php                   # CLI task definitions (geo:generate-llms-txt, system:check-lts)
│
├── themes/                           # Multi-Theme Presentation Overrides (Cascading Resolution)
│   ├── db-cyber/                     # Neon cyber-minimalist flagship theme (default)
│   ├── db-editorial/                 # Editorial typographic theme
│   ├── db-brutalist/                 # High-contrast neo-brutalist theme
│   ├── db-minimal/                   # Ultra-clean minimalist theme
│   ├── db-enterprise/                # High-trust corporate SaaS theme
│   ├── db-sunset/                    # Obsidian glassmorphism theme with sunset gradients
│   └── default-clean/                # Neutral reference theme and safe fallback
│
├── tests/                            # Test-Driven Development Suite (Pest)
│   ├── Architecture/                 # Pest Arch tests enforcing layering rules and zero-host
│   ├── Feature/                      # Feature tests (Cms, Mcp, Geo, Install)
│   │   └── Cms/ZeroModulesCleanInstallTest.php # Clean install verification suite
│   ├── Unit/                         # Domain unit tests for Services and DTOs
│   ├── Regression/                   # Database-free policy and deployment contracts
│   └── Pest.php                      # Global test helpers and configuration
│
├── AGENTS.md                         # Universal Agent Rulebook and Orchestration Invariants
├── README.md                         # Canonical human+AI project documentation
├── VERSION                           # Canonical SemVer version string (e.g. 1.0.0)
└── composer.json                     # Locked dependencies (PHP 8.3, Laravel 13.x, Passport, Pest)
```
