---
name: superagent-orchestrator
description: Master Meta-Orchestrator skill for coordinating SpecArchitect, TddCraftsman, DomainEngineer, SecurityAuditor, and GeoSeoSpecialist subagents in Laravel LTS.
---

# Superagent Meta-Orchestrator Skill

## Goal
Coordinate end-to-end feature delivery through hierarchical subagent delegation, enforcing Spec-Driven Design, Zero-Host Sail execution, TDD verification, and strict token budgeting.

## Operational Workflow

```
[User Request]
      |
      v
1. DISAMBIGUATE & BOUND SCOPE
      |
      v
2. CHECK LTS HEALTH & RUNTIME (./vendor/bin/sail artisan system:check-lts)
      |
      v
3. DELEGATE SPEC TO SpecArchitect (specs/<feature>.md + DTOs)
      |
      v
4. DELEGATE TDD TO TddCraftsman (tests/Unit/* + tests/Feature/*)
      |
      v
5. VERIFY RED STATE (./vendor/bin/sail pest)
      |
      v
6. DELEGATE IMPLEMENTATION TO DomainEngineer (app/Services/*)
      |
      v
7. EXECUTE REFLEXION LOOP (Pest + PHPStan Level 8)
      |
      v
8. DELEGATE SECURITY TO SecurityAuditor (Passport OAuth2 Scopes)
      |
      v
9. DELEGATE GEO/SEO TO GeoSeoSpecialist (/llms.txt + JSON-LD)
      |
      v
10. DELIVER CLEAN ATOMIC DIFF
```

## Error Recovery (Reflexion Trigger)
If `./vendor/bin/sail pest` or `./vendor/bin/sail bin phpstan analyse` fails:
1. Extract exact stack trace and failing line numbers.
2. Formulate a targeted diff request to `DomainEngineer`.
3. Re-run tests in Sail container.
4. Limit retries to 3 cycles. If failure persists, halt and report the invariant mismatch to the user.
