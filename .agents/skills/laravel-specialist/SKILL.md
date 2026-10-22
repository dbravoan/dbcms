---
name: laravel-specialist
description: Implement owned Laravel models, routing, queues and tests using current dbCMS contracts.
license: MIT
metadata:
  author: https://github.com/Jeffallan
  version: "1.1.0"
---

# Laravel implementation reference

Use [cms-clean-architecture](../cms-clean-architecture/SKILL.md) for architecture
and [AGENTS.md](../../../AGENTS.md) for execution and protected working state.
The locked application uses Laravel 13/PHP 8.3 and Passport. Do not introduce
Sanctum, Livewire or Horizon merely because a generic example mentions them.

Read the relevant reference, then inspect the actual owner and its consumers:

| Task | Reference |
| --- | --- |
| Relationships, casts and query preparation | [Eloquent](references/eloquent.md) |
| Routes, authorization and transport | [Routing](references/routing.md) |
| Jobs, retries and effects after commit | [Queues](references/queues.md) |
| Fixtures and behavior assertions | [Testing](references/testing.md) |

Keep domain services and models in their owning core area or module. Reuse shared
editorial mutation, public visibility and section contracts. Choose verification
from [TESTING](../../../docs/TESTING.md); database isolation alone does not isolate
`.env`, key files or installation locks. Report what was actually executed.
