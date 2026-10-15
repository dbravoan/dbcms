# Crear, desplegar y actualizar proyectos derivados

Este recorrido se incorpora en dbCMS 1.3.0. Los comandos de creación deben usar una
release publicada que ya incluya estos archivos. Cambiar `VERSION` localmente no
publica una versión en GitHub, Packagist ni GHCR.

Cada web es un proyecto independiente. dbCMS conserva su catálogo completo; cada
derivado conserva una marca y las plantillas públicas que selecciones. Elige cómo
desplegar: desde GitHub Actions o desde tu máquina,
con independencia de dónde guardes el código y de si usas Git.

## 1. Elegir marca, plantillas y despliegue

`--brand` es obligatorio: solamente `dbcms` o `termocms`. No acepta listas ni `all`.
`--theme` es obligatorio y admite un identificador, una lista separada por comas o
`all`. `--deploy` también es obligatorio:

| Valor | Se ejecuta desde | Qué conserva el proyecto |
|---|---|---|
| `github-actions` | GitHub Actions | CI, actualización por PR y workflow de despliegue por SSH/rsync. |
| `ansistrano` | Tu máquina, usando Docker para Ansible | Playbook, hooks y controles de releases; sin workflows de GitHub. |
| `rsync` | Tu máquina | Envío directo por rsync sobre SSH y controles de releases; sin workflows de GitHub. |
| `docker` | Tu máquina | Construcción y publicación en tu registro OCI y despliegue remoto por digest. |
| `none` | Sin automatización incluida | Herramientas de desarrollo y comprobación, sin controles de despliegue. |

GitHub Actions es el ejecutor. SSH es el transporte seguro. Rsync transfiere el
directorio construido; Ansistrano organiza las releases. Son elecciones distintas:
puedes guardar un proyecto en GitLab y desplegar con Ansistrano, o desplegar una
carpeta con rsync aunque no tenga `.git`.

Por ejemplo:

```bash
./bin/project-init mi-web --brand=dbcms --theme=db-minimal --deploy=rsync
./bin/project-init mi-web --brand=termocms --theme=db-minimal,db-editorial --deploy=ansistrano
./bin/project-init mi-web --brand=dbcms --theme=all --deploy=github-actions
```

Ejecuta **una** de estas variantes. La lista conserva su orden y el primer tema es
el inicial. `all` conserva el catálogo de esa versión y comienza con `db-cyber`.
No implica descargar automáticamente futuras plantillas. El instalador permite
elegir entre los temas conservados; con uno solo, no muestra un selector múltiple.

La preparación valida los argumentos y el tag oficial antes de retirar archivos.
Repetir el mismo perfil es seguro; cambiarlo después requiere una modificación
deliberada del proyecto, no volver a ejecutar el inicializador con otros argumentos.
No copia datos de la instancia original ni modifica su `.env`.

La preparación retira los controles oficiales de los otros despliegues, el
workflow de preparación y la distribución de instaladores. Los perfiles locales
también retiran la configuración oficial de GitHub. Los archivos propios se
conservan. Docker de desarrollo y CI sigue disponible como herramienta compartida;
elegir rsync o Ansistrano no exige Docker en el VPS.

`cms-project.json` registra marca, temas, `deployment` y la versión/SHA del núcleo de origen. No
guardes secretos ahí ni confundas esa versión con la versión comercial de tu web.
La revisión se obtiene del tag oficial de dbCMS, no del primer commit independiente
que GitHub crea para el derivado. Si el tag no existe todavía, la preparación falla
sin inventar una procedencia.

## 2. Crear desde GitHub sin preparar nada en tu ordenador

1. En `dbravoan/dbcms`, pulsa **Use this template → Create a new repository**.
2. Elige el nombre, propietario y visibilidad. Copia solo la rama principal.
3. Configura el secreto `PROJECT_BOT_TOKEN` en el repositorio nuevo. Usa un token
   limitado a ese repositorio con permisos de escritura en **Contents**, **Pull
   requests** y **Workflows**. Es necesario porque la preparación elimina el
   workflow de distribución del producto y las actualizaciones pueden modificar
   workflows. No se reutilizan los secretos del repositorio dbCMS.
