# dbCMS como plantilla de GitHub para proyectos independientes

Fecha: 13 de octubre de 2026. Repositorio: `git@github.com:dbravoan/dbcms.git`.
La comprobación autenticada inicial confirmó un repositorio privado con
**Template repository** y Actions activados. La plantilla ya tiene historial de
publicación; antes de copiarla se debe comprobar que cada etiqueta estable conserva
su commit original. No hay secretos ni variables de repositorio y el despliegue VPS
sigue deshabilitado.
Conservar la visibilidad privada. El [informe de publicación](archive/RELEASE_VALIDATION_2026-10-13.md)
separa estos datos de los resultados posteriores del push y de Actions.
En esta entrega sólo se publica dbCMS: no se crean el blog personal ni RegulaSE.

## 1. Preparar exactamente el código que se publicará

1. Las correcciones anteriores ya están incorporadas al commit inicial mediante
   `--amend`. Revisar cualquier ajuste posterior y confirmar sus archivos antes de
   construir el paquete definitivo: no basta con que la copia local funcione.
2. Comprobar `.gitignore`, `.dockerignore` y el contenido versionado. No incluir
   `.env` privados, credenciales de proveedores, configuraciones locales MCP,
   claves OAuth, dumps SQL/SQLite, sesiones, logs, uploads, contenidos QA o releases.
   `.env.example`, `.env.production.example`, fuentes y manifiestos declarativos
   del CMS sí son parte de la plantilla. Los fixtures ficticios de tests siguen siendo tests.
3. Revisar también el historial y los tags antes de hacer público un repositorio.
   Ignorar ahora un secreto no lo elimina de un commit anterior. Si se encuentra
   una credencial real, revocarla y resolver la exposición antes de publicar.
4. Mantener `CMS_QUICK_LOGIN=false`, ejecución nativa inicialmente desactivada y
   proveedores sin claves en las configuraciones de ejemplo. RegulaSE no hereda
   conexiones ni aprobaciones del CMS de desarrollo.
5. Mantener las versiones canónicas alineadas, pasar el gate local y publicar sólo
   `main` sin forzar. Esperar el CI del mismo SHA. Para futuras modificaciones, usar
   ramas y PR con el mismo quality gate antes de fusionar.
6. Crear una etiqueta anotada nueva sólo después de que el commit definitivo de
   `main` haya superado CI. Las etiquetas estables publicadas son inmutables; cualquier
   corrección requiere una versión PATCH nueva. Publicar sólo la etiqueta concreta;
   no enviar respaldos ni todas las referencias.

`bin/build-release-zip.sh` usa **`git archive HEAD`**. Un ZIP creado antes de
commitear las correcciones contiene código anterior, aunque una imagen construida
desde el working tree ya incorpore esas correcciones. Comprobar que el archivo
publicado contiene el commit final validado y que los checksums coinciden.
El procedimiento completo está en [versionado y despliegue](DEPLOYMENT_AND_VERSIONING.md).

Mantener protección de rama, revisión obligatoria y checks requeridos según las
opciones disponibles en la cuenta. No conceder acceso administrativo ni introducir
secrets de despliegue por el mero hecho de convertir el repositorio en plantilla.
Conservar licencias y atribuciones de dependencias, fuentes y recursos de marca;
seleccionar `dbcms` como marca administrativa genérica para RegulaSE.

## 2. Convertir el repositorio actual en plantilla

