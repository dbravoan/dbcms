---
name: spec-driven-planner
description: Creates formal specifications, DTO schemas, and API contracts before any code implementation begins.
---

# Spec-Driven Planner Skill

## Goal
Transform user feature requests into mathematically sound, typed specifications (`specs/<feature>.md`) adhering to Hoare logic invariants ($P \{Q\} R$).

## Procedure
1. Load `specs/templates/FEATURE_SPEC_TEMPLATE.md`.
2. Define the exact DTO class signatures in `app/DTOs/`.
3. Specify pre-conditions (validation rules), state mutations, and post-conditions.
4. Define OpenAPI response schemas and error codes.
5. Define the test acceptance matrix for the `tdd-craftsman` agent.
6. Verify against `specs/schemas/feature_spec.schema.json`.