4. Abre **Actions → Preparar proyecto → Run workflow**.
5. Introduce el identificador, la marca, los temas y el despliegue, por ejemplo
   `mi-web`, `termocms`, `db-minimal,db-editorial`, `github-actions`.
6. Revisa la PR generada. Incluye la retirada de recursos excluidos, el manifiesto y
   la configuración pública. El mismo workflow valida el árbol preparado en Docker.
7. Fusiona la PR. Con `github-actions`, el push a `main` ejecuta CI y el despliegue
   permanece desactivado hasta configurar `DEPLOY_ENABLED=true`. Con un perfil
   local, la PR retira los workflows: clona la web y despliega desde tu equipo.

El workflow que prepara la PR puede eliminarse a sí mismo: su ejecución actual
termina normalmente y deja de estar disponible después de fusionarla.

El token automático de Actions no tiene permiso para editar workflows; concederle
`contents: write` no sustituye ese permiso. Los workflows no guardan el token en el
checkout que se monta dentro de los contenedores de validación.

## 3. Crear con Composer, sin PHP ni Composer en el equipo

Requisitos locales: Docker con Compose. Git es opcional para los perfiles locales. Desde la carpeta donde quieras crear
la web, sustituye la versión por una release publicada con estas funciones:

```bash
docker run --rm \
  --user "$(id -u):$(id -g)" \
  --env COMPOSER_HOME=/tmp/composer \
  --volume "$PWD:/workspace" --workdir /workspace \
  laravelsail/php83-composer:latest \
  composer create-project --no-install --no-scripts --remove-vcs \
  dbravoan/dbcms mi-web 1.3.0

cd mi-web
./bin/project-init mi-web --brand=dbcms --theme=db-minimal --deploy=rsync
```

La primera operación descarga las fuentes, sin intentar instalar las dependencias
con extensiones PHP incompletas. El envoltorio construye el contenedor compatible,
instala dependencias y ejecuta el inicializador. No requiere `vendor/` previamente.

Después puedes guardar el proyecto en GitHub, GitLab o cualquier servidor Git.
Esto es opcional para los perfiles locales. Para Git, crea un repositorio vacío y
sustituye la URL del ejemplo por la de tu proveedor:

```bash
git init -b main
git add .
git commit -m "Initialize mi-web"
git remote add origin git@github.com:TU_USUARIO/mi-web.git
git push -u origin main
```

Para desarrollar localmente, copia `.env.example` a `.env`, revisa nombre de Compose,
puertos, URL y credenciales de la nueva web y levanta sus contenedores. Genera las
claves únicamente para esa instalación nueva y compila los assets:

```bash
cp .env.example .env
docker compose up -d --build
./bin/sail-run php artisan key:generate
./bin/sail-run php artisan passport:keys
./bin/sail-run npm ci
./bin/sail-run npm run build
```

Completa `/install` sin demo. No copies el `.env`, los volúmenes ni las claves de
otra web. Si conviven varios proyectos en tu equipo, usa nombres de Compose y
puertos distintos antes de levantar sus servicios.

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
por proyecto. El usuario de despliegue debe poder escribir sus datos y recargar
el servicio FPM configurado mediante un permiso sudo limitado. El pool PHP-FPM
del dominio debe ejecutar con el **mismo usuario que `VPS_USER`**: `.env` y la
clave privada OAuth tienen permisos `0600`. En Plesk usa el propietario de la
suscripción como usuario SSH. Con Nginx/Apache configura un pool dedicado, por
ejemplo `user=deploy` y `group=deploy` si `VPS_USER=deploy`; no uses el pool genérico
`www-data` con otro propietario ni amplíes los permisos de los secretos.

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

En el perfil `github-actions`, crea **Settings → Environments → production** y
configura allí estos secretos:

