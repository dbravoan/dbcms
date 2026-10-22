# Sail and isolated execution

Follow [AGENTS.md](../../AGENTS.md) and
[Zero Host](../../docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md).
Start local Sail explicitly; bootstrap missing vendor in Docker. Inside an existing
CI/bootstrap container run PHP/Composer directly without nesting Sail. Native VPS
commands follow the deployment runbook. Working bind mounts are writable host
files; tests changing installation/update files need disposable code and data.
