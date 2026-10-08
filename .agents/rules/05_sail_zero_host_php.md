# Rule 05 · Sail Zero-Host PHP

**Canonical source:** [`docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md`](../../docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md) and `AGENTS.md` (Step 1).

Apply, do not restate:

- Never run `php`, `composer`, `artisan`, `pest`, `phpstan` or `pint` on the host.
- Always go through `./bin/sail-run <command>` (or `./vendor/bin/sail`).
