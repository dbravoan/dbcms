---
name: superagent-orchestrator
description: Coordinate substantial dbCMS changes across independent subsystems when delegation improves implementation and verification.
---

# Coordinate dbCMS work

Read [AGENTS.md](../../../AGENTS.md), the governing specification and the
[verification lanes](../../../docs/TESTING.md). Establish actual ownership and
current behavior before splitting the task.

Delegate independent work with explicit file ownership, common contracts and
acceptance criteria. Use focused context. Reconcile shared changes and inspect
dynamic registrations before removing code. Small edits do not require a team,
new DTOs or a fixed sequence of specialist roles.

Keep PHP/Composer in Sail or the existing CI/bootstrap container. Protect working
files, secrets and data; run installation/update checks in disposable filesystems
and databases. Verify affected behavior, static analysis and the required global
lane. Security, transaction and concurrency evidence cover different boundaries.

Check framework support when relevant to the task, using
`system:check-framework-support`; do not make an unrelated upgrade review a
prerequisite for every change. Review GEO/SEO when public discovery changes.
Report concrete results and unresolved limits without declaring perfection.
