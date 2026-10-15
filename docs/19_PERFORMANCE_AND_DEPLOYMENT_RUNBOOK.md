# 19 · Performance & Deployment Runbook

Operational guide for building, caching, hardening and deploying dbCMS on a
VPS/Plesk hosting with native PHP CLI, or an explicitly selected Docker runtime.
Complements `DEPLOYMENT_GUIDE.md` (releases, persistent state and promotion)
with the concrete performance and security mechanics the code relies on.

---

## 1. Front-end build (required before deploy)

Assets are **not** versioned: `public/build` is in `.gitignore`. A release must
compile them, otherwise the site loads without CSS/JS.

```bash
npm ci
npm run build      # runs `prebuild` (FontAwesome generator) + `vite build`
```

- `quality.yml` compiles assets and verifies `public/build/manifest.json` inside Docker.
- The production Dockerfile includes the generator in `bin`, the seven themes, resources and modules before Vite runs.
- `deploy.yml` transfers the built application directory for the github-actions profile; local rsync and Ansistrano use their own commands; existing PHP-FPM/Plesk continues serving the site. PHP-FPM reload must succeed before the health gate.

## 2. OPcache

When PHP-FPM uses `validate_timestamps=0`, a successful reload is required after changing `current`. In the optional immutable Docker runtime, replacing the image replaces PHP-FPM as well.

## 3. Caching layers

| Layer | Mechanism | Invalidation |
| :--- | :--- | :--- |
| Public HTML | `CachePublicPage` middleware, neutral snapshots, short TTL, per-request hydration | dependency-based (see below) |
| Menu / markets | version-keyed cache (`cms:page-cache:version`) | global namespace bump |
| Sitemap / RSS / llms | content version (`cms:public-index:version`) plus publication-boundary fingerprint | committed content/translation writes and publication time boundaries |
| Public search | denormalized local index, public visibility revalidation and atomic bulk upserts | affected entity after commit; module/context changes rebuild registered sources |
| Framework | `config:cache`, `route:cache`, `view:cache` | `optimize:clear` |

Dependency-based page cache:

- While rendering, `CacheDependencyCollector` records every cacheable model read
  (`type:id`, `type:*`).
- `PageCacheService` keeps a reverse index (dependency → cached pages).
- `PageCacheObserver`: an **update** drops only the pages that read that entity;
  publication/list-membership changes and **create/delete** drop pages that read its type.
- Empty collections record type dependencies, so publishing the first entity also invalidates a cached empty listing. Translation writes and after-commit invalidation keep HTML and public indexes current.
- Personal header/cart state, consent/geo dialogs, analytics and CSRF are stripped before storage and rendered for each request. CSP nonces rotate on hits. Hydrated responses are `private, no-store`; conditional requests receive fresh HTML instead of a reused 304. Authenticated requests, flashes, cart/checkout/orders and CAPTCHA-bearing forms render live.
- Models listed in `cms.page_cache_global_models` (Setting, Market, Language,
  Menu, MenuItem) are layout/site-wide: they bump the global version instead.
- `PublicationBoundaryService` adds time to HTML and discovery cache keys. The
  first request at a scheduled deadline sees the new namespace without waiting
  for the general TTL or a scheduler. TTL is also capped at the next deadline;
  the boundary query state is reused within the current second and content version.
- Sitemap children are partitioned at 50,000 URLs or 50 MiB uncompressed. Global
  and regional indexes advertise numbered child documents. See
  [`GEO_PUBLIC_CONTRIBUTORS.md`](GEO_PUBLIC_CONTRIBUTORS.md) for limits and extensions.

### Search indexing and reproducible performance checks

Page/Gallery sources load each entity's translations, markets and public sections
once, then iterate in groups of 200. `SearchIndexer` writes upserts in groups of
200 inside a local transaction. A source that fails after its first completed
group preserves the previous index through rollback. The full rebuild is atomic
as well. These are local read/write operations; source adapters must not make HTTP
requests inside that transaction. A larger corpus increases transaction duration
and index lock time, so measure on a representative copy before choosing rebuild
windows.

`SearchIndexer::batch()` deduplicates repeated type/entity requests during an
import and projects final data once per entity. The demo seeder uses it without
suppressing audit or other model events. The regression
`batch imports defer repeated projections and incremental edits and deletions remain indexed`
creates a page, creates its translation and edits the translation within one
batch. A counting decorator verifies **one source projection**, then verifies that
normal later edits and deletion still refresh the index. This is a deterministic
operation-count check, not a claimed production latency benchmark.

Reproduce it in an isolated quality container/database/filesystem using the
repository's Docker CI setup, running:

```bash
./vendor/bin/pest tests/Feature/Search/PageGallerySourcesTest.php --filter='batch imports'
./vendor/bin/pest tests/Feature/Search/PageGallerySourcesTest.php --filter='failed source replacement'
./vendor/bin/pest tests/Feature/Seo/DiscoveryPendingClosureTest.php
```

These commands must run in the disposable quality runtime, never against the
development database: the suites use `RefreshDatabase`. Record the code revision,
container image, fixture size, assertions, SQL counts if measured, elapsed time and
concurrent load. The local audit showed the cost of indexing on every seed event;
deduplication restored short fixture runs. Its timings under shared Docker load
are diagnostic observations and do not establish a throughput or latency SLA.

