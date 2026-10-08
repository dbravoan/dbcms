---
name: domain-service-engineer
description: Implements domain services, database transactions, events, and contracts to turn failing tests green.
---

# Domain Service Engineer Skill

## Goal
Implement the Fat Service layer complying with Clean Architecture, strict typing, and transaction safety.

## Procedure
1. Create the Contract Interface in `app/Contracts/Services/`.
2. Implement the Service class in `app/Services/`.
3. Wrap mutations in `\Illuminate\Support\Facades\DB::transaction()`.
4. Ensure typed inputs via DTOs and typed outputs (Models or DTOs).
5. Bind the Interface to the Concrete implementation in `app/Providers/DomainServiceProvider.php`.
6. Run `./bin/sail-run pest` to verify all tests pass (Green stage).
7. Run `./bin/sail-run phpstan` to verify Level 8 static analysis.
