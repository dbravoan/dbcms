---
name: spec-driven-planner
description: Define dbCMS behavior, ownership, affected contracts and acceptance scenarios before implementing a feature or substantive refactor.
---

# Specify the change

Read [Core and extensions](../../../specs/CORE_AND_EXTENSIONS.md), the affected
implementation and existing specification. Use the
[feature template](../../../specs/templates/FEATURE_SPEC_TEMPLATE.md) proportionally.

State the goal, current failure, owner, observable behavior and non-goals. Record
relevant authorization, availability, locale/market, transaction and publication
invariants. For removals, identify consumers and tests that move or disappear.

Describe only contracts that change. Do not generate DTOs, interfaces, API endpoints,
Redis state or SEO output to fill sections. Put meaningful types in their owner.
Choose concrete acceptance scenarios and isolated verification lanes from
[TESTING](../../../docs/TESTING.md).

Markdown specifications are reviewed as prose. When producing an actual JSON
specification, validate it against `specs/schemas/feature_spec.schema.json`; that
schema does not validate Markdown. Mark replaced specifications as superseded.