After a bulk recovery/import, an operator can rebuild the private index with
`search:reindex`. Market/language resource changes and module activation/reinstallation
already trigger the required rebuild after commit. Search still filters current
public availability, including future publication deadlines, before serving results.

Manual purge:

```bash
./vendor/bin/sail artisan cms:cache:purge          # bump namespace version
./vendor/bin/sail artisan optimize:clear           # config/route/view/event caches
```

## 4. Security headers & CSP

`App\Http\Middleware\SecurityHeaders` attaches, on every response:

- `Content-Security-Policy` with a per-request nonce (`'strict-dynamic'`,
  `default-src 'self'`), shared to Blade as `$cspNonce`.
- `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`,
  `Referrer-Policy: strict-origin-when-cross-origin`, `Permissions-Policy`,
  `Cross-Origin-Opener-Policy`.
- `Strict-Transport-Security` only when the request is HTTPS.

All front assets are **self-hosted** (fonts, icons, CSS, JS, media), so no
third-party origins are required. If you add one, extend the policy in
`SecurityHeaders::contentSecurityPolicy()`.

Two implementation notes:

- **Alpine** evaluates `x-data`/`x-on` expressions at runtime with the standard
  build, so `script-src` includes `'unsafe-eval'`. Dropping it breaks every
  interactive component (dropdowns, modals, tooltips). To remove it you must
  migrate to `@alpinejs/csp` and rewrite the expressions as registered
  components.
- **Inline scripts** must carry the per-request nonce:
  `<script nonce="{{ $cspNonce ?? '' }}">`. In local, only reachable
  directives are extended with `http://localhost:5173` (Vite HMR); value-less
  directives such as `object-src 'none'` are left untouched.

### Nonce and the page cache

`CachePublicPage` stores the rendered HTML, which embeds the CSP nonce of the
request that produced it. On a cache **hit** the middleware rewrites the stored
nonce to the current request's nonce
(`withFreshNonce()`), otherwise the browser would block every script/style on
cached pages.

## 5. Sessions & cookies

`.env.production`:

```
SESSION_DRIVER=database        # or redis
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true     # HTTPS-only
SESSION_SAME_SITE=lax
```

Local development may keep `SESSION_SECURE_COOKIE=false`. Tests pin
`SESSION_ENCRYPT=false` in `phpunit.xml` for deterministic cookie assertions.

## 6. Permissions and secrets

VPS/Plesk retains `.env`, storage and uploads under `shared`, adopting legacy locations through links. Its CLI runs with the SSH user's UID/GID, compatible with the existing PHP-FPM owner. The optional Docker production entrypoint sets runtime permissions and persists storage in a volume. Both modes keep APP_KEY stable and Passport private/public keys at 0600/0644. Local development uses official `./vendor/bin/sail`; its container lifecycle is separate from these VPS permission rules.

## 7. Rate limiting

Public (unauthenticated) POSTs — form submissions, comments, shop
checkout/inquiry/cart and the geo preference cookie — are throttled per IP by
`ThrottlePublicWrites` (`cms.public_writes_per_minute`, default **30/min**).
Backoffice, installer, OAuth2 and MCP endpoints are excluded.

```
CMS_PUBLIC_WRITES_PER_MINUTE=30
```

## 8. Development helpers (never enable in production)

```
CMS_QUICK_LOGIN=false   # 1-click persona switcher; ignored outside local/testing
```

## 8.1. Public form anti-spam

Public forms are protected by a honeypot plus, when the **Captcha** module is
active, a `captcha` field the administrator adds in the form builder. There is
no global switch: if a form declares the field, the challenge is rendered and
verified; if the module is deactivated, the field is ignored everywhere. The
provider is chosen per installation in the module settings (`internal`,
`recaptcha`, `turnstile` or `both`), as described in
[`21_FORM_RENDERING_AND_FIELD_TYPES.md`](21_FORM_RENDERING_AND_FIELD_TYPES.md).

## 9. VPS/Plesk deploy checklist

1. Reusable container quality gate passes: Composer audit/validate, Pint, PHPStan level 8, serial Pest, Vite and production image build.
2. Persist a private `.env` with APP_KEY and unique database credentials; back up database, uploads and Passport keys.
3. Transfer the built package and pinned CLI image; preserve releases/current/shared and run migrations once in Docker CLI.
4. Reload existing PHP-FPM, require HTTP 200 from `/up`; smoke-test home, a catalogue item and backoffice.
5. Keep additive migrations compatible with the previous release. Symlink rollback does not roll back data. Docker web hosting remains an explicit alternative.

See [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) for executable Docker commands and SSH configuration.

### Checkout concurrency acceptance

The serial Pest gate includes `tests/Concurrency/ShopStockConcurrencyTest.php`.
It uses two Laravel processes and independent MySQL connections against committed
fixtures, then verifies that both actual checkout queries wait on the same row
before releasing it. One order succeeds, the competing order is rejected and
stock remains zero. This suite refuses any database other than MySQL `testing`,
uses no `RefreshDatabase`, and cleans only its own fixtures in `finally`.

To run separately after the test schema has been migrated, with exclusive access
to the testing database:

```bash
./vendor/bin/sail pest tests/Concurrency
```

Do not run it alongside another suite that migrates or resets the same database.
