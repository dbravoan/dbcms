# Feature Specification: Cierre de pendientes y Auditoría y Calidad fiable

> **Spec ID**: `FEAT-PENDING-CLOSURE-QUALITY-WORKFLOW`  
> **Status**: IMPLEMENTED  
> **Author**: Superagent  
> **Date**: 2026-10-12

## 1. Domain Objective & Context

Cerrar los defectos de código pendientes del informe final y revisar ContentAudit
en sus estados activo, desactivado, desinstalado y reinstalado. Mantener los datos,
contratos y permisos existentes, y comprobar igualdad de efectos entre transportes.
Incluye transporte de notificaciones, instalación ZIP, índices públicos, programación,
extensibilidad GEO y extracción de lógica heredada de los controladores afectados. La revisión adicional
solicitada incluye fronteras arquitectónicas verificables, aislamiento editorial
del núcleo, coste de consultas y guía de mantenimiento alineada con el runtime.

No se publica ni despliega código por esta petición. Las pruebas externas requieren
un destino real y capacidad disponible; la falta de dominio, facturación o proveedor
no se resuelve inventando éxito, ni se sustituyen por una certificación local.

## 2. Algebraic Data Types & DTO Contracts

DTOs inmutables propietarios para paquete inspeccionado, resultado de instalación,
destinatarios/notificación y presentación del editor. Contratos separados para
instalación de paquetes, resolución de destinatarios, timestamps públicos y
extractores de contenido. Los módulos registran sus implementaciones.

## 3. Formal Invariants

- La activación real del módulo, permisos y roles aprobadores gobiernan todas las
  decisiones; un setting aislado no reactiva un módulo inactivo.
- Un cambio pendiente nunca altera la versión pública antes de aprobación válida.
- Aprobar, rechazar o restaurar exige actor autorizado y revisión vigente; no se
  publican secciones o traducciones de otra entidad ni se repiten efectos terminados.
- El rollback conserva estructura, idiomas, mercados y estado editorial coherente.
- Notificaciones usan transporte real después del commit, destinatarios vigentes y
  errores explícitos; el rollback local no envía correo ni borra avisos persistidos.
- Un ZIP no escribe fuera del staging, no agota recursos ni reemplaza un módulo
  existente por colisión; un fallo limpia temporales y conserva código y datos.
- Lastmod/dateModified corresponden a la versión pública y sus dependencias.
- Cada sitemap respeta límites de número y bytes y enlaza sólo URLs disponibles.
- Las fechas programadas marcan fronteras de caché e invalidación y se comprueban
  al servir contenido; la vigencia no depende de una caché obsoleta.
- Nuevos extractores GEO siguen registro y permisos; el corpus no expone snapshots
  pendientes ni fabrica texto de widgets sin contrato.
- Los agregados editoriales son privados: un contador no revela revisiones que
  el actor no puede consultar. Los resultados de búsqueda comparten su contexto
  de mercado sin repetir la consulta por cada hit.
- Los totales del carrito derivan de una sola lectura de productos por resumen,
  manteniendo precio, impuestos y total coherentes en la misma representación.
- El CRUD común no importa implementaciones del módulo editorial opcional; su
  contrato tiene un comportamiento neutro cuando no existe adaptador registrado.
- Toda prueba usa DB y runtime desechables; se conservan cambios y datos del usuario.

## 4. REST API & Passport OAuth2 Contract

Se mantienen nombres y rutas mediante adaptadores. Web usa permisos del usuario;
OAuth añade scopes. Se documentan los nuevos conflictos (409), validación (422),
denegación (403), ausencia/inactividad (404) y errores de transporte. Ningún prompt,
token o parámetro `confirm` otorga aprobación editorial o publicación.

## 5. Generative & Traditional SEO Specification

Schemas, sitemap/RSS y corpus describen contenido público real. Pruebas locales
validan contratos e integridad; no demuestran posicionamiento, citabilidad, entrega
SMTP externa ni exactitud jurídica. Se prepara validación externa con resultados
separados y sin publicar borradores a terceros.

## 6. Test Invariant Checklist

- [x] Regresiones RED de notificación, paquetes, descubrimiento y revisión editorial.
- [x] Paridad de transportes y estados activo/inactivo/reinstalado.
- [x] Concurrencia, revisión obsoleta, rollback e integridad de contenido público.
- [x] Correo entregado en transporte SMTP local aislado y fallos comprobados.
- [x] Paquetes maliciosos y límites, staging, colisiones y recuperación.
- [x] Timestamps, segmentación e invalidación en fronteras de publicación.
- [x] Temas dbCMS/TermoCMS y arquitectura de controladores afectados.
- [x] Gate global, imagen productiva y documentación del estado exacto validado.
