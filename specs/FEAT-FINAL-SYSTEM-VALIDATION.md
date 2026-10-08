# Feature Specification: Validación final y preparación de dbCMS como plantilla

> **Spec ID**: `FEAT-FINAL-SYSTEM-VALIDATION`  
> **Status**: APPROVED  
> **Author**: Superagent  
> **Date**: 2026-10-12

## 1. Domain Objective & Context

Auditar backoffice, módulos, ejecución y descubrimiento público; corregir defectos
reproducibles y documentar cómo publicar el repositorio actual `dbravoan/dbcms`
como plantilla y arrancar RegulaSE con datos, identidad y secretos propios.
No se autoriza en esta fase un push, cambio de visibilidad, creación remota de
repositorios ni despliegue. No se promete posicionamiento, factualidad automática
o una puntuación universal SEO/GEO/AEO.

## 2. Algebraic Data Types & DTO Contracts

Se conservan los contratos existentes. La autorización por fuente, entidad y
recurso debe proceder de registros comunes. Lectura y resolución de selectores
comparten restricciones. Los medios se validan en el servicio propietario, no
solo en el transporte. Cualquier ampliación de contratos usa DTO inmutable.

## 3. Formal Invariants

- Acceso al backoffice exige el permiso real; tener un rol no concede acceso.
- Un autor no modifica la política de revisión ni consulta revisiones ajenas.
- Fuentes privadas y módulos inactivos no se descubren por búsqueda o IDs.
- Todos los transportes rechazan MIME falso y límites excedidos de medios.
- Sitemap, alternates y corpus solo describen contenido publicado y disponible
  en el mercado/idioma efectivo; respetan también la visibilidad de precios.
- Ningún export estático obsoleto evita el control dinámico del corpus GEO.
- Un nuevo proyecto no hereda secretos, datos de QA ni cuentas demo de producción.
- CI valida una instantánea del código en contenedores y bases desechables;
  ningún test puede limpiar la base del usuario ni su runtime.

## 4. REST API & Passport OAuth2 Contract

Se mantienen rutas y formatos salvo endurecimiento documentado de autorización
y validación. Web usa permisos de usuario; OAuth añade scopes sin sustituirlos.
Errores de permiso devuelven 403; datos inválidos, 422. No se habilitan proveedores,
permisos personalizados ni ejecución nativa por suposición.

## 5. Generative & Traditional SEO Specification

Canonical e idioma/mercado corresponden a una URL pública real. JSON-LD refleja
contenido visible y no constituye validación externa. El corpus enlaza sus
fuentes canónicas y excluye borradores, versiones pendientes y mercados ajenos.
Las métricas editoriales se presentan como heurísticas. RegulaSE requiere fuentes
oficiales, jurisdicción, vigencia, revisión humana y separación de opinión y hechos.

## 6. Test Invariant Checklist

- [x] Reproducir defectos con regresiones antes de corregir.
- [x] Probar correcciones con bases MySQL aisladas por agente.
- [x] Ejecutar suite completa, Node, PHPStan, Pint, auditorías y build sobre el
  mismo snapshot final aislado; comprobar imagen de producción y rutas críticas.
- [x] Registrar inventario completo de rutas/módulos y niveles de cobertura.
- [x] Documentar plantilla, instalación limpia, actualizaciones y lanzamiento
  RegulaSE con decisiones reales y fuentes primarias.

Resultado y límites: `docs/FINAL_SYSTEM_AUDIT_2026-10-12.md`. Gate aislado con
1.302 pruebas y 12.606 aserciones; 40 pruebas Node; PHPStan/Pint, auditorías y build
correctos. Se verificó instalación productiva sin demo. Los checks acreditan su
alcance documentado; no certifican correo real, proveedores externos, validación
de buscadores ni cobertura de todas las combinaciones del sistema.
