# Despliegue de dbCMS: GitHub Actions, rsync, Ansistrano y Docker

Para crear y publicar una web derivada mediante Actions, siga primero
[Proyectos derivados](DERIVED_PROJECTS_GUIDE.md): preparación de marca/temas/despliegue,
secretos, bootstrap, asistente web y actualizaciones por PR. Los instaladores
autónomos descritos al final pertenecen a la distribución del producto dbCMS.

El repositorio se verifica con Docker. `ci.yml` ejecuta Composer validate/audit,
Pint, PHPStan nivel 8, Pest, Vite y la construcción de producción. Releases y
despliegues exigen una ejecución correcta de ese CI para el SHA exacto que consumen,
sin repetir el gate completo. No hay PHP ni Composer en el host de los workflows.

## VPS/Plesk: alojamiento existente

El perfil `github-actions` conserva `deploy.yml`: GitHub construye y publica por
SSH/rsync después del CI del SHA exacto. Los perfiles `rsync` y `ansistrano` se
ejecutan desde tu máquina y funcionan con GitHub, GitLab u otro proveedor, incluso
sin `.git` en rsync. Solo se incluyen los controles del perfil seleccionado;
`none` permite usar un procedimiento propio sin automatización incluida.

Los tres perfiles nativos conservan el servidor web y PHP-FPM existentes. El
docroot apunta a `VPS_DIR/current/public`; se transfiere el directorio construido
a `releases/<release>` y `current` se cambia mediante un enlace atómico.
Composer, Vite y las pruebas corren en Docker de construcción. Los comandos
Artisan remotos utilizan la CLI PHP del VPS, seleccionada por `VPS_PHP_BINARY`
(ruta absoluta, por defecto `/usr/bin/php`). No requieren Docker ni registro OCI
en el destino. El preflight comprueba versión y extensiones antes de promover.

El servidor necesita PHP CLI/FPM 8.3+, Bash/GNU coreutils, rsync y curl; Ansistrano
necesita también Python3. El pool FPM del dominio debe ejecutar con el mismo usuario que SSH: los secretos
se conservan con permisos 0600. Use un pool dedicado o el propietario de la
suscripción de Plesk, y permiso sudo limitado para recargar su servicio.
Configure `VPS_FPM_SERVICE`; una recarga fallida hace fallar el despliegue.

### Documentos GEO dinámicos y exports antiguos

El comando `geo:generate-llms-txt` conserva su nombre, pero ahora exporta snapshots
privados a `storage/app/private/geo-export` (o `--output` absoluto privado). No
actualiza ficheros públicos. En el vhost Nginx/Plesk que sirve el proyecto, antes
de reglas genéricas para archivos estáticos, conservar el paso por Laravel:

```nginx
location = /llms.txt { rewrite ^ /index.php last; }
location = /llms-full.txt { rewrite ^ /index.php last; }
```

La configuración PHP del vhost debe preservar el URI original para que Laravel
resuelva esas rutas. El runtime Docker ya incluye esas locations. Retirar exports
históricos `public/llms.txt` y `public/llms-full.txt` tras revisarlos; no eliminar
contenidos o claves ajenos. Probar ambos endpoints después de retirar una publicación:
el corpus debe reflejar su disponibilidad vigente, también por mercado e idioma.
Un fichero generado una vez no debe sobrevivir como una segunda ruta de publicación.

Conserve `.env`, APP_KEY, claves Passport, storage y uploads. El script adopta
las ubicaciones heredadas `VPS_DIR/.env`, `VPS_DIR/storage` y `VPS_DIR/uploads`
mediante enlaces desde `shared/`, sin mover sus datos. También acepta datos ya
provisionados en `shared/.env`, `shared/storage` y `shared/uploads`. La `.env`
persistente debe existir; no se crea ni se rota una APP_KEY al actualizar.

```text
VPS_DIR/
  current -> releases/release-<fecha>-<sha>
  releases/
  shared/.env
  shared/storage/       # claves Passport, sesiones, logs y archivos
  shared/uploads/
```

Para `github-actions`, active `DEPLOY_ENABLED=true` y configure el entorno
`production`. Secretos: `VPS_HOST`, `VPS_PORT`, `VPS_USER`, `VPS_DIR`,
`VPS_SSH_KEY`, `VPS_SSH_FINGERPRINT` y `VPS_HEALTH_URL`. La huella es obligatoria.
El bootstrap explícito utiliza `VPS_ENV_FILE` solo si falta la configuración.
El workflow construye la aplicación y extrae su directorio con vendor y assets,
sin publicar una imagen en GHCR ni instalar Docker en el VPS.

Para desplegar desde su máquina, configure las variables indicadas en
[la guía de derivados](DERIVED_PROJECTS_GUIDE.md) y use `bin/deploy-rsync` o
`bin/deploy-ansistrano`. Las claves y el archivo de entorno de producción deben
estar fuera del proyecto. Los comandos verifican la huella SSH y construyen en
una copia temporal sin Git, secretos ni datos locales. No se generan ZIP/TAR ni
archivos `docker save`.

