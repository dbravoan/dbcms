# Feature Specification: Independent derived projects

> Spec ID: derived_projects | Status: IMPLEMENTED

## Domain objective
Create independent Docker/Composer, cloned GitHub Template or extracted source ZIP applications with one administrative brand, fixed selections of public themes, modules and visual packages, signed upstream provenance, reviewed updates from Git source and an explicit choice of GitHub Actions or local rsync/Ansistrano/Docker deployment, without distributable installation archives.

## Guided entry and local setup

The approved [guided bootstrap](FEAT-GUIDED-PROJECT-BOOTSTRAP.md) extends creation
with `make init` or `./bin/project-init` in the source folder; plain `make` shows
help. Composer examples omit the version and resolve its native latest stable.
A compatible published wizard release is required; no development fallback or
release publication is implied by this specification.

Interactive creation suggests the folder slug, dbCMS, db-cyber and all extension
sources, requires explicit deployment and confirms the frozen version/SHA,
composition, source changes and local settings. Resolve latest official stable
once, verify signed source inventory and Git bytes, and stage before mutation.
Preserve Git, private/runtime files and unrelated additions; reject modified
official files, collisions, unsafe paths and unverifiable origins. Cancellation
leaves sources/environment unchanged. There is no overwrite bypass.

After source preparation, install locked dependencies, create `.env` only if
absent with unique Compose identity, available persisted ports and isolated
prefixes, preserve existing environment/keys and generate only a missing APP_KEY.
Start Sail, wait for readiness, build assets and print actual URLs and `/install`.
No migrations, demo, OAuth keys or deployment run during bootstrap. An existing
valid profile resumes local setup with pinned source/composition and never checks
latest. Failures must be resumable without resetting data or reselecting sources.

`--no-interaction` requires explicit name/brand/theme/deployment for new projects;
a valid existing profile may omit them when resuming. CI passes both
`--prepare-only --no-interaction`: only source composition runs, without `.env`
creation or local Sail. Containers use PHP/Composer directly, never nested Sail.

## DTO and manifest contract
`readonly App\DTOs\ProjectProfile` carries name, brand, themes (list of IDs), defaultTheme, coreVersion, coreSha deployment (github-actions|ansistrano|rsync|docker|none) and optional sourceSelection. `cms-project.json` is versioned, contains no secrets, and has this required shape:
```json
{"schema":1,"name":"mi-web","brand":"dbcms","deployment":"github-actions","themes":["db-minimal"],"default_theme":"db-minimal","core":{"repository":"dbravoan/dbcms","version":"3.1.0","sha":"0000000000000000000000000000000000000000"}}
```
The example version and hash illustrate the shape; initialization requires a real published stable release. Production initialization resolves the official release tag through GitHub Git, never a template-generated commit. Explicit noninteractive brands: dbcms|termocms. Explicit noninteractive themes: a valid CSV or all. Required `--deploy`: github-actions|ansistrano|rsync|docker|none, stored as scalar `deployment` in schema 1; existing profiles without this field require a reviewed manual manifest migration; reinitialization does not adopt or infer a deployment choice. CSV is deduplicated preserving order; all resolves the installed catalogue and defaults to db-cyber.

New children record `source_selection` with exactly three keys:
`module_directories` and `block_package_directories` are duplicate-free lists of
safe directory names (`[A-Za-z][A-Za-z0-9_-]*`); `include_showcase` is boolean.
New creation uses `include_showcase: false`. Empty lists are valid. Updates and reconciliation preserve this declaration,
exclude unselected directories under `modules/` and `block-packages/`, and omit
`database/seeders/PlatformShowcaseSeeder.php` when requested. An absent declaration
keeps the full source catalogue; malformed declarations fail before mutation.
This projection describes distributed files, independently of installed/active
module and package state. It is never inferred from missing directories.

Creation accepts `--modules=all|none|CSV` and `--block-packages=all|none|CSV`, both defaulting to `all`. CSV values are manifest IDs, not directory names. Dependencies are resolved transitively and displayed before pruning; `none` is an empty direct selection and does not suppress required dependencies. The manifest records the final exact physical directories. Unknown IDs, missing dependencies and cycles fail before mutation. Repeating initialization with a different resolved selection fails. File composition never installs or activates extensions. See [generic child implementation](FEAT-GENERIC-DERIVED-PROJECTS.md) and the [creation-to-update guide](../docs/DERIVED_PROJECTS_GUIDE.md).

## Preconditions, mutations and postconditions
P: Project slugs start with a lowercase letter and use lowercase alphanumeric segments separated by single hyphens. Validate project slug, complete selection and official stable release provenance before deletion. Reject symlinked catalogue entries and locally edited or added resources that would be excluded; compare exclusions with the official source revision before any project write. Existing matching profile resumes its pinned source/composition; changed profile is rejected.
Q: Source-preparation phase is file-only (no database transaction): preserve selected/shared resources, remove excluded public-theme/admin-brand/module/package directories, remove product archive and initialization workflows, retain only the selected deployment workflow/scripts, write atomic project manifest and update environment examples without touching .env or user data.
R: Manifest records exact upstream version+SHA independently from commercial app version. Runtime config exposes cms.project and uses its brand and its default_theme as a public-theme fallback after the persisted installed choice and installed configured theme. Composer export retains project workflows, tests, Docker and configuration.

## Update and deployment contracts
Update tooling obtains signed official release metadata and Git source, compares official base/project/target and preserves excluded themes/brands and custom modules. GitHub Actions prepares a draft PR and resolves dependency locks when possible; complete quality verification is mandatory locally; local profiles prepare reviewed working-copy changes without requiring an application Git repository. Conflicts are never silently overwritten. GitHub deployment is manual on main and requires the exact successful basic-CI SHA plus its signed local deploy bundle; local deployment validates its working copy and dependency lock through the Docker quality gate. github-actions alone retains official GitHub CI/update/deploy workflows. Local rsync and ansistrano run from the developer machine and do not require GitHub or a local Git repository; local docker deploys through its own wrapper. none retains Docker development/quality tooling without production deployment helpers. Non-GitHub profiles remove official .github files but preserve user-owned custom files; all profiles retain local Docker quality gates. Installation retains web wizard and disables demo. Update reconciliation follows successful deployment. No HTTP API or OAuth scope additions; no GEO content changes.

## Acceptance matrix
- Both brands × all five deployment profiles, verifying physical pruning/idempotence and preservation of Docker CI/build tooling; representative complete derived suites. Physical no-demo installs cover all five deployment profiles and both brands. Single/CSV/all themes; stable order; invalid/mixed/unknown selections rejected without deletion.
- Default all, none and CSV extension selections, dependency closure and exact source_selection projection; no showcase seeder in new children; old absent selection retains the full catalogue.
- Same initialization repeats safely; incompatible initialization fails; official source hash does not equal unrelated template HEAD.
- .env, uploads, shared components and custom modules survive initialization.
- Selected themes switch with production caches, without rebuild; excluded CSS absent.
- Three-way update handles upstream-only, project-only, conflicts, deletes, dependency locks and exclusions.
- Initial/update/recovery deployments preserve secrets and data and never generate installer archives.
- All three source entries reach `/install`; cancellation, latest-stable verification failure and modified official source leave sources/environment unchanged.
- Frozen reruns resume failures without source reselection; existing keys/environment survive and two concurrent projects use isolated services and available ports.
- Source-only CI passes both preparation/noninteraction flags and never invokes nested Sail.
- Pest, PHPStan, Pint, build and dependency audits run in Docker.
