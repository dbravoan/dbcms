# Rule 02 · Thin Controllers, Fat Services

**Canonical source:** `AGENTS.md` and [`docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md`](../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md).

Apply, do not restate:

- Controllers: ≤ 10 lines per `__invoke()`, no SQL, no business branching.
- Domain logic lives in its owning service; introduce interfaces only for useful substitution or subsystem boundaries.
- Wrap multi-write operations in `DB::transaction()`.
