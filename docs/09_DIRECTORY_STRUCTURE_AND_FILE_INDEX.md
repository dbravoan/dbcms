# Directory and documentation index

Start with [Core and extensions](../specs/CORE_AND_EXTENSIONS.md), then
[TESTING.md](TESTING.md) for the verification lane. This index describes the
current development architecture. Dated verification reports live in
[archive/](archive/) and are historical evidence, not current guarantees.

## Source ownership

| Path | Responsibility |
| --- | --- |
| `app/Content/Pages` | Core pages, translations, publication and page routes |
| `app/Core/Capabilities` | Authorization, availability, catalog and shared execution |
| `app/Core/Modules` | Read-only discovery and explicit installation lifecycle |
| `app/Core/Blocks`, `block-packages` | Shared block/widget definitions and visual packages |
| `app/Core/Sections` | Shared section validation, synchronization and rendering |
| `app/Core/Content` | Entity schemas and optional editorial contract |
| `app/Core/Forms` | Shared field registry and rendering |
| `app/Core/Search`, `app/Core/Filters` | Search sources, indexing and filter grammar |
| `app/Services/Capabilities` | Entity reads, writes, translations and core domain actions |
| `modules/*/module.json` | Optional domain manifests and required dependencies |
| `modules/Mcp` | Protocol transports over canonical core capabilities |
| `modules/AiAssistant/Resources/prompts` | Editorial guidelines owned by AiAssistant |
| `modules/Orchestration` | AI plan interpretation; requires AiAssistant |
| `modules/Marketing` | Private artifacts and contracts required by marketing modules |
| `resources/js`, `resources/views` | Shared frontend behavior and Blade components |
| `themes`, `resources/views/themes/admin` | Public themes and backoffice brands |
| `database/migrations` | Direct schema for a new core installation |
| `tests/Pure`, `tests/Smoke`, `tests/Frontend` | Fast contracts, focused HTTP journeys and JS behavior |
| `tests/Support` | Explicit module compositions and small domain fixtures |
| `tests/Feature`, `tests/Regression`, `tests/Architecture` | Domain integration, regressions and dependency boundaries |
| `tests/Concurrency`, `tests/Release`, `tests/Projects` | Concurrent checkout and physical distribution verification |
| `bin/test-suite`, `bin/test-frontend.mjs` | Fast/integration/release selection and frontend artifact scope |
| `bin/ci-check`, `docker/ci` | Disposable quality runtime, audits, build and analysis |
| `docker/production` | Production image; creative tools and marketing workers are opt-in |
| `.github/workflows` | Exact-commit CI, release and deployment gates |
| `.agents`, `AGENTS.md` | Scoped contributor guidance and container execution rules |

Pages cannot be disabled as a module. Charts, KPI/ROI and BeforeAfter are visual
packages. Optional module registration does not write installation state or run
Composer/npm during an HTTP request.

## Current guides