| Secreto | Contenido |
|---|---|
| `VPS_HOST`, `VPS_USER`, `VPS_PORT`, `VPS_DIR` | Destino del proyecto; puerto predeterminado 22. |
| `VPS_SSH_KEY` | Contenido de la clave privada del usuario de despliegue. |
| `VPS_SSH_FINGERPRINT` | Huella ED25519 SHA256 verificada. |
| `VPS_HEALTH_URL` | URL HTTPS pública que se comprobará. |
| `VPS_ENV_FILE` | `.env` completo de producción, solo para bootstrap explícito. |

Configura `DEPLOY_ENABLED=true` en las variables del repositorio cuando esté
preparado. `VPS_PHP_BINARY` y `VPS_FPM_SERVICE` son variables de configuración del
perfil nativo. Los perfiles locales no utilizan secretos ni variables de GitHub.

Genera una APP_KEY nueva y estable para producción dentro del contenedor local:

```bash
./bin/sail-run php artisan key:generate --show
```

Guárdala en un archivo privado de producción fuera del proyecto. En GitHub
Actions, guarda su contenido como `VPS_ENV_FILE`. El entorno debe
incluir `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, identidad,
marca/tema seleccionados, `CMS_SEED_DEMO=false` y los datos de sus servicios.
En Docker incluye también `DB_PASSWORD` y `DB_ROOT_PASSWORD`; Compose administra
MySQL/Redis. No reutilices las credenciales de ejemplo.

El bootstrap escribe `shared/.env` una sola vez; una repetición no lo sustituye.
Cambiar el secreto posteriormente no rota automáticamente el entorno instalado.

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
./bin/deploy-docker
```

Publica la imagen y despliega su digest verificado. En el primer arranque, el
contenedor materializa una sola vez el `.env` privado del volumen con la APP_KEY
suministrada. Las entregas siguientes lo conservan y rechazan una clave distinta;
no se regenera ni se rota al actualizar. El VPS conserva Compose,
configuración y volúmenes propios. No requiere GitHub Actions ni `.git` local.

### GitHub Actions

Tras superar CI en `main`, el workflow de despliegue de GitHub Actions publica por SSH/rsync.
Para la primera entrega, ejecútalo manualmente indicando el SHA completo de un
CI satisfactorio y activando `bootstrap`. Después, cada CI satisfactorio de
`main` despliega si `DEPLOY_ENABLED=true`.

### Sin despliegue incluido

`none` permite integrar tu propio procedimiento. Puedes ejecutar el gate de
calidad Docker local, pero no se incluyen ni activan despliegues remotos.

Ningún perfil derivado genera instaladores ZIP/TAR, archivos `docker save` ni
GitHub Releases. El perfil Docker publica imágenes OCI en el registro elegido.

## 6. Primer despliegue y siguientes entregas

En perfiles locales, prepara primero el archivo privado de producción fuera del
proyecto e indica su ruta al ejecutar la primera entrega:

```bash
export VPS_ENV_FILE_PATH="$HOME/.config/mi-web/production.env"
./bin/deploy-rsync  # o deploy-ansistrano / deploy-docker, según el perfil
unset VPS_ENV_FILE_PATH
```

El bootstrap lo crea en el VPS solo si aún no existe. En GitHub Actions se utiliza `bootstrap` y `VPS_ENV_FILE`.

Abre `https://TU_DOMINIO/install` y completa base de datos y administrador, sin
demo. Las siguientes entregas conservan claves, entorno, archivos y base;
ejecutan únicamente las migraciones pendientes y comprueban procesos y HTTP.
El bootstrap nunca sustituye una configuración existente ni rota su APP_KEY.

`cms:project:migrate --force` limita las migraciones al núcleo, Passport y módulos
instalados, incluidos los propios e inactivos. No instala módulos opcionales ni
activa funciones nuevas. En actualizaciones firmadas comprueba antes sus hashes.

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

## 7. Actualizar dbCMS en una web derivada

