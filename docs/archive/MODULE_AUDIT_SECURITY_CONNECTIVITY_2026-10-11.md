# Auditoría de seguridad y conexiones — 11 de octubre de 2026

> Historical evidence. This records the behavior and checks of its stated date; current contracts live in [Core and extensions](../../specs/CORE_AND_EXTENSIONS.md) and [Testing](../TESTING.md).

La aplicación local responde correctamente a las rutas administrativas revisadas y a los dos transportes MCP. Se corrigieron seis grupos de problemas confirmados. Las conexiones con proveedores externos **no están verificadas en vivo**: las comprobaciones de presencia de credenciales devolvieron falso y no se enviaron solicitudes a LLM, almacenamiento remoto, Google o Meta.

## Alcance y evidencia

Código revisado: autenticación Passport, scopes/RBAC compartidos, MCP nativo y legacy/SSE, IA, conexiones, Captcha, Maintenance, Analytics, Ads y Meta. Ejecución PHP/Node exclusivamente dentro de Sail. Las regresiones usan PHPUnit puro, dobles HTTP y SQLite en memoria; no se ejecutaron migraciones ni `RefreshDatabase` contra la base del sitio.

Evidencias privadas con permisos 0600:

- [Resultados HTTP y descubrimiento MCP](/home/david/Proyectos/webs/dbcms/storage/app/runtime-verification/module-audit-20261011/security-connectivity-runtime.json).
- [Inventario de configuraciones HTTP](/home/david/Proyectos/webs/dbcms/storage/app/runtime-verification/module-audit-20261011/module-settings-http.json).
- [Probe MCP y permisos](/home/david/Proyectos/webs/dbcms/storage/app/runtime-verification/module-audit-20261011/probe-security.php).
- [Probe GET de configuraciones](/home/david/Proyectos/webs/dbcms/storage/app/runtime-verification/module-audit-20261011/probe-module-settings.php).

Los resultados iniciales de replay y escape conservan el estado anterior a las correcciones para mantener evidencia reproducible. El estado corregido se verifica con las regresiones indicadas abajo. No se guardaron JWT, cookies de sesión, contraseñas ni HTML administrativo en estos resultados. El cliente y los tokens temporales de esta auditoría fueron revocados exclusivamente por sus identificadores propios. Se conserva el usuario QA sin roles, ID 2; su contraseña aleatoria no fue expuesta.

## Peticiones HTTP reales

| Comprobación | Resultado |
| --- | --- |
| MCP nativo anónimo, `POST /mcp/cms` | 401 |
| MCP legacy anónimo, `POST /api/mcp` | 401 |
| SSE y catálogo legacy anónimos | 401 |
| `tools/list` autenticado, nativo y legacy | 200; 50 herramientas en el momento del probe |
| `cms_entity_types`, `cms_resource_types`, `cms_capabilities` | 200 |
| Consulta de conexiones y mantenimiento por MCP | 200; mantenimiento desactivado |
| Generación IA anónima | 401 |
| Generación IA con token administrativo sin scope `ai:use` | 403 |
| Usuario QA sin roles pide scope MCP en login API | 422; no se concede elevación |
| Workbench IA con sesión del usuario QA | 403 |
| Conexiones con sesión QA | 302 al login administrativo |
| SSE autenticado y mensaje del token propietario | 200 / 200 |
| Mensaje SSE con otro token del mismo usuario | 403; aislamiento por token |
| JWT revocado reutilizado contra MCP nativo | 401 |
| 16 páginas `/control/modules/{identifier}/settings`, sesión admin | 16 de 16 con 200 |
| Página pública `/es/es` después de cambios | 200 |

Módulos de las 16 páginas: ads, ai_assistant, analytics, before_after, blog, captcha, charts, content_audit, forms, gallery, maintenance, mcp, meta, pages, search y shop. Sólo se registraron estado, ruta y tamaño de respuesta. El cierre de la sesión del probe mediante POST con CSRF devuelve 302.

La inicialización MCP moderna corresponde a `POST /mcp/cms`. El JSON-RPC legacy conserva `POST /api/mcp`, con SSE en `/api/mcp/sse` y mensajes en `/api/mcp/message`. La autenticación va por encabezado Bearer; no por query token. Los permisos efectivos combinan scopes OAuth con permisos del actor y disponibilidad del módulo.

## Correcciones confirmadas

