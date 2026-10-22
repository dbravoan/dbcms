---
name: domain-service-engineer
description: Implements domain services, database transactions, events, and contracts to turn failing tests green.
---

# Domain Service Engineer Skill

## Goal
Implement the Fat Service layer complying with Clean Architecture, strict typing, and transaction safety.

## Procedure
1. Define the behavior and decide whether a substitution or subsystem boundary actually needs a Contract Interface.
2. Implement the service in its owner: core `app/Services/`, `app/Content/Pages/`, or the optional module’s `Services/` directory.
3. Use `\Illuminate\Support\Facades\DB::transaction()` for related atomic database writes; keep remote HTTP outside the transaction and dispatch dependent effects after commit.
4. Type meaningful boundaries; avoid DTOs and forwarding wrappers with no independent responsibility.
5. Bind necessary interfaces in the owning provider; use concrete services for simple internal collaborators.
6. Run the applicable fast/integration/release lane from `docs/TESTING.md`.
7. Run `./vendor/bin/sail bin phpstan analyse` to verify Level 8 static analysis.
