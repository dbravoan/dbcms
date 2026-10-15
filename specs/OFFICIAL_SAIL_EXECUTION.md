# Official Laravel Sail execution

> **Spec ID**: FEAT-OFFICIAL-SAIL
> **Status**: APPROVED
> **Date**: 2026-10-15

Local PHP, Composer and Artisan operations use Laravel's official `./vendor/bin/sail` entry point. Existing purpose-specific project initialization, update, LTS and GEO helpers resolve their own project directory before dispatch. An already containerized execution invokes PHP/Composer directly and never launches another Sail or Docker runtime. Initialization and update retain their Docker bootstrap when dependencies, including Sail, are unavailable.

Remove the redundant general execution wrapper. Keep Composer's host guard and point its actionable examples, Make targets, application command hints and maintained guides at the official Sail interface. Preserve the established production-native CLI behavior for rsync and Ansistrano deployments. Do not change local environment values, installation state, permissions or application data as part of this refactor.

Documentation checks recognize commands invoked through official Sail, including its `php artisan` passthrough, and still recognize direct Artisan examples used inside containers or production. Unknown documented commands must fail validation; prose, flags and unrelated Sail subcommands must not be misidentified as Artisan commands.

Acceptance: fixture-based container dispatch verifies exact argument forwarding, project working directory and absence of nested runtimes; helper execution leaves unrelated protected file permissions unchanged; documented command extraction has valid, invalid and non-command examples. Run focused Pest, shell syntax and appropriate static checks inside Docker before the full quality gate.