La opción **Settings → General → Template repository** ya está activada en
`dbravoan/dbcms`. Tras publicar, comprobar que `main` es la rama predeterminada y
que su contenido es el commit final verificado. La plantilla seguirá privada.
GitHub indica que un template no puede incluir archivos almacenados con Git LFS.
[Procedimiento oficial](https://docs.github.com/en/repositories/creating-and-managing-repositories/creating-a-template-repository).

## 3. Crear RegulaSE

Este recorrido se realizará después, en una tarea independiente. Para el blog
personal se aplica el mismo procedimiento: elegir su propio nombre de repositorio,
identidad, tema, puertos, base, almacenamiento y claves; no reutilizar los de dbCMS
ni los de RegulaSE. Generar sólo la rama predeterminada y registrar el SHA de origen.
La plantilla crea una copia inicial; el actualizador permite descubrir y preparar futuras mejoras del núcleo sin sincronizar historiales Git.

En `dbravoan/dbcms`, elegir **Use this template → Create a new repository**:

- Propietario: la cuenta u organización elegida; `dbravoan` si se mantiene la actual.
- Nombre: `regulase`.
- Visibilidad inicial: privada durante preparación, salvo decisión expresa distinta.
- Copiar únicamente la rama por defecto, sin incluir todas las ramas de trabajo.
- Descripción propuesta: «Normativa, calidad y consumo responsable en España y Europa».

Alternativa mediante GitHub CLI, cuando esté instalado y autenticado, para una
ejecución futura expresamente autorizada:

```bash
gh repo create dbravoan/regulase --template dbravoan/dbcms --private --clone
cd regulase
```

Estos comandos **no se han ejecutado**. Comprobar que `origin` apunta a
`dbravoan/regulase` antes de cualquier push. El repositorio generado tiene historial
independiente; las futuras releases se descubren desde el actualizador y requieren revisión y aprobación.
[GitHub: crear desde template](https://docs.github.com/en/repositories/creating-and-managing-repositories/creating-a-repository-from-a-template),
[CLI: opciones reales](https://cli.github.com/manual/gh_repo_create).

## 4. Arrancar una copia local independiente sin PHP en el host

Trabajar en el checkout de RegulaSE, nunca en la carpeta o volúmenes del CMS actual.
Requisitos: Git y Docker con Compose. Conservar `composer.lock` y `package-lock.json`.

```bash
cp .env.example .env
docker build --target ci -f docker/production/Dockerfile -t regulase-bootstrap .
docker run --rm --user "$(id -u):$(id -g)" \
  --env COMPOSER_HOME=/tmp/composer \
  --volume "$PWD:/var/www/html" --workdir /var/www/html regulase-bootstrap \
  bash -c 'mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs && composer install --prefer-dist --no-interaction'
```

La imagen bootstrap tiene las extensiones PHP y Composer del proyecto; instala
`vendor` antes de invocar Sail. No se usa `--ignore-platform-reqs`. El build inicial
es un paso de instalación; no supone que la instancia ya esté configurada. Después
usa únicamente Sail oficial para las herramientas locales; requiere `up -d`
explícito y se detiene con `./vendor/bin/sail down`, sin arranque efímero automático.

Editar el `.env` **nuevo** antes de levantar servicios. Valores locales ilustrativos
para evitar colisiones con dbCMS, sujetos a que esos puertos estén disponibles:

```dotenv
COMPOSE_PROJECT_NAME=regulase
APP_NAME="RegulaSE"
APP_URL=http://localhost:8081
APP_PORT=8081
VITE_PORT=5174
FORWARD_DB_PORT=3307
FORWARD_REDIS_PORT=6380
FORWARD_MAILPIT_PORT=1026
FORWARD_MAILPIT_DASHBOARD_PORT=8026
FORWARD_ADMINER_PORT=8089
DB_DATABASE=regulase
CMS_BRAND=dbcms
CMS_THEME=db-cyber
CMS_QUICK_LOGIN=false
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
GEO_SITE_NAME="RegulaSE"
GEO_SITE_DESCRIPTION="Normativa, calidad y consumo responsable en España y Europa"
SEO_DEFAULT_TITLE="RegulaSE: normativa y calidad explicadas"
SEO_DEFAULT_DESCRIPTION="Guías con fuentes oficiales sobre seguridad, calidad y obligaciones de los productos en España y la Unión Europea."
```

Definir `WWWUSER`/`WWWGROUP` con los IDs locales si hacen falta para permisos.
Añadir credenciales propias de la base y un prefijo de caché exclusivo. Los valores
locales no son la configuración de producción ni autorizan reutilizar secretos.

```bash
./vendor/bin/sail up -d --build
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan passport:keys
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

Abrir `http://localhost:8081/install` y completar el instalador **sin demo**, con
administrador y contraseña propios. Alternativamente, usar el instalador CLI
interactivo `./vendor/bin/sail artisan cms:install --without-demo` y revisar sus
opciones con `--help`; introducir la contraseña en su pregunta oculta, no como
argumento de terminal. No ejecutar ambos caminos sobre una instalación terminada.
No usar `migrate:fresh`, importar la base actual ni copiar `storage/installed`.

El instalador limpio conserva foundation y crea el administrador, sin los artículos
ni cuentas de showcase. Foundation incluye una matriz inicial de idiomas y mercados:
en el backoffice, activar España como mercado por defecto, revisar su idioma español
y desactivar los demás hasta que exista contenido y revisión para ellos. La futura
edición europea es una decisión editorial, no la simple activación de ocho mercados.
Las primeras URLs explícitas tendrán contexto `/es/es/…` según los slugs configurados.

## 5. Adaptar el proyecto, manteniendo el CMS reutilizable

Registrar la versión/SHA de la plantilla usada. Cambiar identidad pública, organización,
logo, imagen social y remitente en la configuración real; el backoffice `dbcms` puede
conservarse. Las credenciales y datos se generan en la instancia, no en Git.

Crear el tema público `regulase` con el comando real documentado en
[la guía de temas](17_FRONTOFFICE_THEME_GUIDE.md), y seleccionarlo cuando cumpla los
contratos de páginas, blog, galería, tienda y hooks de privacidad. Empezar con el tema
existente para comprobar el arranque no equivale a entregar el diseño de RegulaSE.
Guardar su lógica editorial en un módulo propietario cuando se necesiten fichas
normativas tipadas; no introducir condiciones `if RegulaSE` en el núcleo.

Módulos iniciales propuestos: Pages, Blog, Gallery, Forms, Search y ContentAudit.
IA y MCP son opcionales, con permisos propios y conexiones verificadas. Shop,
Ads, Analytics, Meta, Charts y BeforeAfter sólo se activan si existe un uso real.
Conservar datos al desinstalar; no borrar tablas como forma de limpiar una instancia.

RegulaSE debe inicializar marca, temas y método de despliegue siguiendo la
[guía de derivados](DERIVED_PROJECTS_GUIDE.md). No hereda la distribución de
instaladores del producto. El perfil `github-actions` conserva CI y actualización
por PR; los perfiles locales retiran los workflows oficiales. Si elige Docker,
configure el registro propio con `CMS_IMAGE_REPOSITORY`, no el registro de dbCMS.
Revise VERSION, paquetes, README, badges y metadatos de identidad del proyecto.

## 6. Publicar en el alojamiento elegido

El alojamiento nativo VPS/Plesk usa PHP CLI del servidor y releases persistentes, descritos en
[DEPLOYMENT_GUIDE](DEPLOYMENT_GUIDE.md) y [DEPLOYMENT_AND_VERSIONING](DEPLOYMENT_AND_VERSIONING.md).
La alternativa de runtime Docker tiene su propio compose; no mezclarlos sin diseñar
volúmenes, proxy y copias de seguridad. Para RegulaSE, crear directorio, base y
credenciales exclusivos. El dominio definitivo todavía está por elegir; no asumir
que `regulase.es` u otro esté disponible.

Configurar HTTPS, `APP_ENV=production`, debug desactivado, URL canónica definitiva,
cookies seguras, correo real cuando se necesite, workers y scheduler. Generar claves
de aplicación/OAuth exclusivas y conservarlas entre releases. Mantener secrets en
el entorno de producción; en el perfil GitHub Actions, activar `DEPLOY_ENABLED` únicamente después de revisar
host, directorio, huella SSH, health URL y recuperación. No copiar esos secrets desde dbCMS.

Las rutas `/llms.txt` y `/llms-full.txt` deben llegar a Laravel aunque haya exports
antiguos en public. Retirarlos y aplicar las locations indicadas en la guía de
despliegue al vhost Plesk/Nginx. El export CLI privado no es un archivo para publicar
automáticamente; evita corpus obsoleto cuando se retire o restrinja contenido.

Antes de abrir indexación: probar URLs públicas reales, canonical, alternates,
schemas, sitemap, RSS, corpus y privacidad; comprobar que robots no conserve el
bloqueo deliberado de staging. Validar el dominio en Search Console y Bing Webmaster
Tools y observar indexación/citas reales. No enviar borradores a validadores externos.
Las migraciones deben ser compatibles y preceder la activación; recuperación de
código no implica revertir datos. Puede existir una ventana de mantenimiento.

## 7. Recibir mejoras del núcleo

Las copias que incorporan el actualizador consultan releases estables públicas de `dbravoan/dbcms` independientemente de su propio origin. Guardar la identidad del núcleo y la versión del proyecto por separado: cambiar el nombre de RegulaSE no cambia su proveedor de actualizaciones.

El panel notifica la nueva versión a quienes tienen `manage:updates`. En un proyecto gestionado con Git/CI prepara una propuesta de fuentes, dependencias, conflictos y requisitos. Incorporarla a una rama de RegulaSE, revisar personalizaciones, construir los assets y ejecutar el gate del proyecto antes de desplegar. El panel no modifica el checkout ni hace push/merge automáticamente.

La plantilla conserva un historial independiente. No utilizar `--allow-unrelated-histories` como mecanismo rutinario ni sobrescribir archivos personalizados. La comparación se realiza contra inventarios oficiales firmados del núcleo, no contra la historia Git del proyecto.

Una copia antigua necesita incorporar primero el actualizador y adoptar una base verificable. No puede recibir notificaciones de código que aún no tiene. Una instalación autónoma creada desde ZIP o Composer create-project puede usar aplicación web si supera las condiciones de alojamiento y respaldo; Composer no distribuye actualmente el núcleo como dependencia actualizable mediante composer update.

Seguir [CORE_UPDATES_RUNBOOK](CORE_UPDATES_RUNBOOK.md) para incorporación, firmas, permisos, respaldo consistente, revisión, despliegue y recuperación. Las etiquetas estables anteriores permanecen inmutables.
