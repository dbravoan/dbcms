---
name: cms-clean-architecture
description: Apply dbCMS ownership, module lifecycle, shared mutation and presentation boundaries when reviewing or changing this repository.
---

# dbCMS architecture

Read [Core and extensions](../../../specs/CORE_AND_EXTENSIONS.md),
[conventions](../../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md) and the area
specification. Follow [AGENTS.md](../../../AGENTS.md) for runtime and working-state
safety. These sources take precedence over historical examples.

- Pages is core in `App\Content\Pages`; shared rendering is
  `App\Core\Sections\SectionRendererService`.
- Optional modules own their models, migrations, routes, navigation and capabilities.
  Discovery reads; explicit installation changes persistent state. A disabled or
  absent module cannot expose executable contributions.
- Blocks and widgets use one definition/registry/availability decision. Charts,
  KPI/ROI and BeforeAfter are visual packages. Preserve unavailable instances.
- Controllers validate/delegate/respond. Domain services own business decisions
  and transaction boundaries; Blade receives data without executing queries.
  Judge responsibilities, not line counts. Do not add forwarding wrappers to
  shorten an action or mirror every helper with an interface.
- Interfaces represent substitution or subsystem boundaries. DTOs type meaningful
  inputs/outputs in their owner. Reuse entity mutation and editorial contracts;
  remote effects follow commit. Private entities do not enter public discovery.
- Use shared semantic admin components and verify both installed brands. Inspect
  current component props and theme resolution before copying an example.
- Fresh-installation development does not require historical aliases/converters.
  Inspect runtime registration and consumers before deleting a contract.

Use [canonical examples](references/CANONICAL_PATTERNS.md) when choosing an
existing implementation to follow, and [anti-patterns](references/ANTI_PATTERNS.md)
when reviewing a suspected boundary violation. Keep PHP strict types, PHPStan
level 8 and Pint. Choose verification from [TESTING](../../../docs/TESTING.md);
filesystem-mutating installation/update tests need disposable code and data.
