# Command: /sdd-feature

Executes a full Spec-Driven Development cycle for a new domain feature:
1. **SpecArchitect**: Drafts `specs/{feature_name}.md` and DTOs in `app/DTOs/`.
2. **TddEngineer**: Writes Pest tests in `tests/Unit/`, `tests/Feature/`, and `tests/Architecture/`. Confirms failure.
3. **DomainEngineer**: Implements Contract & Service in `app/Services/`. Runs tests via `./vendor/bin/sail pest`.
4. **GeoSeoSpecialist**: Updates Schema.org JSON-LD and `/llms.txt`.
5. **SecurityAuditor**: Validates Passport scopes and authorization.
