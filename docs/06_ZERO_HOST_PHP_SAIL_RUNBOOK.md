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

## Daily Developer & Agent CLI Commands
```bash
# 1. Start all containers in background
./vendor/bin/sail up -d
# or via Makefile
make up

# 2. Run Composer commands (Zero-Host)
./bin/sail-run composer install
./bin/sail-run composer update

# 3. Run Database Migrations
./bin/sail-run php artisan migrate

# 4. Run Test Suite (Pest)
./bin/sail-run pest

# 5. Run Static Analysis (PHPStan Level 8)
./bin/sail-run phpstan

# 6. Run Code Formatter (Laravel Pint)
./bin/sail-run pint
```
