# Feature Specification: Fiabilidad del flujo de Auditoría y Calidad

> **Spec ID**: `FEAT-CONTENT-AUDIT-RELIABILITY`
> **Status**: IMPLEMENTED
> **Author**: DomainEngineer

## 1. Domain Objective & Context
El flujo sólo interfiere cuando el módulo está instalado, activo y su flujo habilitado. Las revisiones conservan atributos, traducciones, bloques, mercados y relaciones del módulo. Una restauración es completa y atómica; los rechazos no dejan publicar una revisión descartada ni sobrescriben una revisión distinta por una pantalla antigua.

## 2. Algebraic Data Types & DTO Contracts
El módulo implementa directamente `EditorialWorkflowInterface` y `ContentRevisionInspectorInterface`; política, historial, presentación, evaluación y notificaciones tienen responsables separados. `ContentSnapshotService` conserva una única versión de instantáneas (v3). `PendingEditorialDraft` y `EditorialDecision` expresan los resultados de la frontera editorial. La implementación core sin módulo no consulta tablas opcionales y deniega decisiones de revisión. No hay fachadas ni adaptadores delegadores históricos.

## 3. Formal Invariants
- Estado de módulo: instalado y activo, configuración de flujo activa.
- Decisiones revisan permisos vigentes; bloqueo de fila serializa revisiones de una misma entidad.
- Rechazo de revisión pendiente conserva la publicación vigente y archiva la revisión exacta.
- Restauración incluye datos específicos y anula el puntero pendiente anterior. Errores no permiten restauraciones parciales.
- Eventos y notificaciones sólo se despachan después de commit.
- La puntuación editorial es una heurística de estilo, nunca una prueba de autoría humana ni veracidad.
- El contador pendiente y la autoridad de revisión se resuelven por el contrato core para el actor vigente; no revelan expedientes ajenos o de módulos retirados.

## 4. REST API & Passport OAuth2 Contract
Se conservan rutas y capacidades. Rechazo web requiere vinculación de la revisión mostrada, igual que aprobación. Denegación 403; versión obsoleta 409; razón y vinculación inválidas 422. OAuth añade scopes a permisos de entidad, nunca a sesiones web.

## 5. Generative & Traditional SEO Specification
La decisión editorial invalida índices después de commit. La revisión privada no se publica en índices. La restauración conserva metadata y pertenencias a mercados.

## 6. Test Invariant Checklist (TDD)
- [x] Desactivación física y reinstalación no dejan observadores interferir indebidamente.
- [x] Restauración completa y puntero pendiente anulado.
- [x] Historial sin pérdida de campos específicos de traducción.
- [x] Rechazo de pantalla obsoleta falla sin descartar la revisión actual.
- [x] Notificación y eventos no salen de una transacción revertida.
- [x] Opciones de seguimiento y motivo cumplen semántica configurada.
- [x] Mutaciones, acciones de secciones, navegación y dashboard no importan el módulo editorial ni consultan sus revisiones.
- [x] Crear, actualizar y eliminar siguen funcionando sin módulo/tablas editoriales; no se concede aprobación por ausencia.
- [x] El contador de revisiones pendientes sólo revela expedientes que el actor puede inspeccionar.

## 7. Evidencia y límites

Regresiones en `tests/Feature/ContentAudit/ContentAuditReliabilityTest.php`. Operación documentada en `docs/CONTENT_AUDIT_WORKFLOW.md`. No se atribuye verdad, autoría o posicionamiento a la heurística editorial ni se reconstruyen campos ausentes de instantáneas antiguas.
