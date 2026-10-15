# Rule 05 · Sail Zero-Host PHP

**Canonical source:** [`docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md`](../../docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md) and `AGENTS.md` (Step 1).

Apply, do not restate:

- Never run `php`, `composer`, `artisan`, `pest`, `phpstan` or `pint` on the host.
- Always go through `./vendor/bin/sail <command>`.

- Start with `./vendor/bin/sail up -d` (`--build` on first launch); stop with `down`.
- Missing vendor requires the canonical Docker bootstrap, not host Composer.
- Within an existing CI/bootstrap container run PHP/Composer directly; never nest Sail.
- Use `sail bin phpstan analyse` for PHPStan and `sail run <program>` for arbitrary tools.
