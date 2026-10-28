# Directory and documentation index

Start with [AGENTS.md](../AGENTS.md), [Core and extensions](../specs/CORE_AND_EXTENSIONS.md),
then [conventions](DEVELOPER_CONVENTIONS_AND_STANDARDS.md) and
[TESTING.md](TESTING.md). The architecture contract governs behavior; conventions
explain implementation, area guides explain operation, and TESTING owns verification. This index describes the
current v3 architecture. Delivery evidence must match the exact source SHA,
as defined in [v3 delivery](../specs/V3_DELIVERY.md).

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
| `app/Services/Projects` | Verified source preparation, frozen composition and local environment planning |
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
| `Makefile`, `bin/project-init`, `bin/project-local-start` | Guided child creation and official Sail startup; no application provisioning |
| `bin/test-suite`, `bin/test-frontend.mjs` | Fast/integration/release selection and frontend artifact scope |
| `bin/ci-check`, `docker/ci` | Disposable quality runtime, audits, build and analysis |
| `docker/production` | Production image; creative tools and marketing workers are opt-in |
| `.github/workflows` | Exact-commit CI, release and deployment gates |
| `.agents`, `AGENTS.md` | Scoped contributor guidance and container execution rules |

Pages cannot be disabled as a module. Charts, KPI/ROI and BeforeAfter are visual
packages. Optional module registration does not write installation state or run
Composer/npm during an HTTP request.

For implementation walkthroughs, use [creating modules](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md)
for domain behavior and module-owned blocks, or
[creating blocks and visual packages](26_CREATING_BLOCKS_AND_VISUAL_PACKAGES_GUIDE.md)
for reusable visual components. A module can contribute several blocks; a block
does not require a separate business module.

## Current guides

### Use the CMS

- [Editar y publicar páginas, bloques, medios y menús](USING_THE_CMS.md)
- [Idiomas y mercados](14_LOCALIZATION_AND_I18N.md)
- [Acceso público al editor de secciones](15_FRONT_SECTION_EDITING.md)
- [Asistente IA: texto, medios y traducción](AI_EDITOR_TRANSLATION.md)
- [Revisión y aprobación editorial](CONTENT_AUDIT_WORKFLOW.md)
- [Formularios, campos y validación](21_FORM_RENDERING_AND_FIELD_TYPES.md)
- [Conectar una IA mediante MCP](25_CONNECTING_AI_AGENTS_OVER_MCP.md)
- [Comprobar una conexión MCP real](24_MCP_REAL_CONNECTION_WALKTHROUGH.md)
- [CreativeStudio y SocialPublishing](MARKETING_MODULES_RUNBOOK.md)

### Develop and extend

- [Arquitectura del código](01_ARCHITECTURE_AND_CLEAN_CODE.md)
- [Especificar un cambio](02_SPEC_DRIVEN_DESIGN_SDD.md)
- [Convenciones de desarrollo](DEVELOPER_CONVENTIONS_AND_STANDARDS.md)
- [Mantenimiento y decisiones de arquitectura](ARCHITECTURE_MAINTENANCE_GUIDE.md)
- [Fronteras de aplicación](ADR_EXTRACTED_APPLICATION_BOUNDARIES.md)
- [Registro y ciclo de extensiones](CORE_EXTENSION_REGISTRATION.md)
- [Crear módulos funcionales y bloques de módulo](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md)
- [Crear bloques y paquetes visuales](26_CREATING_BLOCKS_AND_VISUAL_PACKAGES_GUIDE.md)
- [Referencia de campos del editor de bloques](27_BLOCK_EDITOR_FIELD_REFERENCE.md)
- [Kit e inventario administrativos](13_ADMIN_UI_FRAMEWORK.md), [superficies](BACKOFFICE_UI_INVENTORY.md)
- [Temas de backoffice](16_BACKOFFICE_THEME_GUIDE.md), [frontoffice](17_FRONTOFFICE_THEME_GUIDE.md) y [manifiestos](18_THEME_MANIFEST_AND_BRAND_REFERENCE.md)
- [Filtros y URLs](20_FRONT_FILTERING_AND_SEO_URLS.md), [búsqueda general](22_GENERAL_SEARCH.md)
- [SEO técnico](05_GEO_AND_SEO_ENGINEERING.md) y [descubrimiento público/GEO](GEO_PUBLIC_CONTRIBUTORS.md)
- [Proveedores y modelos IA](12_AI_PROVIDERS_AND_MODEL_CATALOG.md), [integrar un editor](AI_EDITOR_ENTRYPOINTS.md)
- [Capacidades MCP y autorización](23_MCP_BACKOFFICE_PARITY.md)
- [Servicios compartidos de instalación y ajustes](INSTALLATION_AND_MODULE_SETTINGS_BOUNDARIES.md)
- [Correo y paquetes de extensiones](MODULE_NOTIFICATION_AND_PACKAGE_SECURITY.md)
- [Contexto útil para agentes](03_TOKEN_OPTIMIZATION_AND_KV_CACHE.md)

### Operate and deliver

- [Sail y bootstrap sin PHP local](06_ZERO_HOST_PHP_SAIL_RUNBOOK.md)
- [Crear, personalizar y actualizar aplicaciones hijas](DERIVED_PROJECTS_GUIDE.md)
- [Desplegar y recuperar cada perfil](DEPLOYMENT_GUIDE.md)
- [Preparar y publicar una entrega](DEPLOYMENT_AND_VERSIONING.md)
- [Actualizar el núcleo](CORE_UPDATES_RUNBOOK.md)
- [Rendimiento, caché y seguridad del runtime](19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md)
- [Passport y OAuth2](07_PASSPORT_OAUTH2_SPECIFICATION.md)
- [Soporte del framework y dependencias](08_LTS_UPGRADE_AGENT_SPEC.md)
- [Orquestación y recuperación de tareas](NATIVE_ORCHESTRATION_RUNBOOK.md)
- [Verificación y aislamiento](TESTING.md)
