# Rule 02 · Thin Controllers, Fat Services

**Canonical source:** `AGENTS.md` (Step 4) and [`docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md`](../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md).

Apply, do not restate:

- Controllers: ≤ 10 lines per `__invoke()`, no SQL, no business branching.
- Domain logic lives in `app/Services/**` (or `modules/*/Services`), implementing `App\Contracts\Services\*Interface`.
- Wrap multi-write operations in `DB::transaction()`.
