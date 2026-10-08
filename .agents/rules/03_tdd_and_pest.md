# Rule 03 · TDD & Pest

**Canonical source:** `AGENTS.md` (Step 3) and [`docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md`](../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md).

Apply, do not restate:

- Red → Green → Refactor: write the failing Pest test first.
- Unit tests in `tests/Unit/Services/`, feature tests in `tests/Feature/`, boundaries in `tests/Architecture/`.
- Run everything inside Sail (serial): `./bin/sail-run php artisan test`.
