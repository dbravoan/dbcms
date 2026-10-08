# Document 07: Laravel Passport OAuth2 Specification & Security

## Overview
This skeleton uses **Laravel Passport** to provide a full OAuth2 server implementation conforming to RFC 6749. 

## Supported Grant Types & Authentication Modes
1. **Personal Access Tokens**: For user sessions and direct developer API keys.
2. **Client Credentials Grant**: For machine-to-machine (M2M) backend communication and agent microservices.
3. **Password Grant / Authorization Code Grant with PKCE**: For mobile and Single-Page Applications (SPA).

## Fine-Grained OAuth2 Scopes
Scopes are strictly typed via the `App\Enums\OAuthScope` enum:
- `read:content`: Grants read-only access to content, sitemaps and GEO resources.
- `write:content`: Grants write/update/delete access to content.
- `admin:system`: Full administrative access to system monitoring and LTS agent tools.

## Key Management in Docker Sail
Encryption keys (`oauth-private.key` and `oauth-public.key`) must be generated inside the Sail container and mounted to `storage/`:
```bash
./bin/sail-run php artisan passport:keys --force
```
In production CI/CD pipelines, keys are passed securely via environment variables (`PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY`).
