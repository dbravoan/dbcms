# Feature Specification: Orquestación nativa y correcciones de auditoría

> **Spec ID**: `FEAT-CMS-ORCHESTRATION-20261010`  
> **Status**: IMPLEMENTED  
> **Author**: SpecArchitect / Superagent  
> **Date**: 2026-10-10

## 1. Domain Objective & Context

El CMS PHP/Laravel interpreta instrucciones y ejecuta servicios existentes mediante capacidades registradas por los módulos. IA, web, API, MCP y jobs comparten autorización y reglas de dominio. MCP adapta el protocolo y no gobierna el catálogo. LangGraph, Google ADK y conexiones personales no forman parte de esta entrega.

La nueva entrada de instrucciones está desactivada por defecto (`CMS_ORCHESTRATION_ENABLED=false`). Los modelos y aplicaciones son conexiones compartidas del CMS, con acceso sujeto a permisos.

## 2. Algebraic Data Types & DTO Contracts

Contratos: `CapabilityInterface`, `CapabilityExecutorInterface`, `PlannerInterface`, `CapabilityPresentationInterface`, `PendingOperationInterface`, `ContentRevisionInspectorInterface` y `ExecutionServiceInterface`.

DTOs inmutables en `App\Core\Capabilities`: `ActorContext`, `OrchestrationRequest`, `CapabilityDescription`, `CapabilityAvailability`, `ExecutionPlan`, `ExecutionStep` y `StepResult`. El actor contiene usuario humano/de servicio, origen, cliente/token y scopes OAuth reales, mercado e idioma. Los adaptadores HTTP construyen este contexto; el modelo no puede suministrarlo.

Una capacidad describe nombre, argumentos, efecto, módulo y dependencias. El catálogo efectivo limita tipos y recursos por permisos y actividad de sus módulos. El modelo recibe descripciones y esquemas autorizados, sin clases internas ni credenciales. La disponibilidad se consulta de nuevo antes de ejecutar.

Estados persistidos: `planning`, `pending`, `running`, `needs_input`, `approval_required`, `pending_remote`, `completed`, `failed`, `uncertain`, `cancelled`. Resultados de capacidades distinguen validación, denegación, indisponibilidad, conflicto, aprobación y operación remota pendiente.

## 3. Formal Invariants (Hoare Logic)

**Precondiciones:** identidad vigente; permiso RBAC; scopes adicionales sólo en OAuth; módulo/operación/modelo disponible; argumentos válidos. Los clientes M2M necesitan asociación explícita en `oauth_service_accounts`. Borrar una cuenta revoca tokens y sesiones asociadas; nunca convierte un token humano en M2M.

**Procesamiento:** el modelo propone JSON, máximo 20 pasos y una revisión automática. El CMS verifica herramientas, esquemas, dependencias anteriores y referencias a resultados. La aclaración no contiene pasos ejecutables. No se aceptan SQL, ejecución de código, herramientas inventadas, identidad, scopes ni aprobaciones suministradas por el modelo.

El plan completo se valida antes de persistir pasos. Las operaciones locales usan transacciones cortas; las llamadas HTTP quedan fuera. Lectura, generación y borradores solicitados pueden ejecutarse. Publicación, borrado y cambios externos se detienen hasta aprobación humana desde el backoffice. La firma incluye versión del plan, argumentos resueltos, contenido, traducciones, mercados, secciones y revisión pendiente. Una aprobación se consume una vez y no amplía los permisos del actor original.

**Postcondiciones:** checkpoints y resultados persistidos. No se repiten pasos completados. Una operación remota pendiente conserva su ID; un resultado incierto no se reenvía automáticamente. Reanudaciones y consultas privadas revalidan permisos. Editar contenido publicado conserva la versión visible hasta aprobar la revisión.

## 4. REST API & Passport OAuth2 Contract

| Operación | Web | API | MCP |
| --- | --- | --- | --- |
| Catálogo | `GET /admin/orchestration/catalog` | `GET /api/orchestration/catalog` | `tools/list`, `cms_capabilities` |
| Iniciar | `POST /admin/orchestration` | `POST /api/orchestration` | `cms_orchestration_start` |
| Estado | `GET /admin/orchestration/{id}` | `GET /api/orchestration/{id}` | `cms_orchestration_status` |
| Responder/reanudar/cancelar | `POST .../{id}/respond|resume|cancel` | equivalentes API | `cms_orchestration_respond|resume|cancel` |
| Aprobar | sesión web + CSRF en `/admin/executions/{id}/approve` | no existe | devuelve `execution_id` y `review_url` |

`ADMIN_PATH` cambia el prefijo web. Inicio requiere instrucción y clave de idempotencia; una clave reutilizada con otra solicitud devuelve 409. API requiere Passport `ai:use`, RBAC y los scopes correspondientes a las capacidades de cada paso. MCP requiere `mcp:access` y read/write/destructive, más RBAC. `confirm:true` no concede autoridad. `use:ai` y `manage:ai` se asignan exclusivamente a los roles estándar descritos en el runbook, sin ampliar roles personalizados.

