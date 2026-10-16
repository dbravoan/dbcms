# Rule 03 · Useful tests

Follow [AGENTS.md](../../AGENTS.md) and [Testing](../../docs/TESTING.md).

- Protect an observable current behavior, not implementation shape or case counts.
- Reproduce meaningful failures; use minimal fixtures and explicit dependencies.
- Default: `make test`. Integration: `make test-integration`. Release and changed
  installation/update/concurrency boundaries: `make test-release`.
- PHP runs only through official Sail or directly inside disposable CI containers.
- Retire tests of removed compatibility contracts; preserve security and isolation.