### Con el perfil GitHub Actions

1. Configura `PROJECT_BOT_TOKEN` si creaste el proyecto con Composer.
2. Abre **Actions → Actualizar dbCMS** e introduce una release estable posterior,
   admitida por la política firmada del producto.
3. El workflow verifica firma, versión, SHA del tag y hashes de las fuentes obtenidas
   por Git. No descarga los ZIP del actualizador.
4. Compara base oficial, proyecto y destino. Mantiene excluidos los recursos de
   marcas, temas y despliegues descartados; conserva personalizaciones y registra conflictos.
5. Sin conflictos, resuelve los locks en Docker y ejecuta el quality gate. Abre una
   PR lista para revisar si pasa; si hay conflictos o fallan las comprobaciones,
   abre una PR en borrador y conserva los archivos locales afectados.
6. Revisa código, dependencias y migraciones. Para cada conflicto decide incorporar,
   fusionar o conservar el código local. Retira su entrada de `conflicts` en
   `resources/updates/project-update.json` **después** de resolverlo y documentar la
   decisión en la PR. No edites los manifiestos firmados para hacer pasar las pruebas.
7. Ejecuta el CI, prepara la copia de seguridad y fusiona la PR. Main CI y el perfil
   elegido publican el commit; la reconciliación registra la base desplegada y las
   diferencias del proyecto después de las comprobaciones.

### Con GitLab, otro proveedor o sin repositorio

Trabaja en una rama si usas Git. Sin Git, prepara una copia independiente de los
fuentes y conserva la anterior para revisar las diferencias. No ejecutes la
preparación en la carpeta de producción ni copies su `.env` o sus datos:

```bash
# En una rama local, con cualquier remoto Git:
./bin/project-update 1.4.0

# En una copia de desarrollo sin .git:
./bin/project-update 1.4.0 --working-copy
```

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

## 8. Temas, cachés y comprobaciones

Vite construye un CSS independiente por tema instalado y por marca. Cada página
solicita su tema activo, la marca necesaria y los recursos comunes. Cambiar a otro
tema instalado desde el panel no requiere Node ni recompilar en el VPS.

La selección del panel se guarda en `cms.active_theme`; después se consideran
`CMS_THEME`, el valor inicial del perfil y los temas disponibles. Ningún valor
permite activar un tema que el proyecto no conserva. La activación invalida la
caché pública y funciona con configuración cacheada.

Para incorporar un tema propio después de la preparación, genera sus fuentes con
`./bin/sail-run php artisan make:front-theme mi-tema`, añade `mi-tema` a `themes` en
`cms-project.json` y despliega ese cambio con el método elegido para construir su CSS.
Después podrás activarlo desde el panel. El actualizador conserva los temas propios
y detecta una colisión si una futura release utiliza el mismo identificador/ruta.

El repositorio dbCMS ejecuta su suite completa, incluida la distribución autónoma.
Los derivados ejecutan la suite de perfil, servicios, seguridad, instalación y
autorización, junto con compilación, auditorías, PHPStan y Pint. Las pruebas de
paridad del catálogo completo pertenecen al producto original; los derivados
comprueban los recursos que realmente conservan.

## 9. Perfiles anteriores y cambios de método

Los perfiles anteriores sin `deployment` siguen siendo legibles; no se deduce el
método a partir de archivos porque antes se copiaban todas las alternativas.
Para adoptar uno, ejecuta el inicializador con los mismos nombre, marca y lista
de temas, añadiendo `--deploy`. La adopción verifica la base firmada antes de
retirar controles oficiales: si están personalizados, se detiene y muestra los
archivos que debes revisar. Guarda y revisa ese cambio como cualquier migración.

Una vez elegido el método, repetirlo es idempotente. El inicializador rechaza
cambiarlo silenciosamente. Para cambiar después, prepara una migración explícita
del repositorio, sus secretos, dependencias y configuración del VPS, y pruébala
en staging antes de sustituir el procedimiento de producción.
