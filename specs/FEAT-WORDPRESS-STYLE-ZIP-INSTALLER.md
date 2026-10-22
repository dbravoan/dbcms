# Feature Specification: WordPress-style ZIP installer

> **Spec ID**: `FEAT-INSTALLER-002`
> **Status**: IMPLEMENTED (static review; acceptance checklist is not a current test run)
> **Author**: `SpecArchitect` Agent

---

## 1. Domain Objective & Context

The official dbCMS archive must be installable on PHP 8.3+ shared hosting by uploading, extracting and visiting the site. The first HTTP request must prepare the minimum safe environment before Laravel loads, so an absent `APP_KEY` never produces `MissingAppKeyException`.

This boundary is deliberately framework-independent: it must also render a safe recovery/preflight response when Composer dependencies, the PHP runtime or writable paths are unavailable. It must never repair an already sealed installation by silently rotating its encryption key.

## 2. Input and Result Contracts

The prebootstrap accepts only server-derived context: project root, current request scheme/host, PHP runtime details and filesystem state. It has no database dependency.

The following labels describe outcomes; they are not a returned enum or JSON
status field. `InstallerBootstrap::prepare()` returns `null` to continue, or
a `title/message/details` failure array rendered by `public/index.php` with 503:

| State | HTTP behaviour | Mutation |
| --- | --- | --- |
| `ready` | Continue into Laravel | None |
| `initialized` | Continue into Laravel installer | Atomically create `.env` with a valid key |
| `recover_key` | Render generic recovery page, status 503 | None |
| `missing_vendor` | Render dependency/package error, status 503 | None |
| `unsupported_php` | Render compatible-version message, status 503 | None |
| `unwritable` | Render panel-oriented writable-path guidance, status 503 | None |

No prebootstrap response exposes filesystem paths, secrets, stack traces or environment contents.

## 3. Formal Invariants (Hoare Logic)

- **Clean first request**: given no `storage/installed` and no `.env`, running prebootstrap creates `.env` from `.env.installation.example`, stores exactly one cryptographically random 32-byte base64 `APP_KEY`, applies mode `0600`, and only then loads Composer and Laravel.
- **Concurrent first requests**: given two requests against the same clean root, an exclusive lock and same-directory atomic replacement ensure both observe one complete `.env` and the same valid key; no partial file or duplicate key survives.
- **Existing unsealed environment**: given no installation lock and a valid persisted key, the key is preserved byte-for-byte.
- **Sealed recovery**: given `storage/installed` and an absent, empty or malformed key in `.env`, prebootstrap never writes `.env` or generates a key; it returns `recover_key`.
- **Package integrity**: given a release build, the installer archive contains production dependencies, compiled assets, both Apache entry configurations, runtime directories, `.env.installation.example` and `EMPEZAR.html`, but contains no `.env`, installation lock, private key or development dependency.
- **Version immutability**: v3 starts with `3.0.1`; published stable tags and artifacts cannot be replaced.

## 4. Installation Configuration Contract

`.env.installation.example` uses production-safe bootstrap defaults:

- `APP_ENV=production`, `APP_DEBUG=false`, empty `APP_KEY` and a reviewable `APP_URL` placeholder.
- `SESSION_DRIVER=file`, `CACHE_STORE=file` and `QUEUE_CONNECTION=sync`, so the installer does not require migrated tables, Redis or a worker.
- MySQL/MariaDB connection placeholders and no real credentials.

After migrations succeed, the installer atomically persists `SESSION_DRIVER=database`, `CACHE_STORE=database` and `QUEUE_CONNECTION=database`. A failed migration preserves bootstrap drivers, creates neither administrator nor `storage/installed`, and remains retryable.

The pre-Laravel first request derives the suggested `APP_URL` from server `HTTPS`
and a validated `HTTP_HOST`/`SERVER_NAME`, falling back to localhost for an invalid
host. It does not process forwarded proxy headers; review the URL explicitly
behind a proxy. Laravel’s later trusted-proxy handling is a separate boundary.
The Docker runtime additionally rejects a supplied APP_KEY that differs from its
persisted runtime environment; the ZIP bootstrap itself validates the `.env` key.

## 5. Distribution Contract

- Primary release asset: `dbcms-installer-v3.0.1.zip`.
- Administrative archive: `dbcms-v3.0.1.tar.gz`.
- SHA-256 checksums cover both named assets.
- `EMPEZAR.html` identifies the installer ZIP as the end-user download, explains Plesk/cPanel upload/extract/visit steps and distinguishes GitHub's automatic source archives as developer-only.
- The build fails if required runtime files are absent or forbidden secrets/locks are present.
- The archive smoke test starts the unpacked application without Composer or Node, performs a first HTTP request without `.env`, completes a real installation, then verifies login, public homepage and `/up`.

## 6. Test Invariant Checklist

- [ ] First HTTP request initializes one valid persistent key before Laravel boots.
- [ ] Concurrent initialization yields one complete environment and one key.
- [ ] Sealed installation never creates or rotates a missing/invalid key.
- [ ] Existing valid key is preserved.
- [ ] Bootstrap template has production/file/sync defaults and no credential.
- [ ] Missing vendor, incompatible PHP and unwritable paths use safe preflight output.
- [ ] Release filename and release notes distinguish installer from source archives.
- [ ] Archive content, secret exclusion and post-extraction HTTP installation are verified during mandatory local preparation; GitHub verifies signed artifacts under [v3 delivery](V3_DELIVERY.md).
