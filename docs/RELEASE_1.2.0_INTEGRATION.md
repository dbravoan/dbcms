# Integración dbCMS 1.2.0: informe y trazabilidad

Fecha: 2026-10-15. Estado: **validaciones locales de instalación y actualización realizadas**; gate completo aún en ejecución, CI remota y publicación pendientes. Especificación: [FEAT-RELEASE-1.2.0-INTEGRATION](../specs/FEAT-RELEASE-1.2.0-INTEGRATION.md).

## Procedencia y copias

El árbol original partía de `main` en `b45901650169bfeea916aecdddf332f1994bcb56`, seis commits por detrás del remoto. La integración parte de la release 1.1.0, cuyo tag/remoto fija `25e1a5671726659c6fde149ef218715b7d07aadf`; no se mueve ese tag.

La copia previa está en `/home/david/.codex/backups/dbcms-v120/20261015T061118Z`: `repository.bundle`, `working.patch`, `integration.patch`, `working-files.tar.gz`, `refs.txt`, `status.txt` y `manifest.json` con 220 rutas y sus hashes. La configuración privada se conserva por separado en el backup protegido, fuera de esta entrega. Esta copia del trabajo no sustituye un dump comprobado de DB, archivos subidos y claves de cada instalación. Se ha restaurado el archivo de trabajo en un destino temporal y comprobado los hashes de las 220 rutas (`dist/verification-v120/snapshot-restore.json`); se excluye `storage/install-finalize.lock` como estado runtime. La trazabilidad del candidato cubre las 219 rutas de fuentes restantes, todas presentes. Este ensayo verifica la copia del trabajo, no una restauración de producción.

Se conservan las copias históricas y la referencia Matrix indicadas en el [runbook de marketing](MARKETING_MODULES_RUNBOOK.md). Las propuestas históricas y Dependabot se revisan individualmente; esta integración no autoriza su fusión automática ni retirada de datos o servicios.

## Contrato de distribución y compatibilidad

VERSION, npm, core-version, política, instalador y workflow identifican 1.2.0. El constructor conserva los inventarios 1.0.0 y 1.0.1 y añade 1.1.0 con commit fijado. Su runtime procede del ZIP publicado `dbcms-installer-v1.1.0.zip`, SHA-256 `be47c7f236a73b78aa89e7d8b6cddd214fbe308c66717e38699a010777c1de48`; se comprueba tanto el checksum publicado como el hash fijado. Solo se adjuntan los manifiestos históricos firmados a la entrega nueva: no se alteran artefactos anteriores.

La política reconoce 1.0.0, 1.0.1, 1.1.0 y 1.2.0 como orígenes. La selección de una nueva versión sigue siendo ascendente. Se mantiene la clave pública `resources/updates/trusted-keys/releases-2026.pub`; el secreto privado de firma está configurado en GitHub según la preparación operativa y no se copia al repositorio.

`rollback_compatible=true` procede de una revisión expresa de las cinco migraciones: solo crean tablas de guías/CreativeStudio/SocialPublishing o amplían tablas nuevas de esos módulos; no modifican contratos leídos por core 1.1.0 y los módulos parten inactivos. El flag permite la aplicación standalone, ya ensayada localmente de 1.1.0 a 1.2.0. El rollback de código no revierte DB y el rollback automático permanece prohibido tras `migration_started`; los fallos posteriores o inciertos requieren recuperación guiada con respaldo comprobado. El núcleo oficial 1.1.0 arrancó y permitió login, panel, portada y `/up` sobre una copia independiente verificada de la DB post1.2 (99 tablas); no se ejecutaron migraciones DOWN ni se alteró el esquema principal.

PHP mínimo del manifiesto: 8.3.0; extensiones exigidas: OpenSSL, pdo_mysql, mbstring, zip, fileinfo y dom. Las funciones multimedia de marketing requieren además GD, FFmpeg/FFprobe y fuentes; workers y scheduler deben operar según el runbook. La presencia de estas capacidades se debe validar en el alojamiento final, además de la imagen de producción.

## Gates y matriz de trazabilidad

El workflow de release exige versión/tag/SHA coherentes, CI de evento `push` exitoso en `main` para el SHA exacto, ausencia de una release estable previa y clave privada que corresponda a la pública fijada. CI usa Docker para validar Composer/auditoría, npm/auditoría/build, frontend, Pint, PHPStan, Pest y Apache updater; después comprueba imagen de producción y bootstrap. Release construye paquetes y publicación multiarquitectura tras esos gates. No se certifica un SHA futuro con pruebas de un árbol anterior.

## Evidencia local del candidato

Los ensayos siguientes corresponden al código y artefactos locales de `e75dae4f529f458e83e93765a7f489094d879e28`, del 15 de octubre de 2026. No acreditan publicación de 1.2.0, disponibilidad remota de los paquetes ni CI de un commit posterior.

