# Feature Specification: Integración y distribución dbCMS 1.2.0

> **Spec ID**: `FEAT-RELEASE-1.2.0-INTEGRATION`  
> **Status**: APPROVED  
> **Author**: `SpecArchitect` Agent  
> **Date**: 2026-10-15

## 1. Domain Objective & Context
Integrar marketing (CreativeStudio, SocialPublishing, Analytics y guías) con el actualizador del núcleo en una entrega 1.2.0 revisable. Conservar configuraciones privadas, personalizaciones y las entregas históricas. Esta especificación cubre metadatos, distribución, compatibilidad y trazabilidad; no autoriza publicar ni certificar conexiones externas.

## 2. Algebraic Data Types & DTO Contracts
Se reutilizan los contratos inmutables de manifiestos del actualizador y DTOs de marketing existentes; no se añade un DTO de negocio para versionar archivos. El contrato de distribución contiene `version`, `source_sha`, `min_php`, `extensions`, `supported_from`, `rollback_compatible`, inventarios y firmas. Versiones origen reconocidas: 1.0.0, 1.0.1, 1.1.0 y 1.2.0; selección ascendente para nuevas actualizaciones.

## 3. Formal Invariants (Hoare Logic: P {Q} R)
- **P**: fuentes integradas y copia verificable de los cambios originales; secretos excluidos del repositorio.
- **Q**: alinear las superficies canónicas en 1.2.0 y construir inventarios históricos desde tags y paquetes fijados. Solo el contenedor de release ejecuta PHP. No se modifican etiquetas ni assets publicados.
- **R**: la base 1.1.0 fija commit `25e1a5671726659c6fde149ef218715b7d07aadf` y ZIP SHA-256 `be47c7f236a73b78aa89e7d8b6cddd214fbe308c66717e38699a010777c1de48`; las firmas verifican con la clave pública existente. La revisión explícita de las cinco migraciones confirma que solo crean tablas o añaden columnas a tablas nuevas de los módulos, sin cambiar contratos leídos por core 1.1.0; módulos inicialmente inactivos. `rollback_compatible=true` permite el recorrido standalone; no autoriza rollback automático tras `migration_started` ni revierte DB. El arranque de core 1.1.0 con DB migrada se validará por E2E.

## 4. REST API & Passport OAuth2 Contract
No añade endpoints. Se preservan los contratos y permisos existentes de marketing y actualizaciones. La publicación exige CI exitoso del SHA exacto, versión/tag coherentes, secreto de firma válido y ausencia de release estable previa.

## 5. Generative & Traditional SEO Specification
Esta entrega administrativa no añade contenido público indexable; se conservan generadores de llms.txt y JSON-LD existentes. No se añaden páginas administrativas al índice.

## 6. Test Invariant Checklist (TDD)
- [ ] Regresión: versiones de VERSION, npm, core, política, instalador y workflow coherentes.
- [ ] Regresión: base histórica 1.1.0 con commit y ZIP fijados; conservar 1.0.0 y 1.0.1.
- [ ] Gate Docker: Pest, arquitectura, PHPStan y Pint sobre la integración final.
- [ ] Gate distribución: firmas, exclusión de estado privado y manifiestos de ambas distribuciones.
- [ ] Validación de marketing y actualización según matriz de trazabilidad; ejecuciones nuevas pendientes.
