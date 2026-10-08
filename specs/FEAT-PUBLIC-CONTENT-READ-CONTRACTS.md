# Feature Specification: Lectura pública por contratos de Blog y Pages

> **Spec ID**: `FEAT-PUBLIC-CONTENT-READ-CONTRACTS`
> **Status**: IMPLEMENTED
> **Author**: DomainEngineer
> **Date**: 2026-10-12

## 1. Domain Objective & Context
Los controladores de Blog y Pages sólo convierten transporte a contexto readonly y presentan un resultado. Consultas publicadas por mercado/idioma, alias canónicos, filtros y metadata pertenecen a contratos de sus módulos. Los comentarios son un servicio de dominio separado, con validación y notificaciones posteriores al commit.

## 2. Algebraic Data Types & DTO Contracts
`PublicContentContext` readonly contiene mercado, idioma, slug, URL canónica, segmentos, filtros y página. Los lectores devuelven `FrontPageData` (vista, redirección canónica o recurso ausente) sin clases de respuesta HTTP. `CommentSubmission` readonly contiene campos validados; la decisión devuelve redirección/flash neutral.

## 3. Formal Invariants
- Sólo contenido público de mercado e idioma actuales; no fallback a contenido privado.
- No consultas SQL ni composición de filtros/metadata en acciones HTTP.
- Vistas, URLs, breadcrumbs, hreflang y filtros conservan formato.
- Comentarios sólo para post público que permita comentarios. Honeypots no crean datos.
- Un padre pertenece al mismo post y está aprobado; un identificador privado/ajeno no concede vínculos.
- Persistencia y notificaciones de comentario dentro de dominio, eventos después del commit.

## 4. REST API & Passport OAuth2 Contract
Rutas públicas GET actuales se conservan. Comentario POST usa FormRequest de campos + captcha compartido; recursos ausentes 404, entrada inválida 422/errores de sesión según transporte. No se crean rutas OAuth ni se conceden scopes a visitantes.

## 5. Generative & Traditional SEO Specification
Canonical, breadcrumbs, esquemas y hreflang se construyen en lectores compartidos; URLs legacy redirigen a forma única. `dateModified` mantiene el instante público tras edición privada. Mapas de alternativos admiten segmentos como datos, sin Request HTTP en servicio.

## 6. Test Invariant Checklist (TDD)
- [x] Arquitectura: acciones <=10 líneas, no consultas en controllers, contratos de lectores.
- [x] Contrato: mismas vistas y datos públicos con contexto transport-neutral.
- [x] Regresión: respuestas a comentario ajeno/noaprobado rechazadas, honeypot y moderación preservados.
- [x] Funcional: blog, filtros, páginas, SEO, comentarios y aislamiento siguen pasando.

## 7. Evidencia
Verificación focal: 31 pruebas, 148 aserciones y PHPStan sin errores. Incluye la regresión de slug jerárquico traducido, lector nativo sin Request y comentarios privados/ajenos. No se ejecutaron notificaciones externas.
