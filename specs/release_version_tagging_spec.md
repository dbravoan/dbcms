# Feature Specification: Explicit release version tagging

> **Spec ID**: `release_version_tagging`  
> **Status**: IMPLEMENTED  
> **Author**: SpecArchitect  

## 1. Domain Objective & Context

Automatic tagging from `app:version` can tag the commit preceding the version change. Preserve the existing inspection, `--bump` and `--set` commands, but reject deprecated `--tag` before any mutation. The v3 line started at `3.0.1`; the current release is `3.1.0`; its annotated `v3.0.1` tag is created explicitly only after committing and validating the final source.

## 2. Algebraic Data Types & DTO Contracts

Existing console options remain the interface. No new DTO, persisted schema or domain service is required. `--tag` remains recognized for compatibility and returns an explanatory failure containing `--tag is deprecated`.

## 3. Formal Invariants

- **Preconditions**: The command receives any options, including `--tag` alone or combined with `--bump` or `--set`.
- **Processing**: Reject `--tag` before version updates and before Git commands that mutate references. Without `--tag`, preserve current version inspection and SemVer validation/update behavior.
- **Postconditions**: Rejected tagging returns exit code `1`; `VERSION`, `.env` and every Git tag remain byte-for-byte/reference-for-reference unchanged. Without tagging, successful bump/set continues synchronizing `VERSION` and existing `APP_VERSION`.

## 4. REST API & Passport OAuth2 Contract

Console-only change; no HTTP endpoints, OAuth scopes or CMS public API changes. Release procedure: update version, commit all intended changes, verify the committed source, then explicitly create the annotated tag on that commit. Push only the intended branch and specific release tag.

## 5. Generative & Traditional SEO Specification

No changes to public pages, JSON-LD, sitemaps or GEO surfaces. Documentation must describe the explicit version/commit/validation/tag order and avoid claims of guaranteed uninterrupted deployment.

## 6. Test Invariant Checklist (TDD)

- [x] Existing current-version, patch/minor/major bump, explicit version and invalid input tests remain green.
- [x] `--tag`, `--bump=patch --tag` and `--set=2.0.0 --tag` each return failure with the explanatory deprecation message.
- [x] All three cases preserve private fixture `VERSION`, `.env` and real Git references, including an existing tag.
- [x] Fixtures use an isolated temporary repository and restore application base/environment paths and working directory; the source repository and environment are never mutated.
- [x] Run the regression first in Docker to establish RED, then implement the command and verify GREEN.