### Matriz de equivalencia

| Regla | Backoffice | IA | API | MCP | Job |
| --- | --- | --- | --- | --- | --- |
| Identidad y permiso vigente | sesión + RBAC | contexto servidor | RBAC + scopes | RBAC + scopes | actor persistido, reválida |
| Borrador de contenido | servicio compartido | catálogo + servicio | mismo ejecutor | mismo ejecutor | mismo ejecutor |
| Cambio publicado | revisión | revisión | revisión | revisión | revisión |
| Publicación/borrado/externo | decisión humana expresa | aprobación pendiente | aprobación pendiente | referencia de aprobación | aprobación persistida válida |
| Privacidad de resultados | propietario o revisor de servicio autorizado | igual | propietario/cliente | propietario/cliente | identidad original |

## 5. Generative & Traditional SEO Specification

Los borradores permanecen fuera de superficies públicas. Cambios publicados, traducciones y pertenencia a mercados/listas invalidan HTML, consultas previamente vacías, sitemap, RSS y corpus GEO después del commit. El precio efectivo del mercado se refleja en catálogo, filtros, checkout y Schema.org Offer. Investigación conserva procedencia y no fabrica estadísticas, fuentes ni puntuaciones.

La caché interna guarda HTML público sin carrito, consentimiento, geolocalización, CSRF ni nonce del visitante. Los fragmentos privados se reconstruyen por petición y la respuesta final usa `private, no-store`. Checkout, pedidos, autenticación y CAPTCHA no usan snapshots compartidos.

## 6. Test Invariant Checklist (TDD)

- Identidad sin roles, token humano eliminado, cliente M2M sin asociación y sesiones SSE cruzadas: denegados.
- Argumentos/dependencias inválidos o confirmación del modelo: no ejecutan efectos.
- Catálogo/ejecutor independiente del módulo MCP; herramientas concretas sin LLM.
- Drafts completos y edición publicada en revisión, con huella invalidada por cambios.
- Idempotencia, interrupción al planificar, recuperación de checkpoints y polling sin duplicar.
- Proveedores con HTTP simulado y credenciales ficticias; selección y parámetros por modalidad respetados.
- MIME, URLs privadas/redirecciones, WebP y XSS: bloqueos verificables.
- Compra completa válida bajo bloqueos; offline pendiente de pago; pedidos privados/enlaces24h; presupuesto persistido.
- Caché aislada por visitante/mercado y invalidación de cambios publicados.
- Desinstalación/reinstalación conserva datos; discovery no reactiva módulos.
- Pest, PHPStan8 sin exclusiones/baseline, Pint, auditorías y build sobre el estado final; ver informe de verificación al terminar.

### Regresiones funcionales de módulos, 11 de octubre de 2026

- Un idioma inactivo en el editor conserva contenido y SEO mediante el mismo esquema de traducciones que el idioma visible.
- Forms conserva campos y traducciones desde web/MCP y en snapshots pendientes; activar requiere revisar también esos campos.
- Una revisión antigua invalida su aprobación; reanudar regenera su versión revisable sin relajar la firma ni aprobar automáticamente.
- Publicar una entidad Sectionable promueve únicamente las secciones aprobadas, incluso cuando la petición sólo incluye `publish:true`.
- Sitemap, RSS, GEO, hreflang, listados, búsqueda y categorías comparten disponibilidad por módulo, mercado, estado e idioma; un documento de búsqueda antiguo no convierte contenido restringido en global.
- El tipo JSON-LD describe la entidad visible y sólo propiedades verificables; métricas editoriales no se presentan como citas medidas.
- Un upload convertido informa el MIME de los bytes almacenados y conserva ALT/título/caption en sus consumidores.

Evidencia y límites del estado real: `docs/CMS_MODULE_FUNCTIONAL_AUDIT_2026-10-11.md`. La comprobación local no certifica proveedores sin credenciales, indexación externa ni cumplimiento arquitectónico de los controladores fuera de los gates efectivos.

## Entrega y migración

Migraciones aditivas para ejecuciones/pasos/aprobaciones, cuentas de servicio y permisos IA, presupuestos y puntero de revisión pendiente. El historial de auditoría conserva `mcp_audit_logs` y el nombre de su migración al pasar al núcleo. No se alteran automáticamente pedidos históricos ni estados de pago. Las herramientas/rutas anteriores conservan adaptadores de compatibilidad con autorización endurecida.

La activación exige backup, migraciones verificadas, conciliación de pagos simulados, revisión de roles/conexiones y health check. La recuperación revierte código, conserva datos y no ejecuta rollback de migraciones automáticamente. Esta tarea no despliega ni habilita conexiones. Procedimiento: `docs/NATIVE_ORCHESTRATION_RUNBOOK.md`.