- **Actualización real por HTTP:** instalador oficial 1.1.0, checksum fijado y 12.798 archivos runtime verificados; manifiestos y paquetes 1.2.0 firmados con la clave pública fijada, sin firmas ficticias. Instalación, login, preparación, aprobación y once avances del ejecutor completados. Versión/identidad finales 1.2.0, módulos CreativeStudio/SocialPublishing instalados e inactivos y sus migraciones más guías aplicadas. Hashes del entorno completo y Passport idénticos; conservación de datos comprobada. Login renovado y panel HTTP 200, portada y `/up` correctos. Informe: `dist/verification-v120/REPORT.md`, cinco JSON redactados en `evidence/` (112 comprobaciones principales, cuatro escenarios y seis HTTP; no son conteos Pest).
- **Compatibilidad y propuestas:** core oficial 1.1.0 arrancó sobre otra extracción con copia post1.2 de 99 tablas, verificadas por hashes de todas las filas. Propuesta con marcador Git en modo gestionado y ZIP revisable; el inventario source y el runtime oficiales 1.1.0 difieren en `resources/css/generated/fontawesome.css`, que exige resolver conflicto en esa propuesta. Una personalización adicional de VERSION quedó intacta y bloqueó la actualización. La ruta standalone runtime completó sin conflictos. Los resultados no autorizan rollback automático después de iniciar migraciones ni sustituyen un respaldo operativo de cada instalación.
- **Instalaciones desde fuentes:** proyecto creado con `composer create-project` real, repositorio de paquete local y dist de fuentes 1.2.0; segunda plantilla con historia Git independiente y `composer install`. Ambas ejecutaron `npm ci` y build, instalación por navegador, 99 tablas/un administrador, login y superficies administrativas comprobados. Se probaron marcas termocms y dbcms; módulos opcionales inactivos y rutas directas de módulos desactivados devolviendo 404. Informe: `dist/verification-v120/source-installs/REPORT.md`. La prueba usa paquete local, no acredita aún una publicación Packagist 1.2.0.
- **Imagen de producción y CLI:** `cms:install` probado con base y volumen propios, sin demo, seguido de `/up` y login HTTP 200. Se comprobaron cinco workers de cola y scheduler. La imagen conserva `.env` en el volumen de storage mediante enlace y entrypoint. El [procedimiento Docker](DEPLOYMENT_GUIDE.md) arranca primero MySQL/Redis y realiza el aprovisionamiento completo con la CLI antes de iniciar app.
- **Navegador real:** login de administrador, campaña QA en CreativeStudio, texto manual y revisión, descarga de exportación ZIP comprobada. No se conectaron cuentas ni se hicieron generaciones facturadas o publicaciones externas. Este recorrido de interfaz no sustituye el piloto externo Matrix.

Las bases, contenedores, entornos, claves, cookies y respuestas privadas temporales de los ensayos de actualización y fuentes se eliminaron al terminar. Los informes conservan solo evidencia redactada. La prueba runtime de producción utiliza su base y volumen QA propios.

| Requisito | Evidencia | Estado local |
| --- | --- | --- |
| Versión 1.2.0 coherente | Regresión canónica y artefactos construidos de e75dae4 | Artefactos 1.2.0 comprobados; gate completo en ejecución |
| Bases 1.0.0/1.0.1 intactas y nueva 1.1.0 fijada | Constructor y manifiestos firmados de la nueva distribución | Build local firmado realizado; históricos sin reemplazar |
| Distribuciones firmadas sin estado privado | Paquetes locales y verificación RSA/inventarios del E2E | PASS local; CI/publicación remotas pendientes |
| Standalone 1.1.0 → 1.2.0 | Instalador publicado original y target local firmado | PASS HTTP real, identidad y migraciones comprobadas |
| Compatibilidad aditiva y recuperación | Core 1.1.0 sobre copia DB post1.2 verificada | PASS arranque/login; sin rollback automático ni DOWN |
| Propuesta gestionada y conflictos | Marcador Git, ZIP propuesto, VERSION personalizada | PASS; conflicto source/runtime fontawesome documentado |
| Fuentes Composer y plantilla independiente | Dos instalaciones y builds completos | PASS local; paquete Composer servido localmente |
| CreativeStudio y SocialPublishing | E2E, guías, rutas inactivas y recorrido manual de CreativeStudio | PASS en el alcance descrito; suite completa en ejecución |
| Guías, IA, Analytics, consentimiento y atribución | Tests correspondientes y frontend | Gate completo en ejecución; no se fija cifra anticipada |
| Arquitectura, análisis, estilo y regresión completa | `bin/ci-check`, workflow quality | Gate local completo en ejecución; CI remota pendiente |
| Runtime y procesos | CLI, cinco workers, scheduler, `/up` y login | PASS runtime local; alojamiento final pendiente |
| Proveedores externos y piloto Matrix | Cuentas autorizadas y recibos remotos | PENDIENTE; sin publicaciones externas |
| Restauración de copia previa | 220 hashes verificados, 219 fuentes trazadas | PASS archivo de trabajo; copia de producción independiente |

Las cifras históricas del runbook de marketing no se suman a estos ensayos. El gate completo se documentará al finalizar, con su SHA y resultado; no se presenta una validación local como CI remota ni una instalación local como release publicada.
