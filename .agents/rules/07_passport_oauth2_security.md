# Rule 07 · Passport OAuth2 Security

**Canonical source:** [`docs/07_PASSPORT_OAUTH2_SPECIFICATION.md`](../../docs/07_PASSPORT_OAUTH2_SPECIFICATION.md) and `AGENTS.md` (Step 6).

Apply, do not restate:

- Protect API routes with `auth:api` + scopes from `App\Enums\OAuthScope` (never hardcoded strings).
- `storage/oauth-private.key` must stay `0600`; never log secrets or PII.
- MCP write tools require `mcp:write`.