- [Arquitectura y código mantenible de dbCMS](01_ARCHITECTURE_AND_CLEAN_CODE.md)
- [Document 02: Spec-Driven Design (SDD) & Formal Contracts](02_SPEC_DRIVEN_DESIGN_SDD.md)
- [Document 03: Token Economics, KV-Cache Dynamics & Attention Sinks](03_TOKEN_OPTIMIZATION_AND_KV_CACHE.md)
- [Document 05: Generative Engine Optimization (GEO) & Search Engine Optimization (SEO)](05_GEO_AND_SEO_ENGINEERING.md)
- [Document 06: Zero-Host PHP & Laravel Sail Containerization Runbook](06_ZERO_HOST_PHP_SAIL_RUNBOOK.md)
- [Document 07: Laravel Passport OAuth2 Specification & Security](07_PASSPORT_OAUTH2_SPECIFICATION.md)
- [Document 08: Laravel LTS Upgrade Watchdog Agent Specification](08_LTS_UPGRADE_AGENT_SPEC.md)
- [Document 09: Directory Structure & Architectural File Index (The "Why")](09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md)
- [Document 11: Guía Maestra para la Creación de Módulos Avanzados en dbCMS / termoCMS](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md)
- [AI Providers & Dynamic Model Catalog (`docs/12_AI_PROVIDERS_AND_MODEL_CATALOG.md`)](12_AI_PROVIDERS_AND_MODEL_CATALOG.md)
- [Admin UI Framework & Component Kit (`docs/13_ADMIN_UI_FRAMEWORK.md`)](13_ADMIN_UI_FRAMEWORK.md)
- [Localization & i18n (`docs/14_LOCALIZATION_AND_I18N.md`)](14_LOCALIZATION_AND_I18N.md)
- [15 · Front Section Access → Backoffice Editor](15_FRONT_SECTION_EDITING.md)
- [16 · Backoffice Theme Guide](16_BACKOFFICE_THEME_GUIDE.md)
- [17 · Front-office Theme Guide](17_FRONTOFFICE_THEME_GUIDE.md)
- [18 · Theme Manifest & Brand Reference](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md)
- [19 · Performance & Deployment Runbook](19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md)
- [20 · Front Filtering, Search & SEO-friendly URLs](20_FRONT_FILTERING_AND_SEO_URLS.md)
- [21 · Form Rendering & Field Types](21_FORM_RENDERING_AND_FIELD_TYPES.md)
- [22 · General Search (cross-entity, friendly URLs)](22_GENERAL_SEARCH.md)
- [23 · MCP Backoffice Parity (agent capabilities)](23_MCP_BACKOFFICE_PARITY.md)
- [24 · MCP: prueba de conexión real (paso a paso)](24_MCP_REAL_CONNECTION_WALKTHROUGH.md)
- [25 · Conectar una IA al CMS (guía de conexión e interacción)](25_CONNECTING_AI_AGENTS_OVER_MCP.md)
- [ADR: Fronteras de aplicación verificables](ADR_EXTRACTED_APPLICATION_BOUNDARIES.md)
- [Asistente IA en los editores](AI_EDITOR_ENTRYPOINTS.md)
- [Asistente IA en los editores y traducción de contenido](AI_EDITOR_TRANSLATION.md)
- [Mantener y extender dbCMS: arquitectura y decisiones](ARCHITECTURE_MAINTENANCE_GUIDE.md)
- [Auditoría y Calidad: operación editorial](CONTENT_AUDIT_WORKFLOW.md)
- [Registro del núcleo y las extensiones](CORE_EXTENSION_REGISTRATION.md)
- [Actualizaciones del núcleo dbCMS](CORE_UPDATES_RUNBOOK.md)
- [Guía Canónica de Versionado SemVer y Despliegue en dbCMS (`docs/DEPLOYMENT_AND_VERSIONING.md`)](DEPLOYMENT_AND_VERSIONING.md)
- [Despliegue de dbCMS: GitHub Actions, rsync, Ansistrano y Docker](DEPLOYMENT_GUIDE.md)
- [Crear, desplegar y actualizar proyectos derivados](DERIVED_PROJECTS_GUIDE.md)
- [Guía Canónica de Estándares, Convenciones y Clean Architecture en dbCMS](DEVELOPER_CONVENTIONS_AND_STANDARDS.md)
- [Contrato público de descubrimiento y corpus GEO](GEO_PUBLIC_CONTRIBUTORS.md)
- [dbCMS como plantilla de GitHub para proyectos independientes](GITHUB_TEMPLATE_REGULASE_RUNBOOK.md)
- [Instalación y configuración mediante servicios compartidos](INSTALLATION_AND_MODULE_SETTINGS_BOUNDARIES.md)
- [Laravel Support Assessment](LTS_UPGRADE_PROPOSAL.md)
- [Operación y piloto de CreativeStudio y SocialPublishing](MARKETING_MODULES_RUNBOOK.md)
- [Correo de módulos y subida de paquetes](MODULE_NOTIFICATION_AND_PACKAGE_SECURITY.md)
- [Orquestación nativa: activación y recuperación](NATIVE_ORCHESTRATION_RUNBOOK.md)
- [RegulaSE — propuesta del primer proyecto](REGULASE_PROJECT_BLUEPRINT.md)
- [Testing and quality gates](TESTING.md)

- [Core extension registration](CORE_EXTENSION_REGISTRATION.md): provider registration, owned contributions and the standalone core distribution.
