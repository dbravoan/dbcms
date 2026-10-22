# Eloquent in dbCMS

Follow [Core and extensions](../../../../specs/CORE_AND_EXTENSIONS.md) and
[Testing](../../../../docs/TESTING.md). Model ownership determines namespaces,
relations, casts and migrations. Generic `App\Models\Post` examples are not the
CMS contract: Blog owns `Modules\Blog\Models\Post`; Pages is core.

## Read the actual model and consumers

`modules/Blog/Models/Post.php` casts publication flags and dates, stores shared
attributes on the post, and uses `PostTranslation` for localized content.
`published()` requires `is_published`, a non-null deadline and a deadline not in
the future. It does not itself check module availability, an active market or
translation eligibility; public readers/discovery apply the remaining predicates.

`MarketScoped` and `Translatable` supply shared relations/scopes. Check each
owner’s unique indexes: translations are not universally keyed by market.
Load only relations required by the presentation and preserve the same public
predicates on nested categories and widgets. Avoid querying from Blade.

For authoritative examples read `modules/Blog/Services/BlogCatalogueReader.php`,
`app/Services/Content/PublicContentDiscovery.php` and
`app/Core/Search/ContextualSearchSource.php`. The contextual source iterates with
`lazyById(200)` and preloads relations. This is not a blanket promise that every
source chunks its query; `AbstractSearchSource` uses `get()`.

## Mutations and atomicity

Use the owner’s domain service and shared `EntityMutationService` for registered
editorial entities. Do not duplicate translations, slug uniqueness, sections,
market sync, revision staging or authorization in a new controller/observer.
`ContentSnapshotService` captures and restores the declared current schema.

Related writes belong inside one domain transaction. Hold `lockForUpdate()`
within that transaction on the relevant rows; issuing the select outside it does
not preserve a lock for subsequent operations. Remote calls stay outside.
Events/queued effects dependent on a committed write dispatch after commit.

Model events are not universal: bulk query updates and quiet saves can skip them.
Follow explicit invalidation and indexing in editorial/lifecycle services. An
observer alone is not proof of atomic final-state projection.

## Verify

Use minimal fixtures for the owner’s state, translation and market relations.
Check authorization, schedule boundaries, rollback and index freshness where
relevant. Keep a real concurrency test for a race; mocks cannot prove row locks.
Run PHP through Sail or an existing disposable CI container. Do not migrate or
reset the working installation to try an example.
