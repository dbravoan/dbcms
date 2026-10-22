# Document 07: Laravel Passport OAuth2 Specification & Security

## Overview
This skeleton uses **Laravel Passport** to provide a full OAuth2 server implementation conforming to RFC 6749. 

## Supported Grant Types & Authentication Modes
1. **Personal Access Tokens**: For explicitly issued user API tokens. Backoffice sessions use the web guard.
2. **Client Credentials Grant**: For machine-to-machine (M2M) backend communication and agent microservices.
3. **Authorization Code Grant with PKCE**: For interactive OAuth clients. The password grant is not enabled.

## Fine-Grained OAuth2 Scopes
The authoritative scope catalogue is `App\Enums\OAuthScope::definitions()`. Scopes supplement resource permissions; they never replace RBAC. Main core scopes:
- `read:content`: Grants read-only access to content, sitemaps and GEO resources.
- `write:content`: Creates and updates authorized content. Deletion still requires the corresponding operation permission.
- `admin:system`: Administrative transport scope for system operations; CMS permissions and capability-specific gates still apply.

MCP uses `mcp:access` plus `mcp:read`, `mcp:write` or `mcp:destructive` for the operation. Destructive capabilities also require a bound human approval. M2M clients bind an explicit CMS service account and role. See [MCP connections](25_CONNECTING_AI_AGENTS_OVER_MCP.md). Optional modules own their API operations; their scope strings are also enumerated in the central typed catalogue.

## Key Management in Docker Sail
Encryption keys (`oauth-private.key` and `oauth-public.key`) must be generated inside the Sail container and mounted to `storage/`:
```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan passport:keys
```
Production can supply keys through `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` or persistent storage files. The workflows do not provision these secrets automatically.

Keep existing keys stable. Production supports persistent files in storage or the configured Passport key environment variables.
