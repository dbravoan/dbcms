# Rule 01 · Architecture & Clean Code

**Canonical source:** [`docs/01_ARCHITECTURE_AND_CLEAN_CODE.md`](../../docs/01_ARCHITECTURE_AND_CLEAN_CODE.md) and [`docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md`](../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md).

Apply, do not restate:

- `declare(strict_types=1);` in every PHP file.
- PHPStan **Level 8** (0 errors) + Laravel Pint before committing.
- Immutable DTOs (`readonly class`) in `app/DTOs/`.
- Thin controllers, fat domain services; models are schema-only.
