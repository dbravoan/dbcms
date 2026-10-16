# Feature Specification: Independent derived projects

> Spec ID: derived_projects | Status: IMPLEMENTED | Date: 2026-10-15

## Domain objective
Create independent GitHub Template or Composer applications with one administrative brand, a fixed selection of public themes, signed upstream provenance, reviewed updates from Git source and an explicit choice of GitHub Actions or local rsync/Ansistrano/Docker deployment, without distributable installation archives.

## DTO and manifest contract
`readonly App\DTOs\ProjectProfile` carries name, brand, themes (list of IDs), defaultTheme, coreVersion, coreSha and deployment (github-actions|ansistrano|rsync|docker|none). `cms-project.json` is versioned, contains no secrets, and has this required shape:
```json
{"schema":1,"name":"mi-web","brand":"dbcms","deployment":"github-actions","themes":["db-minimal"],"default_theme":"db-minimal","core":{"repository":"dbravoan/dbcms","version":"1.2.0","sha":"0000000000000000000000000000000000000000"}}
```
The example hash is illustrative only. Production initialization resolves the official release tag through GitHub Git, never a template-generated commit. Required brands: dbcms|termocms. Required themes: a valid CSV or all. Required `--deploy`: github-actions|ansistrano|rsync|docker|none, stored as scalar `deployment` in schema 1; legacy profiles without this field are preserved without inferring a choice. CSV is deduplicated preserving order; all resolves the installed catalogue and defaults to db-cyber.

An opt-in curated export can add `source_selection` with exactly three keys:
`module_directories` and `block_package_directories` are duplicate-free lists of
safe directory names (`[A-Za-z][A-Za-z0-9_-]*`); `include_showcase` is boolean.
Empty lists are valid. Updates and reconciliation preserve this declaration,
exclude unselected directories under `modules/` and `block-packages/`, and omit
`database/seeders/PlatformShowcaseSeeder.php` when requested. An absent declaration
keeps the full source catalogue; malformed declarations fail before mutation.
This projection describes distributed files, independently of installed/active
module and package state. It is never inferred from missing directories.

## Preconditions, mutations and postconditions
P: Validate project slug, complete selection and official stable release provenance before deletion. Reject symlinked catalogue entries. Existing matching profile is idempotent; changed profile is rejected.
Q: File-only operation (no database transaction): preserve selected/shared resources, remove excluded public-theme/admin-brand directories, remove product archive and initialization workflows, retain only the selected deployment workflow/scripts, write atomic project manifest and update environment examples without touching .env or user data.
R: Manifest records exact upstream version+SHA independently from commercial app version. Runtime config exposes cms.project and uses its brand/default_theme as fallback. Composer export retains project workflows, tests, Docker and configuration.

## Update and deployment contracts
Update tooling obtains signed official release metadata and Git source, compares official base/project/target and preserves excluded themes/brands and custom modules. GitHub Actions prepares a human-reviewed PR; local profiles prepare reviewed working-copy changes without requiring an application Git repository. Conflicts are never silently overwritten. GitHub deployment accepts only successful CI SHAs; local deployment validates its working copy and dependency lock through the Docker quality gate. github-actions alone retains official GitHub CI/update/deploy workflows. Local rsync and ansistrano run from the developer machine and do not require GitHub or a local Git repository; local docker deploys through its own wrapper. none retains Docker development/quality tooling without production deployment helpers. Non-GitHub profiles remove official .github files but preserve user-owned custom files; all profiles retain local Docker quality gates. Installation retains web wizard and disables demo. Update reconciliation follows successful deployment. No HTTP API or OAuth scope additions; no GEO content changes.

## Acceptance matrix
- Both brands × all five deployment profiles, verifying physical pruning/idempotence and preservation of Docker CI/build tooling; representative github-actions/docker installation + full derivative suites. Single/CSV/all themes; stable order; invalid/mixed/unknown selections rejected without deletion.
- Same initialization repeats safely; incompatible initialization fails; official source hash does not equal unrelated template HEAD.
- .env, uploads, shared components and custom modules survive initialization.
- Selected themes switch with production caches, without rebuild; excluded CSS absent.
- Three-way update handles upstream-only, project-only, conflicts, deletes, dependency locks and exclusions.
- Initial/update/recovery deployments preserve secrets and data and never generate installer archives.
- Pest, PHPStan, Pint, build and dependency audits run in Docker.

## Local verification (2026-10-15)
- Complete isolated Docker quality gate: 1,803 source tests passed, five opt-in/profile cases skipped; the explicit installation smoke then passed both physically pruned clones (14 assertions), followed by the Apache root/public recovery boundary checks.
- Five deployment modes × both brands: 15 profile/workflow/no-Git checks passed (241 assertions); representative GitHub Actions and local Docker clones also passed their broad derived suites.
- Latest deployment boundary suite: 45 passed (359 assertions), including native/Docker interruption, foreign locks and registry digest guards; runtime key provisioning/concurrency: five passed (18 assertions). Updated native controls were also checked independently after the complete gate snapshot.
- Real ephemeral native target with SSH, rsync, PHP-FPM and Nginx, without Docker installed: first wizard, no-demo installation, rsync and pinned Ansistrano upgrades, deliberate partial DDL failure, closed recovery and supervised restoration passed.
- Real ephemeral Docker registry: image publication and digest deployment, /install and /admin/login HTTP 200, no-demo installation, stable environment/OAuth keys/admin after upgrade, and failed migration blocking application/retry passed. Project source provenance in this deployment fixture was synthetic, not a published release.
- PHPStan including the runtime bootstrap, Pint, frontend tests, dependency audits, workflow lint, selected-theme/catalogue builds and the final production image build passed. Fresh-vendor Docker/Composer bootstrap was separately verified against published 1.2.0 provenance in an isolated fixture.
- No signed 1.3.0 release has been published and no customer VPS was changed. Real deployment fixtures and their databases/volumes/keys were removed after testing.
