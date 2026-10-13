# Feature Specification: Immutable release recovery and verified fast publishing

> **Spec ID**: `immutable_release_recovery`
> **Status**: APPROVED
> **Author**: `SpecArchitect` Agent
> **Date**: 2026-10-13

---

## 1. Domain Objective & Context

Packagist permanently associated stable version `v1.0.0` with commit `3c8619b05b253f4ac609e54ff9ec73a6d59d42d3`. Restore that immutable identity and publish the corrected source as `v1.0.1`. Future release and deployment workflows reuse the successful Continuous Integration result for the exact source commit instead of executing the full quality gate a second time.

## 2. Workflow Contracts

No application DTO, HTTP API, database or CMS behavior changes.

- **Canonical version**: `VERSION`, the root versions in `package.json` and `package-lock.json`, `config/cms.php`'s default, and README's displayed/current version all equal `1.0.1`.
- **Release verification**: `release.yml` has a dedicated verifier which queries GitHub Actions for a completed, successful `push` run of `ci.yml` on `main` whose `head_sha` equals the release commit. Tag runs use the peeled tag commit. Manual runs require an explicit SHA and apply the same equality check.
- **Release dependency graph**: archive and per-architecture image jobs depend on the verifier. Manifest publication depends on verified image jobs, and GitHub release publication depends on the archive and verified manifest. `release.yml` never calls `quality.yml`.
- **Deployment verification**: `deploy.yml` runs after `Continuous Integration` completes and by manual dispatch. Automatic deployment accepts only a successful CI run caused by `push` on `main`; its checkout/package/deployment SHA is `workflow_run.head_sha`. Manual deployment requires an explicit SHA and verifies a matching successful CI `push` run on `main` before deployment. Deployment additionally requires `VPS_DEPLOY_ENABLED=true`. `deploy.yml` never calls `quality.yml`.

## 3. Formal Invariants (Hoare Logic: $P \{Q\} R$)

- **Preconditions ($P$)**: the candidate SHA belongs to `main`; GitHub Actions reports a completed successful `Continuous Integration` run from `ci.yml`, triggered by `push`, with that exact `head_sha`; the requested release version equals `VERSION`.
- **Processing ($Q$)**: workflows verify immutable evidence through the GitHub API before allowing archive, registry, release or deployment jobs. Native AMD64/ARM64 build and runtime checks remain unchanged after verification.
- **Postconditions ($R$)**: no artifact, image tag, GitHub release or VPS package is produced from an unverified or merely similar commit. Release and deployment do not repeat the container quality gate. Every checkout uses the verified SHA.

For stable tags, `v1.0.0` resolves permanently to `3c8619b05b253f4ac609e54ff9ec73a6d59d42d3`; corrected code is published only as immutable `v1.0.1`. Published stable tags must be protected against updates and deletion.

## 4. Failure Contract

- Missing, pending, failed, cancelled, non-push, non-main or different-SHA CI evidence stops the workflow before builds or publication.
- A manual request without an explicit valid SHA fails validation.
- A successful unrelated run, including a pull request run for the same branch name, grants no authority.
- Deployment stays skipped unless the repository opt-in variable is enabled, even when CI evidence is valid.
- GitHub API errors fail closed; workflows do not infer success from a tag, branch or local checkout alone.

## 5. Release Recovery & Compatibility

Remove the GitHub release assets and GHCR aliases that incorrectly represented `7c79077` as `1.0.0`, restore `v1.0.0` to Packagist's immutable commit, and recreate only its historical source release. Then publish `v1.0.1` from a new validated commit with ZIP, TAR.GZ, checksums and private AMD64/ARM64 images. This changes delivery metadata only and preserves all CMS interfaces and stored data.

## 6. Test Invariant Checklist (TDD)

- [x] Regression: all canonical version surfaces equal `1.0.1`.
- [x] Regression: release has no reusable quality job and verifies an exact successful `ci.yml` push run on `main` through the GitHub API.
- [x] Regression: every release build/publish job is transitively gated by that verifier.
- [x] Regression: deployment triggers from completed Continuous Integration and manual dispatch, never invokes `quality.yml`, and checks out the verified exact SHA.
- [x] Regression: automatic deployment requires successful push CI on `main`; manual deployment requires an exact successful CI match; both require VPS opt-in.
- [x] Existing native architecture, digest, runtime smoke, manifest and derived-template repository tests remain green.