En sitios instalados prepara mantenimiento, migra una vez, construye cachés,
promueve `current`, recarga FPM y comprueba HTTP **200** de `/up`. En la primera
entrega conserva el asistente web. Si una migración ya comenzó, un error mantiene
mantenimiento y crea `shared/deployment-recovery-required`; exige revisión antes
de reintentar, sin rollback automático de código/base. El bloqueo exclusivo
`shared/.deployment-lock/owner` evita entregas concurrentes y persiste ante cortes.
Retire el marcador y el bloqueo únicamente después de revisar y recuperar la
combinación de código/base apropiada. Antes de migrar puede
recuperar el código anterior. Conserve copias verificadas y releases previas.

Para instalar un paquete construido o administrar la release desde el servidor:

```bash
# vps-artisan.sh utiliza la CLI PHP del VPS; configure VPS_PHP_BINARY si procede.
cd /ruta/al/cms/current
bash bin/vps-install.sh --db-host=127.0.0.1 --db-database=dbcms
bash bin/vps-artisan.sh cms:cache:purge
bash bin/vps-artisan.sh queue:restart
```

`vps-install.sh` exige vendor ya incluido; no instala dependencias en el host.
`vps-artisan.sh` ejecuta el archivo Artisan de la release con la CLI PHP nativa
y conserva las conexiones a los servicios configurados en su `.env` persistente. Para workers
administrados por Supervisor, use esa CLI con `queue:work --tries=1
--timeout=1200`; configure `retry_after` a 1500 y el cierre con el mismo margen.

## Docker de producción: alternativa explícita

El perfil `docker` tiene un comando local `bin/deploy-docker` y el control remoto
`bin/deploy-container.sh`. No depende de GitHub ni conserva workflows de Actions.
Construye la aplicación, publica en `CMS_IMAGE_REPOSITORY` y promueve el digest
obtenido del registro. Configure previamente la autenticación Docker local y de
lectura en el VPS; no se transmiten credenciales de GitHub. Los comandos siguientes
explican también la administración manual del runtime.

## Preparación

El servidor necesita Docker Engine con Compose v2 compatible con `up --wait`, acceso a SSH y espacio para imágenes y volúmenes. Configure HTTPS en el proxy de entrada. Para desarrollo local use Sail, no esta composición.

Guarde `docker-compose.prod.yml` y un `.env` privado en el directorio de despliegue. El `.env` debe contener una `APP_KEY` persistente y contraseñas propias para `DB_PASSWORD` y `DB_ROOT_PASSWORD`. Compose rechaza valores ausentes. La imagen nunca incorpora `.env`, claves privadas ni datos locales de `storage`. El `.env` que pueda escribir el instalador vive en el volumen de storage mediante un enlace; las variables que Compose inyecta tienen prioridad sobre ese archivo. Use en el instalador los mismos datos de conexión configurados para Compose.

Puede generar la clave dentro de la imagen sin arrancar servicios:

```bash
docker run --rm --entrypoint php ghcr.io/dbravoan/dbcms:<version> artisan key:generate --show
```

La clave debe mantenerse al sustituir imágenes: rotarla invalida sesiones y datos cifrados. Los volúmenes `app_storage`, `app_uploads` y `mysql_data` conservan el estado; `redis_data` pertenece al perfil opcional Redis. Realice copias verificadas de la base de datos, archivos y claves antes de migrar.

Los paquetes visuales ZIP se instalan en `storage/app/extensions/blocks` y se
conservan con `app_storage`. Los módulos de negocio pertenecen al código de la
composición: en Docker se incorporan a la imagen antes de instalarlos desde el
panel. La carga ZIP de módulos necesita un directorio `modules/` escribible y no
sustituye la construcción de una imagen con código inmutable.

Variables principales:

| Variable | Función |
| --- | --- |
| `CMS_IMAGE` | Imagen y etiqueta inmutable de la versión |
| `APP_KEY` | Clave estable de Laravel, obligatoria |
| `APP_URL` | URL HTTPS externa |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Contraseñas propias, obligatorias |
| `APP_PORT` | Puerto publicado, por defecto 80 |
| `CMS_THEME`, `CMS_BRAND`, `ADMIN_PATH` | Tema, marca y ruta de administración |
| `MAIL_*` | Transporte SMTP; por defecto el contenedor usa log |
| `RUN_MIGRATIONS` | Por defecto false; las migraciones se ejecutan como tarea única |

