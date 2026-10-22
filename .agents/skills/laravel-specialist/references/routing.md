# Routing and transport boundaries in dbCMS

Follow [Core and extensions](../../../../specs/CORE_AND_EXTENSIONS.md),
[Passport](../../../../docs/07_PASSPORT_OAUTH2_SPECIFICATION.md) and
[TESTING](../../../../docs/TESTING.md). The runtime uses Laravel 13/Passport;
generic Sanctum routes and `app/Http/Kernel.php` registration are not this tree.

## Route ownership and authorization

Core routes are composed from `routes/` and `bootstrap/app.php`. Pages owns
`app/Content/Pages/Routes/admin.php`; optional modules register their own routes
through `BaseModuleServiceProvider`. Keep the provider at its module root so
relative route discovery resolves correctly. Administrative paths use
`config('cms.admin_path')`; do not hardcode `/admin` in new integrations.

For an actual declaration, Pages wraps its routes in `manage:pages` middleware
and defines `PUT /{page}` with `PagePutController`. The outer admin registration
supplies the configured prefix/session gate. `PagePutController` accepts
`EditorialEntityRequest`, delegates validated data to `PageDomainServiceInterface`
and redirects; it does not perform model persistence itself.

Route-model binding resolves a resource; it does not grant authority. Scoped
binding constrains parent-child resolution but must be combined with current
actor/resource permissions. API transports add their real OAuth scope and RBAC;
web sessions use their actual permissions without invented scopes.

## Public readers and responses

`PublicContentContextFactory` maps market/locale/slug/query/page into context.
Owned readers return `FrontPageData`; the HTTP adapter produces view/redirect/
not-found responses. Keep catalogue queries, public eligibility, canonical filters
and metadata in those readers. Do not introduce a parallel `/api/posts` contract
by copying a generic tutorial or interpret a successful HTTP response as proof
that a capability succeeded.

Module API routes may have their own version prefixes and result shapes. Read
the exact route/controller/DTO; there is no universal JSON resource envelope or
pagination size for every API. Private domain models must not enter public
resources, search or GEO by default.

## Inspect and cache

After starting Sail, these are inspection commands:

```bash
./vendor/bin/sail artisan route:list
./vendor/bin/sail artisan route:list --name=admin.pages
./vendor/bin/sail artisan route:list --path=api
```

`route:cache` and `route:clear` write runtime cache state. Use a disposable runtime
for validation; the deployment scripts prepare production caches in their own
controlled sequence. Review middleware registration in `bootstrap/app.php` and
current CORS configuration before adding an origin; no Sanctum cookie endpoint
is implied by the project’s Passport setup.
