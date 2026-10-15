# Integración dbCMS 1.2.0: informe y trazabilidad

Fecha: 2026-10-15. Estado: implementación en revisión; **validación del árbol integrado y publicación pendientes**. Especificación: [FEAT-RELEASE-1.2.0-INTEGRATION](../specs/FEAT-RELEASE-1.2.0-INTEGRATION.md).

## Procedencia y copias

El árbol original partía de `main` en `b45901650169bfeea916aecdddf332f1994bcb56`, seis commits por detrás del remoto. La integración parte de la release 1.1.0, cuyo tag/remoto fija `25e1a5671726659c6fde149ef218715b7d07aadf`; no se mueve ese tag.

La copia previa está en `/home/david/.codex/backups/dbcms-v120/20261015T061118Z`: `repository.bundle`, `working.patch`, `integration.patch`, `working-files.tar.gz`, `refs.txt`, `status.txt` y `manifest.json` con 220 rutas y sus hashes. La configuración privada se conserva por separado en el backup protegido, fuera de esta entrega. Esta copia del trabajo no sustituye un dump comprobado de DB, archivos subidos y claves de cada instalación. La existencia del manifest no acredita por sí sola un ensayo de restauración.

Se conservan las copias históricas y la referencia Matrix indicadas en el [runbook de marketing](MARKETING_MODULES_RUNBOOK.md). Las propuestas históricas y Dependabot se revisan individualmente; esta integración no autoriza su fusión automática ni retirada de datos o servicios.

## Contrato de distribución y compatibilidad

VERSION, npm, core-version, política, instalador y workflow identifican 1.2.0. El constructor conserva los inventarios 1.0.0 y 1.0.1 y añade 1.1.0 con commit fijado. Su runtime procede del ZIP publicado `dbcms-installer-v1.1.0.zip`, SHA-256 `be47c7f236a73b78aa89e7d8b6cddd214fbe308c66717e38699a010777c1de48`; se comprueba tanto el checksum publicado como el hash fijado. Solo se adjuntan los manifiestos históricos firmados a la entrega nueva: no se alteran artefactos anteriores.

La política reconoce 1.0.0, 1.0.1, 1.1.0 y 1.2.0 como orígenes. La selección de una nueva versión sigue siendo ascendente. Se mantiene la clave pública `resources/updates/trusted-keys/releases-2026.pub`; el secreto privado de firma está configurado en GitHub según la preparación operativa y no se copia al repositorio.

`rollback_compatible=true` procede de una revisión expresa de las cinco migraciones: solo crean tablas de guías/CreativeStudio/SocialPublishing o amplían tablas nuevas de esos módulos; no modifican contratos leídos por core 1.1.0 y los módulos parten inactivos. El flag permite la aplicación standalone, pendiente de ensayo real. El rollback de código no revierte DB y el rollback automático permanece prohibido tras `migration_started`; los fallos posteriores o inciertos requieren recuperación guiada con respaldo comprobado. El arranque de core 1.1.0 con la DB migrada se debe validar por E2E antes de publicar.

PHP mínimo del manifiesto: 8.3.0; extensiones exigidas: OpenSSL, pdo_mysql, mbstring, zip, fileinfo y dom. Las funciones multimedia de marketing requieren además GD, FFmpeg/FFprobe y fuentes; workers y scheduler deben operar según el runbook. La presencia de estas capacidades se debe validar en el alojamiento final, además de la imagen de producción.

## Gates y matriz de trazabilidad

El workflow de release exige versión/tag/SHA coherentes, CI de evento `push` exitoso en `main` para el SHA exacto, ausencia de una release estable previa y clave privada que corresponda a la pública fijada. CI usa Docker para validar Composer/auditoría, npm/auditoría/build, frontend, Pint, PHPStan, Pest y Apache updater; después comprueba imagen de producción y bootstrap. Release construye paquetes y publicación multiarquitectura tras esos gates. No se certifica un SHA futuro con pruebas de un árbol anterior.

| Requisito | Implementación / evidencia prevista | Estado integración final |
| --- | --- | --- |
| Versión 1.2.0 coherente | `tests/Regression/ReleaseVersionConsistencyTest.php` | PENDIENTE ejecución Docker |
| Bases 1.0.0/1.0.1 intactas y nueva 1.1.0 fijada | `bin/build-update-baselines.sh`, regresión de pins | Sintaxis Bash comprobada; build firmado PENDIENTE |
| Distribuciones firmadas sin estado privado | `UpdateDistributionTest`, empaquetado de release | PENDIENTE |
| Standalone 1.1.0 → 1.2.0 | Paquete 1.1.0 publicado, adopción de inventario, preparación, respaldo y migraciones | PENDIENTE aplicación real y compatibilidad del core 1.1.0 con DB migrada |
| Recuperación sin revertir DB automáticamente | Política y `CORE_UPDATES_RUNBOOK.md` | PENDIENTE ensayo |
| CreativeStudio y SocialPublishing | Specs de módulos y tests Feature correspondientes | PENDIENTE |
| Guías, IA, Analytics, consentimiento y atribución | Tests Guides/Marketing/AiAssistant, frontend | PENDIENTE |
| Arquitectura, análisis, estilo y regresión completa | `bin/ci-check`, workflow quality | PENDIENTE |
| Runtime multimedia, workers y scheduler | `MARKETING_MODULES_RUNBOOK.md`, imagen producción | PENDIENTE |
| Proveedores externos y piloto Matrix | Cuentas autorizadas, revisión humana y recibos remotos | PENDIENTE; sin publicaciones externas |
| Integridad de copia previa | Manifest de 220 rutas | Copia existente; restauración operativa PENDIENTE |

Las cifras del runbook de marketing pertenecen a validaciones históricas del trabajo previo, no a este árbol integrado. Actualizar esta matriz con comando, fecha, SHA/árbol y resultado cuando se ejecuten las pruebas nuevas. No sumar suites solapadas ni convertir pruebas pendientes en aprobadas.