| Grupo | Problema y escenario anterior | Corrección y evidencia |
| --- | --- | --- |
| P2 — replay Captcha | Una respuesta correcta podía reutilizarse durante los diez minutos de vigencia del desafío para múltiples envíos. | Consumo atómico del hash del token en caché hasta su expiración; respuestas incorrectas no consumen el desafío y fallos de caché rechazan la verificación. [CaptchaService](/home/david/Proyectos/webs/dbcms/modules/Captcha/Services/CaptchaService.php:114). |
| P1 — descubrimiento legacy | `cms_list_modules` serializaba `settings` completos. Un actor con permiso de lectura podía recibir configuración privada, incluidos secretos guardados por versiones anteriores. | Consulta con lista explícita de metadatos; no selecciona ni devuelve `settings`. Prueba SQLite con secretos falsos en texto y cifrados. [LegacyCmsService](/home/david/Proyectos/webs/dbcms/app/Services/Capabilities/LegacyCmsService.php:155). |
| P2 — escape Analytics/Ads | IDs de GA4 y atributos de anuncios se interpolaban sin escape contextual; un valor malicioso podía inyectar JavaScript o atributos HTML. | Validación de formatos de IDs y codificación JSON/HTML; sin credenciales/IDs válidos no se generan cuentas de ejemplo. [AnalyticsManagerService](/home/david/Proyectos/webs/dbcms/modules/Analytics/Services/AnalyticsManagerService.php:14), [AdSenseManagerService](/home/david/Proyectos/webs/dbcms/modules/Ads/Services/AdSenseManagerService.php:20). |
| P2 — token y logs Meta | El token CAPI iba en la URL; el mensaje de una excepción podía volver a incluirlo en los logs. | Token sólo en cuerpo POST, timeout de 15 segundos, sin redirects y registro de clase de excepción sin mensaje ni datos del evento. [MetaCapiService](/home/david/Proyectos/webs/dbcms/modules/Meta/Services/MetaCapiService.php:47). |
| P2 — disponibilidad de seguimiento | CookieConsent consultaba únicamente `is_active`; el ajuste `is_enabled=false` no eliminaba el seguimiento Analytics de la vista. | Delegación al `ModuleManager` común. Prueba real en SQLite de ausencia de ajuste, false/0/'0'/'false', true, desinstalación y desactivación. [CookieConsentService](/home/david/Proyectos/webs/dbcms/app/Services/Cms/CookieConsentService.php:17). |
| P2 — CSP y revocación de consentimiento | CSP bloqueaba todos los colectores externos; tras cargar GA4, revocar consentimiento no deshabilitaba la propiedad. | Contributor registrado por el módulo Analytics, mediante contrato core, añade sólo hosts exactos con módulo habilitado e ID válido. Sin consentimiento no se carga GA4; revocarlo activa `ga-disable-ID`. [Ga4CspContributor](/home/david/Proyectos/webs/dbcms/modules/Analytics/Services/Ga4CspContributor.php:15), [SecurityHeaders](/home/david/Proyectos/webs/dbcms/app/Http/Middleware/SecurityHeaders.php:87). |