Compose usa archivos persistentes para sesiones y caché (también durante el instalador), y la base de datos para colas. Después de instalar puede seleccionar los drivers de base de datos para sesiones y caché. Redis se añade con `--profile redis`; configure los drivers correspondientes si esa composición lo requiere. Supervisor mantiene PHP-FPM, Nginx, el worker general y scheduler; los cuatro workers de marketing solo arrancan con sus módulos habilitados. Todos los procesos de aplicación se ejecutan como usuario `dbcms`. Los jobs de IA y orquestación tienen timeout de 1200 s; la reserva de la cola dura 1500 s para evitar que otro worker ejecute el mismo job antes de que termine.

## Instalación inicial

```bash
docker compose -f docker-compose.prod.yml config --quiet
docker compose -f docker-compose.prod.yml up -d --wait mysql
docker compose -f docker-compose.prod.yml run --rm app php artisan cms:install \
  --db-host=mysql \
  --without-demo \
  --site-name="Mi sitio" \
  --admin-path=admin \
  --brand=dbcms \
  --theme=db-cyber
docker compose -f docker-compose.prod.yml up -d --wait app
```

El comando `cms:install` realiza la instalación inicial completa: conexión, migraciones, roles, administrador y cierre del instalador. Pregunta de forma interactiva el nombre, correo y contraseña del administrador. Hereda la base de datos, usuario y contraseña que Compose inyecta; `--db-host=mysql` selecciona el servicio interno y evita volver a preguntar esos datos. Ajuste `--admin-path`, `--brand` y `--theme` a los valores elegidos para Compose; el ejemplo usa `admin`, `dbcms` y `db-cyber`. No ejecute solo `migrate` para sustituir este aprovisionamiento inicial.

La CLI de instalación se ha probado en una imagen de producción local 1.2.0. La imagen enlaza `.env` con `storage/app/runtime/.env`; el entrypoint prepara el volumen persistente y ejecuta la CLI como usuario `dbcms`, de modo que la configuración escrita por el instalador permanece al retirar el contenedor temporal `run --rm`. Conserve ese volumen al arrancar `app` y compruebe `/up` y el login. El entrypoint crea las claves Passport únicamente si faltan, conserva `oauth-private.key` con permisos 0600 y construye las cachés de Laravel. Un error de caché detiene el arranque en lugar de ocultarse. La aplicación dispone de `/up` para salud.

## Rollback y mantenimiento

En Docker, `deploy-container.sh` consume `CMS_IMAGE` por digest y `shared/.env`.
Antes de comenzar migraciones puede recuperar la imagen anterior. Después de
iniciarlas, un fallo detiene la aplicación y deja el marcador de recuperación
para revisar la base y el código conjuntamente. No revierte la base de datos.

```bash
docker compose -f docker-compose.prod.yml logs --tail=100 app
docker compose -f docker-compose.prod.yml exec app php artisan cms:cache:purge
docker compose -f docker-compose.prod.yml exec app php artisan queue:restart
```

No elimine los volúmenes para actualizar. La desinstalación de un módulo del CMS conserva sus tablas y datos, y bloquea su disponibilidad; la eliminación definitiva de datos es una operación distinta.

## Distribuciones ZIP

La release publica **`dbcms-installer-v<VERSIÓN>.zip`** como archivo principal. No descargue los enlaces automáticos **Source code (zip/tar.gz)** de GitHub para una instalación: están destinados a desarrollo y no incluyen las dependencias preparadas.

Instalación sin consola en Plesk/cPanel:

1. Descargue `dbcms-installer-v1.1.0.zip`, compruebe su SHA256 con `checksums.txt` y súbalo desde el administrador de archivos.
2. Extráigalo en el directorio del dominio. Cuando el panel lo permita, configure la raíz pública del dominio en `public/`; el `.htaccess` de la raíz ofrece compatibilidad para Apache cuando no pueda cambiarla.
3. Compruebe que el usuario PHP puede escribir en `storage/`, `bootstrap/cache/` y en el directorio raíz para crear `.env`. Use los permisos del panel y evite `777`.
4. Abra el dominio por HTTPS. La primera petición crea la clave cifrada y abre el asistente web.
5. Pegue los datos de una base MySQL/MariaDB vacía. Si la cuenta no puede crear bases, créela antes desde el panel.
6. Complete el administrador, la marca y el tema; después compruebe el acceso al panel, la portada y `/up`.

El ZIP incorpora `EMPEZAR.html`, `vendor/`, los recursos compilados y la plantilla previa a la instalación. No incorpora `.env`, credenciales, claves OAuth ni el marcador `storage/installed`.

`bin/build-release-zip.sh` delega en el contenedor CI al invocarlo desde el host. Exporta el commit de Git, instala dependencias de producción, compila Vite, inspecciona el contenido y prueba la primera petición HTTP sobre el archivo desempaquetado. El workflow de release exige que la versión coincida con `VERSION` y que el SHA exacto haya superado CI; después publica los archivos con SHA256 y la imagen OCI. La imagen OCI y Compose son una alternativa opcional, no un requisito para servir la web.
