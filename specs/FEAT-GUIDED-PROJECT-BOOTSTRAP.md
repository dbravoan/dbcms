# Guided project bootstrap

Status: implemented. Public availability requires a compatible published release.

## Outcome and ownership

Composer, a cloned GitHub template and an extracted source ZIP share `make init`
(or `./bin/project-init`). Docker supplies PHP/Composer/Node; official Sail owns
local development. The command guides source composition, confirms a reviewable
plan, then starts the local environment and shows `/install`. It never migrates,
installs demo accounts, generates OAuth keys, or publishes/deploys anything.

## Source and composition contract

New projects resolve the latest published stable release once, excluding drafts
and prereleases; freeze version/SHA, verify its signed source inventory and Git
bytes, and stage it before touching project sources. An official development
snapshot may be normalized to that stable source after identifying its official
origin. The signed source descriptor advertises `project_bootstrap: 1`; absence
or another protocol version rejects an incompatible stable release. Honor existing
export rules. Preserve Git metadata, private/runtime files
and unrelated additions; reject modified official files, collisions, unverifiable
origins, unsafe paths and incompatible releases before applying changes. There
is no force-overwrite option or development fallback.

Reuse the existing initialization/selection service and schema-1 project profile.
Interactive defaults: suggested folder slug, dbCMS, db-cyber, all module/package
sources; deployment must be explicitly selected from the existing five choices.
Custom selections show labels and resolved dependencies. Confirm version,
composition, source changes and local settings before applying. Cancellation does
not modify project sources or environment. Noninteractive mode requires explicit
name/brand/theme/deployment for new projects; valid recorded profiles may omit
those arguments when resuming. Extension defaults remain all. CI is source-only.

An existing valid profile pins its recorded source/composition and resumes local
setup without checking latest or turning initialization into an update.

## Local setup

Install locked dependencies. Create `.env` only if absent, with a directory-unique
Compose identity, available persisted host ports, separate session/cache/Redis
prefixes, demo and quick-login disabled. Preserve existing environment values and
keys; generate only a missing APP_KEY. Start Sail, wait for readiness, build assets
and print actual application/tool URLs and stop/restart commands. Restart after a
failed download, dependency installation, port allocation or build without source
reselection or resets. Never nest Sail in CI/bootstrap containers.

## Acceptance

Exercise all three source entries, both brands, five deployments, default/custom
composition, cancellation, invalid/tampered releases, source ownership,
personalization preservation, frozen resumption and two simultaneous local
projects. Physical checks use disposable filesystems and databases and finish the
web installer without demo. Run focused tests, frontend/build/views, Pint,
PHPStan and complete integration/release lanes; distinguish incremental or
partial coverage. Preserve the working installation and all pre-existing changes.

Public documentation must not promise an unavailable release. Composer examples
omit the version and use Docker. If no published stable supports this bootstrap,
fail clearly before source writes. No release publication is authorized here.
