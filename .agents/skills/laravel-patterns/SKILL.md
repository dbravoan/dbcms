---
name: laravel-patterns
description: Apply Laravel implementation references under dbCMS ownership and runtime contracts.
origin: ECC
---

# Laravel patterns in dbCMS

Read [cms-clean-architecture](../cms-clean-architecture/SKILL.md) for ownership,
module lifecycle, mutation boundaries and presentation. Read
[AGENTS.md](../../../AGENTS.md) and [TESTING](../../../docs/TESTING.md) for runtime
and verification. Do not copy a generic Laravel example over an owned contract.

Use only the reference relevant to the task:

- [Eloquent](../laravel-specialist/references/eloquent.md): relationships, casts,
  scopes and queries. Domain services own writes and transactions.
- [Routing](../laravel-specialist/references/routing.md): explicit validation,
  authorization and responses; dbCMS uses Passport for its OAuth API.
- [Queues](../laravel-specialist/references/queues.md): retry, transaction and
  worker boundaries; dispatch remote effects after commit when required.
- [Testing](../laravel-specialist/references/testing.md): useful isolated fixtures
  and behavioral assertions, subordinate to the repository's verification lanes.

Inspect current consumers and framework definitions before selecting an API.
Sanctum, Livewire, Horizon, repository wrappers and sample Order/Project domains
are not implied dependencies or mandatory layers. Adding a dependency requires a
concrete use case and verification against the locked runtime.
