# Actualizaciones del núcleo dbCMS

## Identidad y distribución

El origen del núcleo es `dbravoan/dbcms`, independiente del repositorio, nombre comercial y versión del proyecto descendiente. El producto distribuido por Composer es `type: project`: `create-project` copia el código; `composer update` solo resuelve dependencias, no instala una versión nueva del núcleo.

Se consultan exclusivamente releases públicas estables publicadas. Ni un commit en main, ni un tag sin release, ni una prerelease autoriza descargar/aplicar código. Las versiones estables y sus artefactos publicados no se sustituyen. El registro privado de identidad incluye el inventario oficial verificado; no se deduce integridad de VERSION ni APP_VERSION.

Cada release de actualizaciones incorpora manifest.json y manifest.sig. La firma RSA/SHA-256 se verifica contra claves públicas instaladas; el manifiesto liga versión, SHA, requisitos, migraciones, paquetes y hashes de archivos. checksums.txt permite comprobar transferencia, pero no sustituye la firma. La clave privada nunca se incluye en repositorio, ZIP, imagen, informes o registros.

## Incorporación inicial y personalizaciones

Las instalaciones antiguas necesitan recibir una primera vez el código del actualizador mediante su mantenimiento habitual, con respaldo y revisión. Un CMS antiguo no puede recibir notificaciones de una función ausente. Después se incorpora su base oficial mediante el recorrido de adopción; se comparan los archivos con inventarios históricos firmados publicados en la release nueva. Los archivos de releases antiguas no se cambian para añadir estos inventarios.

La adopción registra la base y las diferencias; nunca copia el contenido local a un supuesto inventario oficial ni reemplaza archivos para hacerlos coincidir. Una versión sin base verificable permanece pendiente de revisión. Un proyecto copiado de un commit sin release no se declara limpio por compartir el número de versión.

La comparación utiliza base oficial, archivos locales y destino. Los archivos locales personalizados se conservan si el destino no los modifica; cambios en ambos lados, borrados y colisiones requieren revisión. Configuración privada, APP_KEY, claves Passport, datos, medios, módulos/temas propios y Git permanecen protegidos. Si composer.json, lock o la compilación se personalizan, se revisan las fuentes y dependencias; no se injerta vendor genérico en un proyecto distinto.

## Avisos y permisos

La comprobación automática tiene TTL de 12 horas y exclusión de consultas concurrentes. Con scheduler, ejecutar el scheduler Laravel de la instalación; sin cron, el panel solicita una comprobación separada cuando entra una persona autorizada. El botón permite comprobar manualmente, con límites para evitar saturación. Nunca se consulta GitHub desde una página pública ni se descargan paquetes durante una simple comprobación.

Los avisos privados se deduplican por usuario y versión. Requieren access:admin y manage:updates vigentes; el correo respeta las preferencias y el transporte configurado. No dependen de un módulo opcional activo. Los roles personalizados reciben el permiso solamente por asignación expresa.

Una caída de GitHub conserva la última información e indica el error y la fecha de intento. No equivale a estar actualizado. La página usa los mismos componentes en dbCMS y termoCMS. Preparar no significa aplicar ni confirmar. La aprobación humana reautenticada queda vinculada a actor, versión, manifiesto, plan de archivos y respaldo. No existe herramienta IA/MCP para aplicar actualizaciones.

## Instalaciones independientes

El panel presenta preparación, revisión de cambios y requisitos, respaldo, aprobación y progreso. Antes de aplicar exige alojamiento individual controlable, carpetas escribibles, espacio, drenaje de actividad y control del código cacheado. No necesita SSH, Composer, Node ni un worker para recorrer las fases HTTP.

El cierre de actualización es del núcleo y se comprueba antes de Laravel. Es distinto del mantenimiento editorial: cierra también API, administración ordinaria y entradas de procesos. El estado y registro se conservan fuera de acceso público, con permisos restrictivos. Un motor aislado puede continuar/recuperar sustituciones aunque Laravel o vendor no arranquen. No se extraen archivos directamente sobre la instalación.

Las fases se registran antes y después de cada cambio. Al reanudar, cada destino debe coincidir exactamente con su hash anterior o nuevo; una tercera variante requiere intervención. Una interrupción mantiene el cierre, nunca abre un sitio mezclado para anunciar éxito. No repetir operaciones externas o migraciones inciertas tras un timeout.

