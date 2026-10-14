# Despliegue de dbCMS: VPS/Plesk y alternativa Docker

El repositorio se verifica con Docker. `ci.yml` ejecuta Composer validate/audit,
Pint, PHPStan nivel 8, Pest, Vite y la construcción de producción. Releases y
despliegues exigen una ejecución correcta de ese CI para el SHA exacto que consumen,
sin repetir el gate completo. No hay PHP ni Composer en el host de los workflows.

## VPS/Plesk: alojamiento existente

`deploy.yml` conserva el servidor web y PHP-FPM existentes. El docroot sigue en
`VPS_DIR/current/public`; cada paquete se extrae en `releases/<release>` y
`current` se cambia mediante un enlace atómico. Composer, Vite y las pruebas
corren en Docker CI. Las migraciones y comandos Artisan remotos corren en un
contenedor CLI con el mismo PHP 8.3 y extensiones, sin ejecutar PHP/Composer en
el host. No se sustituye el alojamiento web por Docker Compose.

El servidor necesita Docker para la CLI, Bash/GNU coreutils, curl y PHP-FPM 8.3+
con las extensiones requeridas por Composer. El usuario SSH debe poder ejecutar
Docker, ser el propietario compatible con PHP-FPM de los archivos persistentes y
recargar el servicio mediante `sudo -n systemctl reload`. Configure
`VPS_FPM_SERVICE` para su servicio; si está vacío se prueban `plesk-php83-fpm`,
`php8.3-fpm` y `php-fpm`. Una recarga fallida hace fallar el despliegue.

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

Active `VPS_DEPLOY_ENABLED=true` en variables del repositorio y configure el
entorno `production`. Secretos: `VPS_HOST`, `VPS_PORT`, `VPS_USER`, `VPS_DIR`,
`VPS_SSH_KEY` y `VPS_SSH_FINGERPRINT`. `VPS_HEALTH_URL` es opcional: por defecto
se usa APP_URL de la `.env` persistente. La huella SSH es obligatoria.

El workflow envía el paquete con vendor y assets compilados, sus checksums y una
imagen CLI identificada por SHA. Verifica los hashes, carga esa imagen y llama a
`bin/deploy-vps-release.sh`. Este entra en mantenimiento, ejecuta una única
migración, prepara cachés, cambia `current`, recarga PHP-FPM, reinicia las colas y
exige HTTP **200** de `/up`. Cualquier error falla el job y restaura el enlace de
código anterior cuando existe. Las migraciones NO se revierten: deben ser
aditivas y compatibles con ambas versiones. No se promete ausencia de
interrupción; conserve copias verificadas y releases anteriores.

Para instalar un paquete construido o administrar la release desde el servidor:

```bash
# Usa la imagen CLI cargada por el despliegue, o una imagen publicada y fijada.
export VPS_CLI_IMAGE=dbcms-cli:<sha>
cd /ruta/al/cms/current
bash bin/vps-install.sh --db-host=127.0.0.1 --db-database=dbcms
bash bin/vps-artisan.sh cms:cache:purge
bash bin/vps-artisan.sh queue:restart
```

`vps-install.sh` exige vendor ya incluido; no instala dependencias en el host.
`vps-artisan.sh` monta el árbol en su misma ruta absoluta y usa la red del host
Linux para mantener conexiones a MySQL/Redis en localhost. Para workers
administrados por Supervisor, use esa CLI con `queue:work --tries=1
--timeout=1200`; configure `retry_after` a 1500 y el cierre con el mismo margen.

## Docker de producción: alternativa explícita

Esta modalidad es opcional y tiene su propio script `bin/deploy-container.sh`.
El workflow VPS/Plesk no la activa. Los comandos siguientes son para quien haya
elegido alojar la aplicación completa en contenedores.

## Preparación

El servidor necesita Docker Engine con Compose v2 compatible con `up --wait`, acceso a SSH y espacio para imágenes y volúmenes. Configure HTTPS en el proxy de entrada. Para desarrollo local use Sail, no esta composición.

Guarde `docker-compose.prod.yml` y un `.env` privado en el directorio de despliegue. El `.env` debe contener una `APP_KEY` persistente y contraseñas propias para `DB_PASSWORD` y `DB_ROOT_PASSWORD`. Compose rechaza valores ausentes. La imagen nunca incorpora `.env`, claves privadas ni datos locales de `storage`. El `.env` que pueda escribir el instalador vive en el volumen de storage mediante un enlace; las variables que Compose inyecta tienen prioridad sobre ese archivo. Use en el instalador los mismos datos de conexión configurados para Compose.

Puede generar la clave dentro de la imagen sin arrancar servicios:

```bash
docker run --rm --entrypoint php ghcr.io/dbravoan/dbcms:<version> artisan key:generate --show
```

La clave debe mantenerse al sustituir imágenes: rotarla invalida sesiones y datos cifrados. Los volúmenes `app_storage`, `app_uploads`, `mysql_data` y `redis_data` conservan el estado. Realice copias verificadas de la base de datos, archivos y claves antes de migrar.

Variables principales:

| Variable | Función |
| --- | --- |
| `CMS_IMAGE` | Imagen y etiqueta inmutable de la versión |
| `APP_KEY` | Clave estable de Laravel, obligatoria |
| `APP_URL` | URL HTTPS externa |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD` | Contraseñas propias, obligatorias |
| `APP_PORT` | Puerto publicado, por defecto 80 |
| `CMS_THEME`, `CMS_BRAND`, `ADMIN_PATH` | Tema, marca y ruta de administración |
| `CMS_ORCHESTRATION_ENABLED` | Habilita la orquestación nativa cuando se configura true |
| `MAIL_*` | Transporte SMTP; por defecto el contenedor usa log |
| `RUN_MIGRATIONS` | Por defecto false; las migraciones se ejecutan como tarea única |

Compose usa Redis para sesiones, caché y colas. Supervisor mantiene PHP-FPM, Nginx y un worker como usuario `dbcms`. Los jobs de IA y orquestación tienen timeout de 1200 s; la reserva de la cola dura 1500 s para evitar que otro worker ejecute el mismo job antes de que termine.

## Instalación inicial

```bash
docker compose -f docker-compose.prod.yml config --quiet
docker compose -f docker-compose.prod.yml up -d --wait mysql redis
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
docker compose -f docker-compose.prod.yml up -d --wait app
```

Complete la configuración del CMS mediante el instalador correspondiente. El entrypoint crea las claves Passport únicamente si faltan, conserva `oauth-private.key` con permisos 0600 y construye las cachés de Laravel. Un error de caché detiene el arranque en lugar de ocultarse. La aplicación dispone de `/up` para salud.

## Rollback y mantenimiento

En la alternativa Docker, si la promoción falla, `deploy-container.sh` restaura la imagen de aplicación anterior cuando existe. No revierte la base de datos. Las migraciones deben ser aditivas y compatibles con ambas versiones durante la transición. Cambios destructivos requieren un plan y copias de seguridad propios.

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
