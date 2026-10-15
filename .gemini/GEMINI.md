# Gemini / Antigravity Agent Configuration (GEMINI.md)

This project is configured for autonomous, token-efficient pair programming using the Antigravity Agent Engine and OpenCode.

## Primary Directives

1. **Strictly Containerized PHP**: Never run `php`, `artisan`, or `composer` directly on the host machine. Use official `./vendor/bin/sail <command>` for local tools after explicit `up -d` (`--build` initially); stop with `down`. For missing vendor follow the canonical Docker bootstrap. Inside existing CI/bootstrap containers execute PHP/Composer directly, without nested Sail.
2. **Architecture**: Clean Architecture with strict separation:
   - Thin Controllers (validation, DTO mapping, service invocation, API Resource output).
   - Thin Models (relational schema, casts, fillable; zero business algorithms).
   - Fat Services (all domain logic, DB transactions, cache, events).
3. **Spec-Driven**: Draft specs in `specs/` before authoring code.
4. **Token Economics**: Maximize KV-cache reuse. Do not re-read large unchanged files. Use `replace_file_content` for surgical edits.
5. **Always Laravel LTS**: Verify current version against LTS roadmap and notify of upgrades.
6. **Built-in GEO & SEO**: Every content model must supply JSON-LD structured schemas and support `/llms.txt`.
7. **OAuth2 Passport**: Secure API authorization using Laravel Passport with fine-grained scopes.