Se ejecutan migraciones pendientes del núcleo y de módulos ya instalados. No se reinstalan módulos, activan funciones, ejecutan seeders de demostración ni generan claves nuevas. El estado instalado cambia únicamente al terminar las verificaciones del código servido desde procesos nuevos. /up por sí solo no prueba que OPcache esté sirviendo la versión nueva.

## Respaldo y recuperación

Se respaldan archivos afectados y configuración privada. La base necesita una copia consistente y comprobación de restauración. El adaptador PDO no necesita mysqldump; cuando sus garantías o privilegios no alcanzan para reproducir la base, solicita respaldo externo en lugar de crear una copia incompleta.

El respaldo externo registra proveedor, referencia, fecha de creación, fecha/comprobación de restauración e informe privado con hash. Es evidencia aportada por una persona responsable; no debe mostrarse como una restauración automática realizada por el CMS. Una casilla marcada no constituye respaldo.

Nunca guardar copias bajo public ni permitir acceso HTTP a staging, journal, .env o claves. Mantener los respaldos privados y disponibles hasta comprobar el sitio y el procedimiento de recuperación. Un fallo de backup, permisos, espacio o verificación impide aplicar.

La recuperación automática solo restaura código cuando las migraciones permiten utilizar la versión anterior. Nunca se ejecuta migrate:rollback ni se restaura la base automáticamente. Ante migración parcial/incompatible o estado desconocido, mantener cierre y recuperar código/base de forma coordinada con el respaldo correspondiente. Conservar el estado de mantenimiento previo y no desactivar un cierre editorial que ya estuviera activo.

La pantalla independiente es `/update.php` (bajo la ruta base del dominio). Conserva una autorización limitada, una cookie HttpOnly y un token CSRF guardado en la pestaña original. Las operaciones caducan a los 15 minutos; se puede volver a verificar la contraseña mientras sigan vigentes la cookie de recuperación (24 horas) **y la sesión original**. Cada lote verifica la cuenta y sus permisos actuales mediante PDO, también durante un reemplazo de vendor. Una cuenta eliminada, permisos retirados, contraseña cambiada o sesión revocada detienen la continuación. El plazo de la cookie no amplía el de la sesión.

Si se cerró la pestaña, abrir `/update.php` desde el mismo navegador con su cookie y sesión todavía vigentes permite renovar la autorización verificando de nuevo la contraseña. Si ya no existe esa sesión o caducó la cookie de recuperación, no borrar `active.json` para forzar la apertura. El mantenedor debe revisar el journal, la copia verificada y las migraciones desde el almacenamiento privado y seguir el recorrido gestionado. Una migración incierta nunca se repite por pulsar «Continuar». Antes de migrar, un fallo de archivos con compatibilidad declarada puede restaurar solo el código respaldado; el sitio continúa cerrado hasta verificar la versión anterior en otra petición.

Si falla la copia antes de cambiar archivos, el CMS retira ese cierre, revoca la aprobación y vuelve a la preparación para adjuntar un informe externo y aprobar de nuevo. Un fallo durante la migración mantiene el cierre y exige recuperación guiada. Los registros de una copia interrumpida se conservan; no se repite silenciosamente una operación cuyo resultado no se conoce.

## Proyectos con Git, CI o contenedores

El panel prepara una propuesta de fuentes con inventario, diferencias, conflictos y requisitos. El mantenedor la incorpora a una rama del proyecto; revisa composer.json/lock, temas, módulos y assets, ejecuta su gate y despliega por su circuito existente. No se mezclan automáticamente historiales de plantillas ni se modifica un checkout desde HTTP.

Los contenedores y los despliegues de varios nodos requieren promoción de imagen/release y control de workers/OPcache por el orquestador del proyecto. El updater no promete poder reemplazar en vivo un sistema distribuido desde PHP. El panel reconoce la actualización al detectar la identidad nueva verificada.

La propuesta incluye el manifiesto y firma en `files/resources/updates/installed-manifest.*`: deben desplegarse junto al núcleo. La detección comprueba las fuentes firmadas, las personalizaciones previamente conservadas y las migraciones pendientes; no acredita por sí sola un respaldo externo. El responsable del despliegue debe crear, restaurar para comprobar y registrar su respaldo antes de promover el código. Una fusión manual que cambie el contenido de un archivo también modificado por la release permanece pendiente de revisión; no se presenta automáticamente como distribución oficial exacta.

