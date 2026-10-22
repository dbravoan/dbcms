# Document 06: Zero-Host PHP & Laravel Sail Containerization Runbook

## Core Security & Determinism Doctrine
Running PHP, Composer, or Artisan directly on the host machine is strictly prohibited. **Laravel Sail** supplies the project PHP runtime and extensions independently of host PHP. Reproducibility also depends on locked dependencies, image and configuration. Local Sail mounts the working directory with write access: code in the container can modify those host files. It is not a sandbox for untrusted PHP. Installation/update tests need the disposable filesystem and database described in [TESTING](TESTING.md).

## Sail Architecture

```
+-----------------------------------------------------------------------------------+
|                              DOCKER COMPOSE NETWORK                               |
+-----------------------------------------------------------------------------------+
|  [laravel.test]       |  [mysql]            |  [redis]          |  [mailpit]      |
|  - PHP 8.3-cli        |  - MySQL 8.0        |  - redis:alpine   |  - SMTP Port:   |
|  - artisan serve      |  - Port: 3306       |  - Port: 6379     |    1025         |
|  - Composer / Pint    |  - Vol: sail-mysql  |  - Vol: sail-redis|  - UI Port:     |
|  - Pest / PHPStan     |                     |                   |    8025         |
+-----------------------------------------------------------------------------------+
```

## First bootstrap without vendor

Guided local setup currently requires Linux with Docker and Compose.
For a new derived project, obtain sources through Docker/Composer, a cloned
GitHub Template or an extracted source ZIP, enter that directory and run:

```bash
make init
# Without Make:
./bin/project-init
```

Plain `make` prints help. Follow the [derived project guide](DERIVED_PROJECTS_GUIDE.md)
for the complete entry commands and composition choices. This guided flow requires
a published compatible stable release; development code alone does not make it
available through Composer. An unavailable or unverifiable release fails before
source changes.

The initializer uses Docker to bootstrap missing dependencies and official Sail
for local services. It confirms the source/local plan, installs locked dependencies,
creates `.env` only if absent, assigns isolated Compose identity/available ports
and prefixes, generates only a missing APP_KEY and builds assets. It preserves
existing environment values, keys and data. It prints actual service URLs and
`/install`; finish the web installer without demo. Bootstrap itself runs no
migrations, creates no demo accounts and generates no OAuth keys.

Repeat `make init` after a failed step: an existing valid profile pins the source
and composition and resumes local setup without resolving latest again. Never
reset volumes, replace `.env` or rotate keys to retry.

For CI source preparation, pass `--prepare-only --no-interaction` with explicit
name, brand, theme and deployment. CI/bootstrap scripts already inside Docker run
PHP/Composer directly and must never invoke Sail or create nested local services.
Native VPS PHP remains part of the separate deployment procedure.

For development of the dbCMS source distribution itself, rather than a new child,
bootstrap its locked dependencies in the compatible container when vendor is absent:

```bash
docker build --target ci -f docker/production/Dockerfile -t dbcms-bootstrap .
docker run --rm --user "$(id -u):$(id -g)" \
  --env COMPOSER_HOME=/tmp/composer \
  --volume "$PWD:/var/www/html" --workdir /var/www/html \
  dbcms-bootstrap bash -c 'mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs && composer install --prefer-dist --no-interaction'
```

Review any new environment and use unique Compose names/ports before starting it.
Once dependencies exist, start official Sail explicitly with
`./vendor/bin/sail up -d --build` on first launch, then `up -d` for later starts.
Sail needs active services before Artisan, Composer or tests; stop with
`./vendor/bin/sail down`.

## Existing checkouts and dependency ownership

Official Sail executes local tools as `sail`, mapped to your workstation UID/GID.
If previous root executions created `vendor/` or `node_modules/`, those dependency
directories must become writable by this user before Composer/npm can update them.
After starting Sail with the correct build arguments, open its privileged shell:

```bash
# ON YOUR WORKSTATION, with Sail already running:
./vendor/bin/sail root-shell
```

Inside that container, adjust only existing dependency directories and leave the shell:

```bash
for dependency_directory in vendor node_modules; do
    if [ -d "$dependency_directory" ] && [ ! -L "$dependency_directory" ]; then
        chown -hR sail:sail "$dependency_directory"
    fi
done
exit
```

This step preserves dependency contents and does not follow symbolic links.
Keep `.env`, storage, uploads and application keys outside this ownership repair;
do not apply a recursive permission change to the whole project.

## Daily Developer & Agent CLI Commands
```bash
# 1. Start all containers in background
./vendor/bin/sail up -d
# or via Makefile
make up

# 2. Run Composer commands (Zero-Host)
./vendor/bin/sail composer install
./vendor/bin/sail composer update

# 3. Run Database Migrations
./vendor/bin/sail artisan migrate

# 4. Run fast contracts and small functional journeys
make test
# Complete integration/release checks use disposable files and databases:
make test-integration
make test-release

# 5. Run Static Analysis (PHPStan Level 8)
./vendor/bin/sail bin phpstan analyse

# 6. Run Code Formatter (Laravel Pint)
./vendor/bin/sail pint

# 7. Run an arbitrary program already installed in the Sail container
./vendor/bin/sail run ffmpeg -version

# 8. Stop the local environment
./vendor/bin/sail down
```

## Composición básica y procesos opcionales

`./vendor/bin/sail up -d` levanta también Redis y espera a que MySQL y Redis estén saludables antes de arrancar Laravel. Así funcionan tanto los valores iniciales (archivos para sesiones y caché, MySQL para colas) como las instalaciones que configuran drivers Redis en `.env`. `./vendor/bin/sail --profile workers up -d` añade el worker general y scheduler. Los perfiles `creative` y `social` añaden sus workers, el worker general y scheduler. Los perfiles no instalan ni activan módulos del CMS.