El transporte POST del token sigue el [contrato del SDK oficial de Meta](https://github.com/facebook/facebook-nodejs-business-sdk/blob/main/__tests__/event-request.test.js). La CSP sigue el modelo de [Google para Analytics sin funciones Ads](https://developers.google.com/tag-platform/security/guides/csp#google_analytics), concretando hosts y evitando comodines. Las funciones Google Signals y personalización publicitaria están deshabilitadas en la configuración emitida. La allowlist cubre colectores globales y UE; futuros cambios de endpoints requieren revisión. En el runtime actual sin GA configurado, el encabezado continúa siendo `connect-src 'self'` y los orígenes de Vite local.

También se corrigió el selector de idioma por defecto al abrir/reabrir el editor de mercados, que antes conservaba un valor vacío o una selección sin guardar. [Vista de mercados](/home/david/Proyectos/webs/dbcms/resources/views/admin/markets/index.blade.php:427). Se añadieron mensajes de validación legibles en español y fallback inglés para evitar textos literales como `validation.required`.

## Estado de proveedores y capacidades

| Conexión | Observado | Límite de verificación |
| --- | --- | --- |
| IA | Diez definiciones: gemini, openai, deepseek, anthropic, custom, opencode, ollama, vllm, magnific, higgsfield. Presencia de credencial, configuración y endpoint: falso para todas. | No generación real ni validación de cuenta remota. Los proveedores elegidos por modalidad no implican una conexión configurada. |
| Almacenamiento externo | Local; conexión externa deshabilitada y no configurada; presencia de ambas credenciales: falso. | No upload/readback remoto S3 ni pruebas de permisos del proveedor. |
| Captcha | Driver efectivo interno; credenciales externas ausentes. | reCAPTCHA/Turnstile no comprobados con proveedores reales. |
| Analytics | GA4 y GTM sin ID configurado. | Renderer GA4 y consentimiento probados con fixtures; no recepción remota de eventos. |
| Ads | Publisher sin configurar. | Renderer aislado seguro; no integración frontend completa. |
| Meta | Pixel y token sin configurar. | Contrato HTTP/log probado con `Http::fake`, no entrega CAPI real. |
| Maintenance | Módulo disponible, modo mantenimiento desactivado. | No se activó el bloqueo global durante la navegación del usuario. |

En el momento del probe el catálogo visible ofrecía ocho entidades: post, blog_category, form, gallery, product, shop_category, shop_inquiry y page. Recursos: audit, form_submission, language, market, media, menu, menu_item, module, notification, order, seo_filter, token y user. Son catálogos filtrados por permisos del actor, no un inventario de todas las tablas.

El hueco inicial de paridad Forms — campos del constructor fuera del handler compartido — se corrigió en el bloque coordinado de dominio; el orquestador verificó posteriormente un formulario MCP real con campos ES/EN y aprobación UI. Esta auditoría no atribuye ese trabajo a sus propios tests. Los registros internos de IA, roles/permisos, comentarios/tags y ejecuciones de migración de medios no disponen todos de CRUD genérico anunciado; algunos usan rutas o acciones específicas y otros requieren definir un contrato antes de exponerlos.

La vista de revisión editorial muestra ahora también los campos pendientes de Forms: identificador, tipo, obligatoriedad, posición, etiquetas y opciones por idioma. El presenter convierte las opciones a texto legible y omite propiedades privadas; la vista las imprime con escape HTML. [EditorialReviewPresenter](/home/david/Proyectos/webs/dbcms/modules/ContentAudit/Http/Presenters/EditorialReviewPresenter.php:33).

## Incoherencias pendientes

- **Analytics:** `gtm_id` aparece en ajustes y descripción, pero no hay consumidor GTM. `anonymize_ip` y `enable_cookie_consent` no cambian el renderer: anonimización y bloqueo por consentimiento se mantienen obligatorios. Conviene describir ese comportamiento en UI o implementar el contrato de los ajustes sin debilitar el consentimiento.
- **Ads:** `renderAdUnit` sólo aparece en su propia definición. No hay callsite en temas/bloques ni loader AdSense; `auto_ads_enabled`, slot por defecto y segmentación de mercado se anuncian sin integración efectiva. Configurar un publisher no basta para mostrar anuncios.
- **Meta:** `sendConversionEvent` sólo aparece en el servicio. No hay listeners de Lead/Purchase ni inyección Pixel `fbq` en vistas. El servicio es infraestructura parcial; no un flujo de conversiones funcionando. Versionado Graph API, normalización/hash de `user_data`, URL fuente y deduplicación de eventos requieren validarse al integrar el flujo.
- **Descubrimiento Gallery:** el handler acepta `items_json`, pero la descripción genérica de atributos basada en `$fillable` no documenta toda esa relación. La herramienta puede recibirla bajo `attributes`; falta explicitar el contrato en el catálogo.

No se encontró una capacidad CMS para emitir claves OpenAI o habilitar automáticamente una cuenta API. La configuración espera una credencial proporcionada por el usuario. No se inspeccionaron credenciales personales de Codex ni se infirió acceso API a partir de una suscripción de ChatGPT.

## Verificación

Primer lote de regresiones locales de seguridad/conexiones: **21 pruebas PHP, 86 aserciones; seis pruebas Node; todas pasan**. El lote adicional del presenter editorial pasa **dos pruebas, 17 aserciones**, incluida la nueva regresión Forms. Al cerrar la CI global se completó el catálogo español de validación y los nombres de atributos ingleses: **144 claves hoja con paridad ES/EN y placeholders idénticos**; la comprobación pura pasa tres pruebas, 441 aserciones. También se verificó paridad de los cuatro catálogos PHP y los dos JSON sin bootstrap ni DB. PHPStan dirigido a los archivos revisados: cero errores. Pint de los archivos modificados: correcto. La suite global la coordina el orquestador.

Archivos de pruebas: [ModuleOutputSecurityTest](/home/david/Proyectos/webs/dbcms/tests/Unit/Services/ModuleOutputSecurityTest.php), [LegacyModuleDiscoverySecurityTest](/home/david/Proyectos/webs/dbcms/tests/Unit/Services/LegacyModuleDiscoverySecurityTest.php), [TrackingAvailabilityTest](/home/david/Proyectos/webs/dbcms/tests/Unit/Services/TrackingAvailabilityTest.php), [ModuleCapabilityAvailabilityTest](/home/david/Proyectos/webs/dbcms/tests/Unit/Services/ModuleCapabilityAvailabilityTest.php), [ValidationMessagesTest](/home/david/Proyectos/webs/dbcms/tests/Unit/Services/ValidationMessagesTest.php), [analytics-consent.test.mjs](/home/david/Proyectos/webs/dbcms/tests/Frontend/analytics-consent.test.mjs), [market-locale-selector.test.mjs](/home/david/Proyectos/webs/dbcms/tests/Frontend/market-locale-selector.test.mjs).

Precaución concreta del harness existente: `Tests\TestCase::tearDown()` elimina el archivo global `storage/framework/cms-maintenance.json`. Por ese motivo los tests de esta auditoría no heredan esa clase ni activan mantenimiento, y las futuras suites del módulo deben aislar dicho archivo para no alterar una sesión real.

## Revisión arquitectónica final — sólo lectura

Se inspeccionaron los contratos, los tests y el código final sin modificar implementación ni acceder de nuevo a credenciales. Una medición por `ReflectionMethod` en Sail, sin bootstrap de aplicación ni acceso a DB, encontró **148 controladores invocables; 58 acciones superan diez líneas físicas de cuerpo**. La fórmula usada coincide con el gate existente (`endLine - startLine - 1`); incluye comentarios y saltos de línea, por lo que la longitud es un indicador y las consultas/reglas siguientes son la evidencia sustantiva.

| Prioridad | Incumplimiento confirmado | Evidencia y cambio recomendado |
| --- | --- | --- |
| P2 | Mutaciones y validación de negocio dentro de controladores | [ModuleSettingsPutController](/home/david/Proyectos/webs/dbcms/app/Http/Controllers/Admin/ModuleSettings/ModuleSettingsPutController.php:23): 56 líneas; consulta Eloquent, excepción específica de Maintenance, construcción de reglas y normalización/persistencia de ajustes. [FilterPostController](/home/david/Proyectos/webs/dbcms/app/Http/Controllers/Admin/Seo/FilterPostController.php:58): `updateOrCreate` y sincronización de secciones desde el controlador, sin transacción conjunta explícita. Extraer Request/adaptador y servicio de dominio transaccional; registrar extensiones de ajustes por módulo. |
| P2 | Preparación de lecturas y catálogos de entidades centralizada en controladores grandes | [MenusGetController](/home/david/Proyectos/webs/dbcms/app/Http/Controllers/Admin/Menus/MenusGetController.php:29): 191 líneas, consultas y ramas específicas Pages/Blog/Shop para enlaces. [IndexGetController de ContentAudit](/home/david/Proyectos/webs/dbcms/modules/ContentAudit/Http/Controllers/Admin/IndexGetController.php:24): 60 líneas, filtros, consultas y títulos. [FormEditGetController](/home/david/Proyectos/webs/dbcms/modules/Forms/Http/Controllers/Admin/FormEditGetController.php:24): 58 líneas, queries y conversión de campos/traducciones. Extraer servicios de lectura/presentación; mantener los controladores como transporte. |
| P2 | Dependencias concretas de módulos opcionales desde core | [BlockRegistry](/home/david/Proyectos/webs/dbcms/app/Core/Blocks/BlockRegistry.php:11) importa modelos Blog/Forms/Gallery/Shop y construye sus payloads en core. [FieldTypeRegistry](/home/david/Proyectos/webs/dbcms/app/Core/Forms/FieldTypeRegistry.php:112) conoce e instancia Captcha; [RoiBlock](/home/david/Proyectos/webs/dbcms/app/Core/Blocks/Types/RoiBlock.php:9) depende de Charts; [FilterIntroRenderer](/home/david/Proyectos/webs/dbcms/app/Core/Filters/FilterIntroRenderer.php:9) depende de un renderer interno Pages; [ModuleManager](/home/david/Proyectos/webs/dbcms/app/Core/Modules/ModuleManager.php:14) depende de PageSection. Guardas de disponibilidad reducen efectos con módulo apagado, pero no eliminan el acoplamiento de código. Mover contribuciones a los providers propietarios y consumir contratos/registros core. |
| P2 | Catálogo de entidades describe atributos persistibles, pero no todas las relaciones editables | [EntityCrudService::describe](/home/david/Proyectos/webs/dbcms/app/Services/Capabilities/EntityCrudService.php:66) usa `$fillable`; [EntityCreateTool](/home/david/Proyectos/webs/dbcms/app/Core/Capabilities/Tools/Entity/EntityCreateTool.php:31) no declara `fields` ni `items_json` como propiedades específicas. Forms/Gallery sí los reciben dentro de `attributes` mediante sus handlers. La ejecución funciona, pero un consumidor que siga únicamente `cms_entity_types` puede omitir el constructor de campos o las imágenes. Extender metadatos de relaciones y reglas aportados por el owner; no añadir entidades ficticias al inventario. |
| P2 | Los gates arquitectónicos no cubren todas las reglas publicadas | [ArchitectureTest](/home/david/Proyectos/webs/dbcms/tests/Architecture/ArchitectureTest.php:9) permite `App\Models`, `Modules`, `Illuminate` y seeders en controladores; no prohíbe queries ni establece límite global. [CapabilityArchitectureTest](/home/david/Proyectos/webs/dbcms/tests/Architecture/CapabilityArchitectureTest.php:33) sí limita Orchestration y una lista concreta de mutaciones Blog/Pages/Shop/Gallery, pero deja fuera lecturas y otros módulos. El gate core prohíbe HTTP controllers/requests, no dependencias a todos los módulos. Ampliar reglas por capas y propiedad, con migración incremental de las excepciones reales. |

La guía pública exige desacoplamiento entre módulos y acciones de hasta diez líneas en [docs/11](/home/david/Proyectos/webs/dbcms/docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md:14). Estas reglas todavía no describen uniformemente la implementación. Las mutaciones principales compartidas y la orquestación tienen gates más estrictos que el código legado restante; esa mejora no implica que toda la aplicación haya alcanzado el mismo estándar.

**Análisis estático y CI:** [phpstan.neon](/home/david/Proyectos/webs/dbcms/phpstan.neon:5) usa nivel 8 sin baseline, `ignoreErrors` ni `excludePaths`; no se encontraron anotaciones `@phpstan-ignore` en app/modules. Sus paths incluyen app, modules, config, database y routes, **no tests**. El análisis dirigido de esta auditoría sí incluyó sus tests. La CI containerizada ejecuta build, Node, Pint, PHPStan y Pest mediante [bin/ci-check](/home/david/Proyectos/webs/dbcms/bin/ci-check:19), llamado por [quality.yml](/home/david/Proyectos/webs/dbcms/.github/workflows/quality.yml:22). Esta revisión no vuelve a ejecutar la suite global; la coordina el orquestador.

**Alcance real de otros gates:** los contratos de tres servicios concretos se comprueban en [ArchitectureTest](/home/david/Proyectos/webs/dbcms/tests/Architecture/ArchitectureTest.php:61), no todos los servicios. `ZeroHostGuardTest` verifica archivos/servicios/scripts declarados, no cada posible ejecución. `DocumentationIntegrityTest` valida comandos, scripts e índice, no equivalencia semántica de cada ajuste o capacidad. El test llamado `CleanArchitectureAndInvokableControllersTest` usa [Gate::before(true)](/home/david/Proyectos/webs/dbcms/tests/Feature/Core/CleanArchitectureAndInvokableControllersTest.php:40); por tanto prueba estructura/renderización y disponibilidad, no autorización real.

**Inventario 8 + 13:** corresponde a tipos concretos registrados y filtrados por permiso, no a todas las tablas ni a trece CRUD completos. El catálogo de recursos expone flags create/update/delete y handlers; diez recursos pertenecen al catálogo de plataforma y audit/order/form_submission se aportan desde módulos. La extracción fuera de MCP y la propiedad modular están protegidas por `CapabilityArchitectureTest` y `ModuleCapabilityOwnershipTest`. Siguen pendientes la descripción completa de relaciones Forms/Gallery y las incoherencias funcionales Analytics/Ads/Meta ya detalladas arriba. La existencia de una entrada de catálogo o de un test de registro no confirma cada efecto de escritura ni una conexión externa funcional.