## Consola para mantenedores

En desarrollo, usar el wrapper Docker del proyecto; en el servidor gestionado, su contenedor o entorno operativo habitual. No ejecutar Composer para actualizar el propio proyecto dbCMS.

```sh
./bin/sail-run php artisan cms:updates check --force
./bin/sail-run php artisan cms:updates adopt 1.0.1 --distribution=source --actor=ID
./bin/sail-run php artisan cms:updates prepare 1.1.0 --actor=ID
./bin/sail-run php artisan cms:updates status UUID --actor=ID
./bin/sail-run php artisan cms:updates proposal UUID --actor=ID --output=/ruta/privada/propuesta.zip
```

`ID` identifica una cuenta humana con `access:admin` y `manage:updates`; `UUID` es el identificador devuelto al preparar. Los servicios vuelven a comprobar permisos y propiedad. `adopt` incorpora una base oficial inicial; no borra diferencias locales ni sustituye una identidad ya registrada.

Para una instalación autónoma compatible, `cms:updates approve UUID --actor=ID --plan-hash=HASH --backup-evidence=/ruta/privada/evidencia.json` solicita confirmación y contraseña de forma interactiva. No existe `--yes` ni aprobación no interactiva. La evidencia contiene `hosting.single_host` y `hosting.no_unmanaged_workers` verdaderos; si se usa respaldo externo, también proveedor, referencia, fechas e informe privado con hash, igual que en el panel. Las fases posteriores se realizan mediante invocaciones separadas de `cms:updates advance UUID --actor=ID`. Los contenedores o checkouts Git reciben una propuesta y no eluden la comprobación del alojamiento por utilizar CLI.

Para cancelar antes del reemplazo: `cms:updates cancel UUID --actor=ID`. El historial privado del panel permite recuperar el identificador después de cerrar una pestaña. Cancelar después de comenzar a modificar archivos exige el procedimiento de recuperación; no elimina el cierre ni el journal.

## Publicación y firma

La release debe corresponder al SHA exacto validado en main. El workflow falla antes de construir si falta el secreto `CMS_UPDATE_SIGNING_KEY` o si su clave pública no coincide con `resources/updates/trusted-keys/releases-2026.pub`. El identificador inicial `releases-2026` está fijado en el workflow. El responsable guarda una copia privada recuperable y limita acceso al entorno de publicación. No regenerar la clave para cada build.

Para construir localmente en Docker se proporciona `DBCMS_UPDATE_SIGNING_KEY_FILE`, una ruta a un PEM privado **fuera del repositorio**, y `DBCMS_UPDATE_SIGNING_KEY_ID=releases-2026`. `bin/build-release-zip.sh` monta esa ruta en el contenedor; no imprime el PEM. El constructor de inventarios históricos recibe internamente las variables `CMS_UPDATE_SIGNING_KEY_FILE` y `CMS_UPDATE_SIGNING_KEY_ID`. No confundir estos nombres con secretos adicionales de GitHub.

La clave pública inicial está en resources/updates/trusted-keys/releases-2026.pub. La rotación necesita firma autorizada por una clave ya confiada; añadir un PEM arbitrario descargado junto al paquete no establece confianza. Las pruebas contractuales generan claves ficticias por ejecución; no usan la clave de publicación como fixture.

El workflow rechaza una versión que ya tenga release, incluso si se intenta volver a ejecutar con el mismo SHA: reconstruir un ZIP puede cambiar sus bytes y no autoriza sustituir artefactos estables firmados. Un fallo después de publicar parcialmente requiere revisar el incidente y publicar otra versión; no mover etiquetas ni reemplazar distribuciones ya entregadas.

Antes de publicar: pruebas de firma/manipulación, conflictos, adopción, backup/restauración, interrupción/concurrencia, denegaciones y HTTP raíz/public; Pest, navegador, PHPStan, Pint, auditorías y build en Docker. Comprobar artefactos de instalación y actualización sobre el mismo SHA. Un test local no demuestra despliegue ni entrega de correo externa.
