---
name: superagent-orchestrator
description: Master Meta-Orchestrator skill for coordinating SpecArchitect, TddCraftsman, DomainEngineer, SecurityAuditor, and GeoSeoSpecialist subagents in Laravel LTS.
---

# Superagent Meta-Orchestrator Skill

## Goal
Coordinate end-to-end feature delivery through hierarchical subagent delegation, enforcing Spec-Driven Design, Zero-Host Sail execution, TDD verification, and reviewable scoped changes.

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
5. VERIFY THE CHANGED CONTRACT (focused tests in isolated Sail)
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
9. VERIFY GEO/SEO ONLY WHEN PUBLIC CONTENT CHANGES
      |
      v
10. DELIVER CLEAN ATOMIC DIFF
```

## Error Recovery (Reflexion Trigger)
Use the fast, integration and release lanes in `docs/TESTING.md`. If tests or
`./vendor/bin/sail bin phpstan analyse` fail:
1. Extract exact stack trace and failing line numbers.
2. Formulate a targeted diff request to `DomainEngineer`.
3. Re-run tests in Sail container.
4. Continue resolving actionable failures. Report the exact blocker and evidence when progress requires unavailable input; do not stop solely because a retry count was reached.
