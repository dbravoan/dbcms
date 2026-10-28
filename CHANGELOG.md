# Changelog

## [3.1.0] - 2026-10-28

Compatible update from the published 3.0.1 baseline.

- ContentAudit changelog projects revision metadata instead of loading snapshot and diff payloads; the new created_at index keeps the global feed within MySQL sort limits.
- ContentAudit review detail keeps the previous snapshot available for diffs without loading it in list queries.
- Block editor schemas now declare media fields, labels, hints, defaults, conditional visibility and complete repeater child controls.
- Image and video payload fields, nested repeater media and appearance backgrounds use the media picker with type filtering and clean cancellation.
- Core block forms document and simplify their fields without renaming stored payload keys.
- Release policy recognizes 3.0.1 as a published source origin so derived projects can prepare the reviewed 3.1.0 update.

[3.1.0]: https://github.com/dbravoan/dbcms/releases/tag/v3.1.0

## [3.0.1] - 2026-10-22

Initial release for fresh installations.

- CMS core with pages, languages, markets, publication, media, menus, themes and technical SEO.
- Optional business modules and visual packages with explicit installation, dependency and availability rules.
- Shared editorial permissions and mutation services for web, API, AI and MCP.
- Independent derived projects with selected source composition, signed provenance and reviewed updates.
- Local quality verification and signed distribution artifacts tied to one source commit.
- Prepared GitHub delivery and independent AMD64/ARM64 Docker publication.
- Usage, operation and development guides, including complete module and block tutorials.

Release availability is established by the published repositories. No automatic
migration of pre-existing installations is provided.

[3.0.1]: https://github.com/dbravoan/dbcms/releases/tag/v3.0.1
