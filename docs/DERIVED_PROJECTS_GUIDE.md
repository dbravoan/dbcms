# Aplicaciones hijas: crear, personalizar, desplegar y actualizar

Una aplicación hija tiene su código, datos, claves y versión comercial propios.
El perfil `cms-project.json` registra la composición y procedencia oficial del
núcleo. Las actualizaciones se revisan; no se mezcla el historial de la plantilla
con `git pull`.

Este recorrido requiere una **release estable publicada que incluya el bootstrap
guiado**. La función forma parte del contrato v3; ni este documento ni cambiar
`VERSION` publican una versión nueva en GitHub o Packagist. Sin una estable compatible el comando
falla antes de cambiar fuentes. La versión 3.x está orientada a instalaciones
nuevas y no convierte automáticamente datos 1.x.

## 1. Obtener fuentes y ejecutar el asistente

El arranque local guiado requiere Linux con Docker y Compose. No ejecutes PHP
ni Composer en el equipo.
Elige una entrada; todas terminan en `make init` dentro de la carpeta del proyecto.

### Composer dentro de Docker

Composer resuelve la última versión estable por defecto ([referencia oficial](https://getcomposer.org/doc/03-cli.md#create-project));
el ejemplo no fija una versión ni solicita una rama de desarrollo:

```bash
docker run --rm --user "$(id -u):$(id -g)" \
  --env COMPOSER_HOME=/tmp/composer \
  --volume "$PWD:/workspace" --workdir /workspace \
  laravelsail/php83-composer:latest \
  composer create-project --no-install --no-scripts --remove-vcs dbravoan/dbcms mi-web
cd mi-web
make init
```

### GitHub Template o ZIP de fuentes

En [dbCMS](https://github.com/dbravoan/dbcms), usa **Use this template → Create a new
repository**, clona tu repositorio y entra en su carpeta. Si prefieres el ZIP de
fuentes, extráelo y entra en esa carpeta; Git no es obligatorio. Ejecuta:

```bash
make init
```

Sin Make, usa `./bin/project-init`. `make` sin argumentos muestra ayuda.
El ZIP de fuentes requiere este entorno de desarrollo. El instalador ZIP para
hosting incluye dependencias y assets y sigue su propio recorrido web, descrito
en el [README](../README.md#instalar-en-hosting-sin-consola).

### Qué pregunta y qué prepara

El asistente sugiere un nombre a partir de la carpeta actual, dbCMS como marca,
`db-cyber` como tema y todas las fuentes de módulos/paquetes. El nombre empieza
por una letra minúscula y usa segmentos alfanuméricos separados por guiones.
Puedes elegir TermoCMS, otros temas y selecciones personalizadas con etiquetas y
dependencias resueltas. El despliegue requiere una elección explícita:

| Valor | Ejecutor y herramientas conservadas |
|---|---|
| `github-actions` | GitHub: CI, actualización por PR y despliegue por SSH/rsync. |
| `ansistrano` | Tu equipo: Docker para Ansible, playbook y controles de releases. |
| `rsync` | Tu equipo: rsync por SSH y controles de releases. |
| `docker` | Tu equipo: registro OCI y despliegue remoto por digest. |
| `none` | Desarrollo y comprobaciones, sin controles de despliegue. |

Revisa versión/SHA, selección, dependencias, cambios de fuentes y configuración
local antes de confirmar. Cancelar no modifica fuentes ni entorno. La preparación
resuelve una vez la última estable oficial, excluye borradores/prereleases,
verifica su inventario firmado y las fuentes Git y trabaja en la misma carpeta.
Una copia oficial de desarrollo verificable se normaliza a esa estable. Rechaza
archivos oficiales modificados, colisiones, rutas inseguras, origen no verificable
o releases incompatibles; no hay opción para forzar sobreescrituras.

Conserva `.git`, archivos propios ajenos a la distribución, `.env`, datos y claves.
Retira las fuentes excluidas y controles oficiales de otros despliegues. Las
fuentes elegidas no instalan ni activan módulos; esa decisión pertenece al
instalador y al ciclo de extensiones.

Después instala las dependencias bloqueadas, crea `.env` solo si falta, configura
identidad Compose, puertos disponibles y prefijos separados de sesiones/caché/Redis,
y desactiva demo y acceso rápido en el entorno nuevo. Conserva valores y claves
existentes, genera solo una APP_KEY ausente, arranca Sail, espera a los servicios y
compila assets. No migra, no genera claves OAuth ni crea usuarios demo.
Abre la URL `/install` impresa y completa el instalador web sin demo.

El comando muestra las URL reales de aplicación/herramientas y cómo parar o
reiniciar. Para uso diario:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail down
```

Si falla la descarga, dependencias, asignación de puertos o build, repite `make init`.
Un perfil válido fija versión y composición: reanuda la preparación local sin
consultar la última release ni convertir el arranque en una actualización. No
copies el entorno de otra web ni elimines volúmenes para reintentar.
Consulta el [runbook de Sail](06_ZERO_HOST_PHP_SAIL_RUNBOOK.md) para dependencias
con propietarios antiguos y herramientas del contenedor.

## 2. Selecciones explícitas y automatización

Para un proyecto nuevo, los scripts sin preguntas exigen nombre, marca, tema y
despliegue explícitos. Para reanudar un perfil válido pueden omitirse: se conservan
sus valores registrados.
Los selectores `--modules` y `--block-packages` aceptan `all`, `none` o CSV de
identificadores de sus manifiestos; ambos tienen `all` como valor predeterminado.
`none` vacía la selección directa, pero no suprime dependencias necesarias.
`--theme` acepta un identificador, CSV ordenado o `all`; el primer tema es inicial,
y `all` comienza con `db-cyber`. Los identificadores no son nombres de carpetas.

En CI usa siempre **ambas** opciones, para preparar únicamente fuentes:

```bash
./bin/project-init mi-web --brand=dbcms --theme=db-minimal --deploy=rsync \
  --modules=blog --block-packages=basic --prepare-only --no-interaction
```

No crea `.env`, no inicia Sail ni compila el entorno local. Dentro de un contenedor
de bootstrap/CI ejecuta PHP/Composer directamente; nunca anides Sail. Sin
`--prepare-only`, `--no-interaction` mantiene la preparación local completa.
Los mismos requisitos de procedencia y release publicada se aplican a los flags.

`cms-project.json` registra marca, temas, despliegue, versión/SHA y directorios
exactos con dependencias. Por ejemplo, la propiedad de fuentes de Blog y Basic:

```json
{
  "source_selection": {
    "module_directories": ["Blog"],
    "block_package_directories": ["Basic"],
    "include_showcase": false
  }
}
```

El comando escribe el manifiesto completo. La selección queda congelada: `all`
no incorpora futuras extensiones al actualizar. Las hijas nuevas excluyen el
showcase. Un perfil existente sin `source_selection` conserva su significado de
catálogo completo; no se deduce de carpetas ausentes. El SHA procede del tag oficial,
no del commit independiente que GitHub crea para el derivado. No guardes secretos
en el manifiesto ni vuelvas a inicializar para cambiar la composición.

## 3. Preparar una PR desde GitHub Actions

Como alternativa de automatización para un repositorio creado con Template:

1. Configura `PROJECT_BOT_TOKEN`, limitado al repositorio nuevo con escritura en
   **Contents**, **Pull requests** y **Workflows**. El token automático no sustituye
   el permiso de edición de workflows; no se heredan secretos de dbCMS.
2. Abre **Actions → Preparar proyecto → Run workflow**. Introduce nombre, marca,
   temas, módulos, paquetes y despliegue explícitos.
3. Revisa el resumen y la PR: el workflow usa `--prepare-only --no-interaction`,
   prepara fuentes en Docker y no inicia Sail. Abre siempre una PR en borrador;
   no ejecuta allí la batería de calidad completa.
4. Revisa los cambios y ejecuta localmente `bin/prepare-delivery --kind=deploy
   --sha=SHA_COMPLETO` sobre el commit propuesto, con la clave privada de entrega
   del proyecto. Fusiona solo después de validar. En `github-actions`, el despliegue sigue
   desactivado hasta configurar `DEPLOY_ENABLED=true`; los perfiles locales retiran
   los workflows oficiales. Clona el resultado y ejecuta `make init` para reanudar
   su entorno local con el perfil ya registrado.

El workflow puede retirarse a sí mismo: la ejecución actual termina normalmente.
Los archivos propios se conservan. Git es opcional en perfiles locales: puedes
crear tu repositorio después o desplegar una carpeta mediante rsync. Los controles
de despliegue y sus credenciales se preparan en los apartados siguientes.

## 4. Preparar el VPS y las credenciales

GitHub Actions usa actualmente Linux AMD64. Los despliegues locales construyen
la plataforma del VPS con Docker; usa un VPS x86_64 para los ejemplos. El equipo
desde el que despliegas necesita Docker, OpenSSH y rsync; no necesita PHP,
Composer, Ansible ni un repositorio Git para desplegar por rsync.

En `github-actions`, `rsync` y `ansistrano`, el VPS necesita PHP CLI y PHP-FPM
8.3+ con las extensiones de la aplicación, Bash, rsync, curl y utilidades GNU.
Ansistrano requiere además Python 3 en el destino. Docker y acceso a un registro
solo son requisitos del VPS cuando eliges `docker`. Node y Composer se ejecutan
en el contenedor de construcción. Las funciones opcionales que usan FFmpeg u
otros procesos conservan los requisitos de sus módulos.

Provisiona dominio, HTTPS y servicios antes de desplegar. Para alojamiento nativo,
el document root apunta a `VPS_DIR/current/public`. Para Docker, configura el
proxy HTTPS hacia el puerto publicado. Usa directorio, usuario y datos propios
por proyecto. El usuario de despliegue debe tener acceso SSH con shell habilitada, poder escribir
sus datos y recargar el servicio FPM mediante un permiso sudo limitado. El pool PHP-FPM
del dominio debe ejecutar con el **mismo usuario que `VPS_USER`**: `.env` y la
clave privada OAuth tienen permisos `0600`. En Plesk usa el propietario de la
suscripción como usuario SSH. Con Nginx/Apache configura un pool dedicado, por
ejemplo `user=deploy` y `group=deploy` si `VPS_USER=deploy`; no uses el pool genérico
`www-data` con otro propietario ni amplíes los permisos de los secretos.

### 4.1. Preparar tu clave SSH en el ordenador local

Los ejemplos de este apartado se ejecutan en tu equipo Linux, desde Bash. Usa
una clave de despliegue propia de esta web y un nombre de archivo que no exista.
La privada queda fuera de la carpeta del proyecto y debe ser un archivo normal,
sin enlace simbólico:

```bash
# EN TU EQUIPO: crear una clave nueva, sin sustituir otras claves existentes.
install -d -m 700 "$HOME/.ssh"
ssh-keygen -t ed25519 -C "deploy mi-web" -f "$HOME/.ssh/mi-web-deploy" -N ""
chmod 600 "$HOME/.ssh/mi-web-deploy"
chmod 644 "$HOME/.ssh/mi-web-deploy.pub"
```

Se crean dos archivos:

| Archivo | Dónde se utiliza |
|---|---|
| `~/.ssh/mi-web-deploy` | Clave privada: permanece en tu equipo; `VPS_SSH_KEY_FILE` apunta aquí. |
| `~/.ssh/mi-web-deploy.pub` | Clave pública: se autoriza para el usuario `deploy` del VPS. |

Este recorrido usa una clave dedicada **sin passphrase** porque el controlador
Docker de Ansistrano y el workflow no implementan desbloqueo interactivo ni
montan tu `ssh-agent`. No elimina ni modifica la passphrase de tus otras claves.
La cuenta `deploy` debe tener únicamente los permisos necesarios para esta web.
Las opciones de generación y autenticación están descritas en los manuales de
[ssh-keygen](https://man.openbsd.org/ssh-keygen) y
[ssh](https://man.openbsd.org/ssh).

### 4.2. Autorizar la pública y comprobar la identidad del servidor

Obtén primero la huella del servidor por su consola del proveedor, Plesk o un
administrador que ya tenga acceso verificado. Este comando se ejecuta **en el
VPS**, no sobre la clave de tu portátil:

```bash
# EN LA CONSOLA DEL VPS: huella de la clave pública del servidor SSH.
ssh-keygen -l -E sha256 -f /etc/ssh/ssh_host_ed25519_key.pub
```

Guarda el valor `SHA256:...` como `VPS_SSH_FINGERPRINT`. Esta huella identifica al
**servidor**; la pública `mi-web-deploy.pub` identifica a tu usuario. Son claves
distintas.

Autoriza tu pública para el usuario que desplegará. Si dispones de acceso inicial
por contraseña y tienes `ssh-copy-id` instalado, desde tu equipo:

```bash
# EN TU EQUIPO: ajusta usuario, host y puerto; confirma solo la huella verificada.
ssh-copy-id -i "$HOME/.ssh/mi-web-deploy.pub" -p 22 \
  -o HostKeyAlgorithms=ssh-ed25519 deploy@web.example.com
```

También puedes añadir el contenido completo de `.pub` como una línea en
`/home/deploy/.ssh/authorized_keys` desde el panel o la consola. Conserva las
claves que ya existan. En el VPS, el directorio `.ssh` y `authorized_keys` deben
pertenecer a `deploy`, con permisos `700` y `600`, respectivamente. Nunca subas
la clave privada al servidor para autorizar el acceso.

Comprueba desde tu equipo que autentica sin pedir contraseña ni passphrase:

```bash
ssh -i "$HOME/.ssh/mi-web-deploy" -p 22 \
  -o IdentitiesOnly=yes -o BatchMode=yes -o HostKeyAlgorithms=ssh-ed25519 \
  deploy@web.example.com 'id -un'
```

Debe responder `deploy`. Si falla, corrige usuario, puerto, pública autorizada y
permisos antes de construir una entrega. No necesitas un alias en `~/.ssh/config`:
los desplegadores reciben explícitamente estos datos y crean un `known_hosts`
temporal con verificación estricta contra `VPS_SSH_FINGERPRINT`.

### 4.3. Exportar la configuración local de despliegue

Para los perfiles locales exporta las variables en tu terminal. No uses `source`
sobre el `.env` de Laravel ni guardes claves en el repositorio:

```bash
export VPS_HOST=web.example.com
export VPS_USER=deploy
export VPS_PORT=22
export VPS_DIR=/srv/www/mi-web
export VPS_SSH_KEY_FILE="$HOME/.ssh/mi-web-deploy"
export VPS_SSH_FINGERPRINT='SHA256:HUELLA_VERIFICADA_POR_OTRO_CANAL'
export VPS_HEALTH_URL=https://web.example.com
export VPS_PHP_BINARY=/usr/bin/php
export VPS_FPM_SERVICE=php8.3-fpm
```

Comprueba la huella con el administrador del servidor. La conexión verifica esa
huella y no acepta automáticamente una clave desconocida.

Las variables de la terminal duran esa sesión. Puedes conservar sus `export` en
un archivo privado fuera del repositorio y cargar **ese archivo de configuración
de despliegue**, no el `.env` de Laravel. En Plesk, `VPS_PHP_BINARY` puede ser
`/opt/plesk/php/8.3/bin/php`; confirma que corresponde al PHP-FPM del dominio.

El usuario de despliegue necesita permiso para la recarga no interactiva del
servicio elegido. Comprueba en el VPS, con ese usuario y el nombre real del
servicio:

```bash
# EN EL VPS: requiere un permiso sudo limitado configurado por su administrador.
sudo -n systemctl reload php8.3-fpm
```

El desplegador no crea ese permiso ni configura el pool FPM.

### 4.4. Credenciales cuando despliega GitHub Actions

En el perfil `github-actions`, crea **Settings → Environments → production** y
configura allí estos secretos:

| Secreto | Contenido |
|---|---|
| `VPS_HOST`, `VPS_USER`, `VPS_PORT`, `VPS_DIR` | Destino del proyecto; puerto predeterminado 22. |
| `VPS_SSH_KEY` | Contenido completo del archivo privado, incluyendo cabecera y cierre; no la ruta ni el archivo `.pub`. |
| `VPS_SSH_FINGERPRINT` | Huella ED25519 SHA256 verificada. |
| `VPS_HEALTH_URL` | URL HTTPS pública que se comprobará. |
| `VPS_ENV_FILE` | Opcional: contenido del `.env` de producción cuando eliges crearlo mediante bootstrap. Si ya existe en el VPS, omítelo y desactiva `bootstrap`. |

Configura `DEPLOY_ENABLED=true` en las variables del repositorio cuando esté
preparado. `VPS_PHP_BINARY` y `VPS_FPM_SERVICE` son variables de configuración del
perfil nativo. Los perfiles locales no utilizan secretos ni variables de GitHub.

### 4.5. Elegir dónde crear el `.env` de producción

Puedes prepararlo en tu equipo para el bootstrap opcional o crearlo directamente
en el VPS. En ambos casos debe contener una APP_KEY propia, válida y estable,
`APP_URL`, los datos de la base y la configuración del proyecto. El `.env` de
desarrollo del ordenador no se transfiere durante el despliegue.

Para una instalación nueva, puedes obtener su APP_KEY dentro del contenedor local:

```bash
./vendor/bin/sail artisan key:generate --show
```

Guárdala en un archivo privado de producción fuera del proyecto. En GitHub
Actions, guarda su contenido como `VPS_ENV_FILE`. El entorno debe
incluir `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, identidad,
marca/tema seleccionados, `CMS_SEED_DEMO=false` y los datos de sus servicios.
En Docker incluye también `DB_PASSWORD` y `DB_ROOT_PASSWORD`; Compose administra
MySQL/Redis. No reutilices las credenciales de ejemplo.

El bootstrap escribe `shared/.env` una sola vez; una repetición no lo sustituye.
Cambiar el secreto posteriormente no rota automáticamente el entorno instalado.

**Si prefieres crear el archivo directamente en el VPS**, no necesitas una copia
local de las credenciales de producción. Provisiona el directorio con su
administrador y edita el archivo como el usuario de despliegue. Ejemplo para
`VPS_DIR=/srv/www/mi-web` y `VPS_USER=deploy`, con grupo `deploy`.
El administrador debe haber creado esa cuenta y su grupo; en Plesk utiliza la
cuenta real de la suscripción en todos los ejemplos:

```bash
# EN EL VPS: preparar directorios; no reemplaza ningún archivo de configuración.
sudo install -d -o deploy -g deploy -m 755 /srv/www/mi-web
sudo install -d -o deploy -g deploy -m 755 /srv/www/mi-web/shared
sudo -u deploy sh -c 'umask 077; touch /srv/www/mi-web/shared/.env'
sudo chmod 600 /srv/www/mi-web/shared/.env
sudo -u deploy nano /srv/www/mi-web/shared/.env
```

Usa el editor o el panel del servidor que tengas disponible. El archivo debe
pertenecer a `deploy` y ser legible por el proceso correspondiente; en alojamiento
nativo, ese es también el usuario del pool FPM. Si ya contiene una APP_KEY de una
instalación existente, consérvala. No ejecutes `key:generate` para actualizarla.

Para generar **una clave nueva** sin pasarla por tu ordenador, en un VPS nativo:

```bash
# EN EL VPS NATIVO: pega la salida en APP_KEY del archivo privado.
/usr/bin/php -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

En un VPS que solo tiene Docker, puedes obtenerla dentro de un contenedor:

```bash
# EN EL VPS DOCKER: tampoco requiere PHP instalado en el host.
docker run --rm --entrypoint php php:8.3-cli-alpine \
  -r 'echo "base64:".base64_encode(random_bytes(32)).PHP_EOL;'
```

Sustituye `/usr/bin/php` por la CLI real si corresponde. Ejecuta únicamente la
alternativa de tu perfil, una sola vez al crear la instalación. No forma parte
de las actualizaciones.

| Perfil | `.env` preparado en el destino | Cómo desplegar sin enviarlo |
|---|---|---|
| `rsync` | `VPS_DIR/shared/.env` | En tu equipo, `unset VPS_ENV_FILE_PATH` y ejecuta `./bin/deploy-rsync`. |
| `ansistrano` | `VPS_DIR/shared/.env` | En tu equipo, `unset VPS_ENV_FILE_PATH` y ejecuta `./bin/deploy-ansistrano`. |
| `docker` | `VPS_DIR/shared/.env`, como entrada de Compose | En tu equipo, `unset VPS_ENV_FILE_PATH` y ejecuta `./bin/deploy-docker`. |
| `github-actions` | `VPS_DIR/shared/.env` | Ejecuta el workflow con `bootstrap` desactivado; no necesitas el secreto `VPS_ENV_FILE`. |
| `none` | Según tu procedimiento | No hay scripts que creen o adopten configuración. |

Con archivo remoto ya preparado, no dejes `VPS_ENV_FILE_PATH` apuntando a un
archivo local inexistente: el desplegador valida esa ruta si está definida.
Los perfiles nativos también admiten un `.env` heredado en `VPS_DIR/.env`;
crean el enlace `shared/.env` hacia él si todavía no existe. Docker exige la
ubicación `shared/.env`; usa esa ruta común para los proyectos nuevos.

No prepares el entorno dentro de una release, por ejemplo
`releases/release-.../.env`: los perfiles nativos sustituyen esa ubicación por
un enlace al archivo persistente. Si falta `shared/.env` y no has solicitado
bootstrap ni existe la ubicación heredada nativa, la entrega no se completa.

En Docker hay dos archivos con funciones diferentes. `VPS_DIR/shared/.env`
permanece en el host y aporta las variables declaradas en Compose. Dentro del
volumen persistente, el contenedor prepara `storage/app/runtime/.env`, al que
apunta `/var/www/html/.env`; no monta el archivo del host directamente. La APP_KEY
debe coincidir en ambos. Una variable propia adicional del archivo del host
necesita su correspondiente entrada en `environment` de `docker-compose.prod.yml`
si quieres que llegue al contenedor.

## 5. Ejecutar el método elegido

### Rsync desde tu máquina

```bash
./bin/deploy-rsync
```

Construye una copia sin secretos ni datos locales, instala los locks y compila
los assets en Docker. Transfiere directamente el directorio a una release nueva
y ejecuta sus controles remotos. No necesita Git, GitHub ni un registro OCI.

### Ansistrano desde tu máquina

```bash
./bin/deploy-ansistrano
```

Construye la misma aplicación y ejecuta Ansible/Ansistrano dentro de un contenedor.
El playbook usa rsync y hooks para los archivos compartidos, migraciones,
promoción, salud y reconciliación. Sus dependencias se instalan fuera del
repositorio; el proyecto conserva únicamente su configuración. No usa GitHub.

### Docker desde tu máquina

Usa un registro OCI propio: GitLab Container Registry, GHCR, Docker Hub u otro
compatible. Configura previamente `docker login` en tu equipo y el acceso de
lectura al registro en el VPS. No guardes contraseñas en scripts ni en Git.

```bash
export CMS_IMAGE_REPOSITORY=registry.example.com/equipo/mi-web
export VPS_COMPOSE_PROJECT=mi-web
./bin/deploy-docker
```

Publica la imagen y despliega su digest verificado. En el primer arranque, el
contenedor materializa una sola vez el `.env` privado del volumen con la APP_KEY
suministrada. Las entregas siguientes lo conservan y rechazan una clave distinta;
no se regenera ni se rota al actualizar. El VPS conserva Compose,
configuración y volúmenes propios. No requiere GitHub Actions ni `.git` local.

### GitHub Actions

El workflow de despliegue se lanza **manualmente en `main`**, indicando un SHA
completo de esa rama con CI satisfactorio y un bundle local firmado de tipo
`deploy` en el borrador `delivery-SHA`. No se dispara al terminar CI ni construye
la aplicación en GitHub. `DEPLOY_ENABLED=true` y el entorno `production` siguen
siendo necesarios. Activa `bootstrap` únicamente para crear un `.env` ausente
desde el secreto. Sigue la preparación y firma de
[Versionado y entrega](DEPLOYMENT_AND_VERSIONING.md); no basta con el CI rápido.

### Sin despliegue incluido

`none` permite integrar tu propio procedimiento. Puedes ejecutar el gate de
calidad Docker local, pero no se incluyen ni activan despliegues remotos.

Ningún perfil derivado genera instaladores ZIP/TAR, archivos `docker save` ni
GitHub Releases. El perfil Docker publica imágenes OCI en el registro elegido.

## 6. Primer despliegue y siguientes entregas

### 6.1. Si el `.env` ya existe en el VPS

Comprueba que está en `VPS_DIR/shared/.env`, con clave válida, propietario y
permisos correctos según el apartado 4.5. En tu equipo:

```bash
unset VPS_ENV_FILE_PATH
# Ejecuta únicamente el comando del perfil de tu proyecto:
./bin/deploy-rsync
# Alternativas: ./bin/deploy-ansistrano o ./bin/deploy-docker.
```

No se descarga ese archivo a tu equipo ni se sustituye por el `.env` de desarrollo.
En GitHub Actions, selecciona el SHA aprobado y deja `bootstrap` desactivado.

### 6.2. Si quieres crear el `.env` mediante el bootstrap opcional

En perfiles locales, prepara el archivo privado de producción fuera del proyecto
e indica su ruta al ejecutar la primera entrega:

```bash
export VPS_ENV_FILE_PATH="$HOME/.config/mi-web/production.env"
./bin/deploy-rsync  # o deploy-ansistrano / deploy-docker, según el perfil
unset VPS_ENV_FILE_PATH
```

El bootstrap lo crea en el VPS solo si aún no existe. En GitHub Actions se utiliza `bootstrap` y `VPS_ENV_FILE`.

### 6.3. Completar la instalación inicial

Abre `https://TU_DOMINIO/install` y completa base de datos y administrador, sin
demo. Las siguientes entregas conservan claves, entorno, archivos y base;
ejecutan únicamente las migraciones pendientes y comprueban procesos y HTTP.
El bootstrap nunca sustituye una configuración existente ni rota su APP_KEY.

`cms:project:migrate --force` limita las migraciones al núcleo, Passport y módulos
instalados, incluidos los propios e inactivos. No instala módulos opcionales ni
activa funciones nuevas. En actualizaciones firmadas comprueba antes sus hashes.

### 6.4. Qué carpetas crea Ansistrano y qué queda compartido

En el primer despliegue, el rol de Ansistrano crea su estructura dentro de
`VPS_DIR`; no necesitas crear las carpetas de cada release a mano. El usuario SSH
sí debe poder escribir en ese directorio. El hook común prepara los datos
persistentes y los enlaces. La estructura resultante para los perfiles nativos
es:

```text
/srv/www/mi-web/
├── current -> releases/release-<fecha>-<id>
├── releases/
│   └── release-<fecha>-<id>/
│       ├── .env -> /srv/www/mi-web/shared/.env
│       ├── .php-binary
│       ├── storage -> /srv/www/mi-web/shared/storage
│       ├── public/
│       │   ├── uploads -> /srv/www/mi-web/shared/uploads
│       │   └── storage -> /srv/www/mi-web/shared/storage/app/public
│       └── bootstrap/cache/                 # cachés propias de esta release
└── shared/
    ├── .env                                 # configuración que preparaste
    ├── storage/
    │   ├── installed                        # aparece al completar /install
    │   ├── oauth-private.key
    │   ├── oauth-public.key
    │   ├── app/public/
    │   ├── framework/
    │   └── logs/
    └── uploads/
```

Durante una entrega pueden aparecer `shared/.deployment-lock/owner`, el marcador
`shared/deployment-recovery-required` y `.deployment-state` dentro de la nueva
release. Se conservan ante determinados fallos para poder recuperar. Ansistrano
utiliza además `.ansistrano-next` como enlace provisional; el hook final lo
promueve a `current` y retira ese nombre provisional.

El orden del playbook es:

1. `hooks/preflight.yml` comprueba que no haya recuperación pendiente ni un
   `current` que sea un directorio normal.
2. Ansistrano crea una release única y envía el directorio construido mediante
   `rsync_direct`, sin clonar el repositorio del proyecto.
3. `hooks/bootstrap.yml` crea el `.env` solo si lo solicitaste y falta. Si ya está
   en el servidor, este hook no necesita recibir nada de tu ordenador.
4. `hooks/prepare.yml` llama a `bin/deploy-vps-release.sh ... prepare`: valida PHP,
   adquiere el bloqueo, crea `shared/storage` y `shared/uploads` si faltan, enlaza
   los datos y ejecuta las migraciones y cachés cuando el sitio está instalado.
5. El rol crea `.ansistrano-next`; `hooks/finalize.yml` llama a la fase `finalize`
   del mismo script para promover, recargar FPM, comprobar HTTP y reabrir.

`ansistrano_shared_paths` y `ansistrano_shared_files` están vacíos porque esos
enlaces los prepara el script común. No añadas otra configuración paralela para
reemplazar `.env` o `storage`. Las entregas posteriores reutilizan `shared` y
crean una carpeta nueva de código. Actualmente no se eliminan releases antiguas
automáticamente (`ansistrano_keep_releases: 0`); establece una política revisada de
retención sin borrar datos compartidos ni una release necesaria para recuperar.

Rsync y GitHub Actions obtienen el mismo árbol persistente. Rsync crea la carpeta
de release con una llamada SSH; después, el script remoto crea o reutiliza
`shared` y realiza los enlaces. No es necesario crear `storage:link` manualmente:
el enlace público ya lo prepara el despliegue.

Docker utiliza otra estructura: `VPS_DIR/control/release-.../` contiene Compose y
los scripts de control; `VPS_DIR/shared/.env` configura Compose y
`shared/current-image` registra el digest satisfactorio. Los datos de aplicación,
uploads, MySQL y Redis viven en los volúmenes de ese proyecto Compose. No utiliza
un `current` nativo ni coloca todos esos datos en carpetas del host bajo `shared`.

### 6.5. Cómo rsync ejecuta Artisan después de enviar los archivos

`rsync` como programa solo copia. El comando del proyecto `./bin/deploy-rsync`
encadena construcción, validación, transferencia y una llamada SSH al script
que acaba de subir:

```text
Tu equipo: ./bin/deploy-rsync
  → Docker construye y valida el proyecto
  → rsync envía vendor, assets y fuentes a releases/release-...
  → SSH ejecuta bin/deploy-vps-release.sh sobre esa carpeta
  → el script remoto ejecuta Artisan con la CLI PHP del VPS
  → promoción, recarga FPM y comprobación HTTP
```

Por tanto, basta con lanzar `./bin/deploy-rsync`; no tienes que conectarte después
para ejecutar migraciones a mano. Copiar una carpeta con un `rsync` escrito por
tu cuenta no ejecuta esos pasos automáticamente. Ansistrano llega al mismo script
mediante sus hooks y GitHub Actions mediante su paso SSH final.

La receta canónica de servidor, migraciones, cachés y recuperación está en
[Despliegue](DEPLOYMENT_GUIDE.md). En instalaciones selladas se validan las claves
Passport originales antes de migrar y se conserva cualquier mantenimiento Laravel
preexistente. El desplegador solo retira el cierre que ha creado.

`queue:restart` requiere que Supervisor u otro gestor reinicie los workers; apunta
sus comandos a `VPS_DIR/current`. El despliegue nativo no instala Supervisor ni cron.
La primera entrega publica el asistente web; las actualizaciones no ejecutan el
instalador, seeders, `migrate:fresh`, `key:generate` ni actualizaciones de dependencias
en producción.

### 6.6. Qué ejecuta el perfil Docker

`bin/deploy-docker` publica la imagen validada y despliega su digest. El control
remoto migra una sola vez las instalaciones selladas, comprueba salud y registra
`shared/current-image`. Cada réplica mantiene `RUN_MIGRATIONS=false`.

El entrypoint solo genera claves en una instalación nueva sin ninguna pareja ni
configuración Passport externa; las parejas incompletas o inválidas detienen el
arranque. La conservación de datos, claves, mantenimiento y recuperación sigue
[la misma guía operativa](DEPLOYMENT_GUIDE.md#docker-de-producción-alternativa-explícita).
El perfil `none` no ejecuta estas secuencias.

### 6.7. Si una actualización falla

Un error posterior al inicio de migraciones deja mantenimiento y el marcador
`shared/deployment-recovery-required`. El siguiente intento se bloquea. El bloqueo exclusivo
`shared/.deployment-lock` conserva también la identidad de la entrega: evita
migraciones concurrentes desde dos equipos y persiste si se corta la conexión.

Revisa el resultado de migraciones, el propietario guardado en
`shared/.deployment-lock/owner` y la copia de seguridad. Decide primero qué código
y base son compatibles, recupera de forma supervisada y comprueba su estado.
Solo después retira `shared/deployment-recovery-required`, el archivo `owner` y
el directorio vacío `.deployment-lock` para permitir otra entrega. No borres esos
controles para forzar un reintento. No hay rollback automático de base de datos.

### 6.8. Lanzar Artisan manualmente sin confundirte de servidor o release

Los despliegues anteriores ya ejecutan su secuencia automática. Para diagnóstico
y operaciones puntuales sobre un alojamiento nativo, entra en el VPS y usa
`current`. El wrapper recupera la ruta PHP registrada en `.php-binary`:

```bash
# EN TU EQUIPO: conectar al servidor verificado.
ssh -i "$VPS_SSH_KEY_FILE" -p "$VPS_PORT" "$VPS_USER@$VPS_HOST"
```

```bash
# YA EN EL VPS NATIVO: diagnóstico de la release publicada.
cd /srv/www/mi-web/current
bash bin/vps-artisan.sh about
bash bin/vps-artisan.sh migrate:status
```

Si has editado deliberadamente `shared/.env` en una instalación sana, reconstruye
la configuración y renueva los procesos que la mantienen cargada:

```bash
# EN EL VPS NATIVO: ajusta el nombre del servicio FPM al del dominio.
bash bin/vps-artisan.sh config:cache
bash bin/vps-artisan.sh cms:cache:purge
bash bin/vps-artisan.sh queue:restart
sudo -n systemctl reload php8.3-fpm
```

Para Docker, utiliza el mismo nombre de proyecto Compose y el archivo de control
del contenedor publicado. Después de un despliegue satisfactorio, en Bash dentro
del VPS puedes obtener esa ruta de las etiquetas de Compose:

```bash
# EN EL VPS DOCKER: usa el nombre elegido al desplegar, no crees otro proyecto.
export VPS_DIR=/srv/www/mi-web
export VPS_COMPOSE_PROJECT=mi-web
APP_ID="$(docker ps -q --filter "label=com.docker.compose.project=$VPS_COMPOSE_PROJECT" --filter label=com.docker.compose.service=app)"
CONTROL_FILE="$(docker inspect --format '{{ index .Config.Labels "com.docker.compose.project.config_files" }}' "$APP_ID")"
export CMS_IMAGE="$(cat "$VPS_DIR/shared/current-image")"
export RUN_MIGRATIONS=false
compose=(docker compose --project-name "$VPS_COMPOSE_PROJECT" \
  --env-file "$VPS_DIR/shared/.env" -f "$CONTROL_FILE")

"${compose[@]}" exec -T --user dbcms app php artisan about
"${compose[@]}" exec -T --user dbcms app php artisan migrate:status
"${compose[@]}" exec -T --user dbcms app php artisan cms:cache:purge
"${compose[@]}" exec -T --user dbcms app php artisan queue:restart
```

Estos comandos ejecutan Artisan como `dbcms`, el usuario de la aplicación.
El contenedor debe estar arrancado y sano. El digest identifica la imagen, pero
no el directorio `control`; evita escoger un directorio solo porque sea el más
reciente, pues podría pertenecer a un despliegue fallido. Si cambias el `.env` del
host Docker, un `config:cache` dentro del contenedor existente no actualiza las
variables que Compose ya le inyectó: vuelve a desplegar con el procedimiento
normal para recrear la aplicación con la configuración revisada y la misma APP_KEY.

Estos comandos manuales no sustituyen la recuperación de una migración fallida.
Si hay marcadores de recuperación o bloqueo, revisa su estado antes de reabrir;
no uses `up` para saltarte ese control.

## 7. Actualizar dbCMS en una web derivada

### Con el perfil GitHub Actions

1. Configura `PROJECT_BOT_TOKEN` si creaste el proyecto con Composer.
2. Abre **Actions → Actualizar dbCMS** e introduce una release estable posterior,
   admitida por la política firmada del producto.
3. El workflow verifica firma, versión, SHA del tag y hashes de las fuentes obtenidas
   por Git. No descarga los ZIP del actualizador.
4. Compara base oficial, proyecto y destino. Mantiene excluidos marca, temas,
   módulos, paquetes y despliegues descartados; conserva personalizaciones y
   registra conflictos. Antes de actualizar, revisa la propiedad de tus rutas
   según el apartado 8: un tema oficial seleccionado que desaparece del destino
   bloquea la propuesta antes de escribir archivos.
5. Sin conflictos, intenta resolver los locks en Docker. La PR se abre siempre
   en borrador; no ejecuta el quality gate completo. Con conflictos conserva los
   archivos locales afectados. La validación completa es obligatoria en local.
6. Revisa código, dependencias y migraciones. Para cada conflicto decide incorporar,
   fusionar o conservar el código local. Retira su entrada de `conflicts` en
   `resources/updates/project-update.json` **después** de resolverlo y documentar la
   decisión en la PR. No edites los manifiestos firmados para hacer pasar las pruebas.
7. Valida localmente el commit revisado, prepara la copia de seguridad y fusiona
   la PR. Para el SHA final de `main`, prepara y sube su bundle firmado; ejecuta
   manualmente el workflow de despliegue cuando pase el CI básico de ese SHA.
   La reconciliación registra la base tras las comprobaciones del destino.

### Con GitLab, otro proveedor o sin repositorio

Trabaja en una rama si usas Git. Sin Git, prepara una copia independiente de los
fuentes y conserva la anterior para revisar las diferencias. No ejecutes la
preparación en la carpeta de producción ni copies su `.env` o sus datos:

```bash
# En una rama local, con cualquier remoto Git:
./bin/project-update VERSION_PUBLICADA_POSTERIOR

# En una copia de desarrollo sin .git:
./bin/project-update VERSION_PUBLICADA_POSTERIOR --working-copy
```

Sustituye `VERSION_PUBLICADA_POSTERIOR` por una release estable publicada mayor
que `core.version` del perfil y cuya política admita esa base. No se puede bajar
de versión ni asumir que existe una release por cambiar un número local.

El envoltorio usa Docker para verificar la release firmada y obtener las fuentes
oficiales. El Git utilizado dentro del contenedor para leer dbCMS no exige que tu
proyecto tenga repositorio. El comando bloquea la ejecución con `APP_ENV=production`.
Mantén el entorno de la copia de desarrollo con `APP_ENV=local`.
El comando devuelve `0` para una propuesta sin conflictos, `2` si necesita
resolución y `1` ante un error. Una propuesta posterior se bloquea hasta resolver
y reconocer los conflictos anteriores; no sustituye su informe.

Revisa `resources/updates/project-update.json`, resuelve los conflictos y revisa
los cambios de Composer/npm. Resuelve sus locks dentro del contenedor de herramientas (por ejemplo
`composer update --with-all-dependencies --no-scripts` y
`npm install --package-lock-only --ignore-scripts`), revisa el lock resultante,
ejecuta la revisión de procedencia y el gate local, y despliega después de
validar. El comando no hace push, no abre una PR ni despliega automáticamente.

Para comprobar la copia con la misma batería usada por los despliegues locales:

```bash
docker compose -p mi-web-check -f docker/ci/compose.yml run --build --rm app bash bin/ci-check
docker compose -p mi-web-check -f docker/ci/compose.yml down --volumes --remove-orphans
```

Los comandos de despliegue local ejecutan ese gate sobre su contexto temporal
antes de construir y transferir/publicar. No alteran tu `.env`, datos ni locks.

El informe conserva SHA de base/destino, cambios, personalizaciones y exclusiones.
La versión fuente propuesta cambia en la copia de trabajo; la identidad privada de producción se
actualiza al reconciliar el despliegue satisfactorio.

Si una actualización falla antes de abrir la PR, revisa el log y el estado de la
rama; no se publica nada en el VPS. Si ya existe la rama `codex/dbcms-VERSION`,
continúa su revisión o retírala expresamente antes de repetir la misma propuesta.

`composer update` por sí solo actualiza dependencias; no actualiza una aplicación
copiada con `create-project`. Tampoco se mezclan historiales independientes con un
`git pull upstream main`: la comparación usa los commits oficiales registrados.

## 8. Temas propios, cachés y comprobaciones

Vite construye un CSS independiente por tema instalado y por marca. Cada página
solicita su tema activo, la marca necesaria y los recursos comunes. Cambiar a otro
tema instalado desde el panel no requiere Node ni recompilar en el VPS.

La selección del panel se guarda en `cms.active_theme`; después se consideran
`CMS_THEME`, el valor inicial del perfil y los temas disponibles. Ningún valor
permite activar un tema que el proyecto no conserva. La activación invalida la
caché pública y funciona con configuración cacheada.

### Aplicación hija y tema propio son cosas distintas

Crear una hija no crea un tema hijo: no hay `parent`, `extends` de manifiesto ni
herencia entre temas. Un tema público propio contiene sus fuentes, layout,
parciales y vistas. Puede usar componentes compartidos y los fallbacks de núcleo
y módulos, pero no depender de vistas de otro tema que puedas retirar.

Con Sail ya arrancado:

```bash
./vendor/bin/sail artisan make:front-theme mi-tema
```

El generador crea fuentes; no modifica el perfil ni activa el tema. Añade
`mi-tema` a `themes` en `cms-project.json`, implementa su presentación y compila
los recursos con `./vendor/bin/sail npm run build`. Comprueba sus vistas y actívalo
desde el panel. La elección persistida `cms.active_theme` tiene prioridad sobre
`CMS_THEME`; cambiar solo `.env` no sustituye una elección instalada válida.

Antes de actualizar un tema oficial que has personalizado, mueve deliberadamente
esa presentación a un identificador y rutas propios. Registra el tema en el perfil
y pruébalo. Si dejas de distribuir el oficial, cambia también `default_theme` si
apunta a él, activa el nuevo tema y revisa la retirada del identificador y carpeta
anteriores como un cambio propio. No vuelvas a ejecutar el inicializador para
simular esta migración. El actualizador **ya** rechaza antes de escribir una release
que retire un tema oficial aún seleccionado; este recorrido cambia la selección
del proyecto, no modifica dbCMS ni otros repositorios.

Aplica el mismo criterio al código de negocio: módulos y paquetes propios usan
identificadores, namespaces y rutas propios, con sus directorios declarados en
`source_selection` cuando ese campo existe. Una personalización conservada dentro
de una ruta oficial sigue siendo una personalización de upstream y puede requerir
fusión. Una colisión futura en una ruta propia se revisa; no se promete que un
archivo personalizado pueda reemplazarse sin conflicto.

La marca administrativa del perfil sigue limitada a `dbcms` o `termocms`. Su
manifest permite ajustar identidad y sus componentes/paleta pueden personalizarse
con revisión de cambios. `make:admin-theme` no añade una tercera marca soportada al
contrato de creación de hijas. Consulta los contratos detallados de
[frontoffice](17_FRONTOFFICE_THEME_GUIDE.md) y [backoffice](16_BACKOFFICE_THEME_GUIDE.md).

El repositorio dbCMS ejecuta su suite completa, incluida la distribución autónoma.
Los derivados ejecutan la suite de perfil, servicios, seguridad, instalación y
autorización, junto con compilación, auditorías, PHPStan y Pint. Las pruebas de
paridad del catálogo completo pertenecen al producto original; los derivados
comprueban los recursos que realmente conservan.

## 9. Perfiles existentes y cambios de composición

El inicializador repite únicamente un perfil coincidente. No convierte un perfil
sin `deployment` ni decide el método mirando archivos existentes: esa adopción no
está implementada. Un perfil incompleto necesita una migración explícita y
revisada de manifiesto, procedencia y controles de despliegue antes de utilizar el
recorrido actual; no se resuelve pasando un `--deploy` nuevo al inicializador.

Los perfiles anteriores sin `source_selection` conservan el catálogo completo.
No se les añade una selección deducida de las carpetas que haya en disco. En los
perfiles que ya la incluyen, la selección resuelta se conserva en actualizaciones
y reconciliación. Para cambiar composición, marca, temas o método de despliegue,
prepara un cambio deliberado del proyecto y verifica sus dependencias, manifiesto,
secretos y destino en un entorno desechable. No se modifica silenciosamente otra
web ni la distribución fuente.
