# Document 06: Zero-Host PHP & Laravel Sail Containerization Runbook

## Core Security & Determinism Doctrine
Running PHP, Composer, or Artisan directly on the host machine is strictly prohibited. Containerization via **Laravel Sail** ensures:
- 100% environment reproducibility across developer machines and CI/CD runners.
- Elimination of host PHP version mismatches (e.g. host running PHP 8.1 while app requires PHP 8.3).
- Sandboxed isolation preventing malicious scripts or rogue commands from touching host system files.

## Sail Architecture

```
+-----------------------------------------------------------------------------------+
|                              DOCKER COMPOSE NETWORK                               |
+-----------------------------------------------------------------------------------+
|  [laravel.test]       |  [mysql]            |  [redis]          |  [mailpit]      |
|  - PHP 8.3-cli        |  - MySQL 8.0/8.4    |  - Redis 7.x      |  - SMTP Port:   |
|  - Nginx / PHP-FPM    |  - Port: 3306       |  - Port: 6379     |    1025         |
|  - Composer / Pint    |  - Vol: sail-mysql  |  - Vol: sail-redis|  - UI Port:     |
|  - Pest / PHPStan     |                     |                   |    8025         |
+-----------------------------------------------------------------------------------+
```

## First bootstrap without vendor

Official Sail is installed by Composer and does not exist before `vendor/`.
The root Compose build context also lives under `vendor/laravel/sail/`, so it
cannot bootstrap its own missing dependency. For a source checkout, use the
compatible project CI image; do not run Composer on the host or install the lock
with an image missing the required PHP extensions:

```bash
docker build --target ci -f docker/production/Dockerfile -t dbcms-bootstrap .
docker run --rm --user "$(id -u):$(id -g)" \
  --env COMPOSER_HOME=/tmp/composer \
  --volume "$PWD:/var/www/html" --workdir /var/www/html \
  dbcms-bootstrap bash -c 'mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs && composer install --prefer-dist --no-interaction'
test -f .env || cp .env.example .env
./vendor/bin/sail up -d --build
```

Review the new environment before starting it: use separate ports and Compose
project names for separate sites. Generate application/OAuth keys only for a new
installation, as described in the README; preserve existing keys. The domain
initializer `bin/project-init` performs its own Docker bootstrap before selecting
brand, themes and deployment, without requiring vendor or generating keys.

Once dependencies exist, official `./vendor/bin/sail` is the sole local tooling
entry point. It requires running services: start them explicitly with `up -d`
(`up -d --build` on the first launch). Unlike the removed custom command bridge,
Sail does not automatically start a one-off container for Artisan or a test.
Use `./vendor/bin/sail down` to stop the local environment.

CI/bootstrap scripts already inside Docker execute PHP/Composer directly. They
must not invoke Sail, require a Docker socket or create nested containers. Native
VPS PHP commands remain part of the separate remote deployment procedure.

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

El arranque básico usa archivos para sesiones y caché, y MySQL para colas; no depende de Redis ni de workers. `./vendor/bin/sail --profile workers up -d` añade el worker general y scheduler. Los perfiles `creative` y `social` añaden sus workers, el worker general, scheduler y Redis. Para Redis sin marketing use `./vendor/bin/sail --profile redis up -d` y configure expresamente los drivers que lo consuman. El perfil no instala ni activa módulos del CMS.
