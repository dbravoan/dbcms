# Feature Specification: Native multi-architecture release

> **Spec ID**: `native_multiarch_release`  
> **Status**: APPROVED  
> **Author**: SpecArchitect  
> **Date**: 2026-10-13

## 1. Domain Objective & Context

The initial `v1.0.0` Docker publication timed out during ARM64 emulation. Replace the emulated combined build with native AMD64 and ARM64 jobs, preserving version `1.0.0`, the project's GHCR namespace, archives and existing template behavior. GitHub supports the selected native ARM64 standard runner for private repositories ([official announcement](https://github.blog/changelog/2026-01-29-arm64-standard-runners-are-now-available-in-private-repositories/)).

## 2. Algebraic Data Types & DTO Contracts

No application DTO or API changes. `docker-publish` uses an include matrix with `amd64/linux/amd64/ubuntu-24.04` and `arm64/linux/arm64/ubuntu-24.04-arm`; its runner resolves from `matrix.runner`. Each build exports an immutable registry digest and a separate digest artifact identified by architecture. No version/latest image tags are published by these individual jobs.

## 3. Formal Invariants

- **Preconditions**: The common quality gate validates the requested/tag version against `VERSION` and succeeds before image or archive publication.
- **Processing**: Build natively without QEMU; run the newly built immutable image on the same architecture, checking its application/bootstrap runtime before uploading its digest artifact. Failed smoke checks do not publish digest artifacts.
- **Postconditions**: `docker-merge` depends on both matrix builds, downloads their artifacts, creates the release manifest from digests and verifies that both `linux/amd64` and `linux/arm64` are present. GitHub release creation depends on successful archive packaging and manifest verification.

## 4. REST API & Passport OAuth2 Contract

No CMS interface, OAuth, data or deployment behavior changes. Artifacts remain in `ghcr.io/${{ github.repository }}` so projects copied from the template publish to their own repositories. The human-authorized unpublished `v1.0.0` tag may be moved to the corrected, validated commit; backups remain local and no unrelated references are overwritten.

## 5. Generative & Traditional SEO Specification

No SEO/GEO output changes. Release documentation describes native architectures and does not imply an application deployment or successful external-service validation.

## 6. Test Invariant Checklist (TDD)

- [x] YAML parsing verifies the two native architecture/runner pairs and absence of QEMU.
- [x] Per-platform builds publish by digest without mutable tags, and smoke validation precedes digest upload.
- [x] Manifest assembly waits for both builds and verifies both architectures before release creation.
- [x] Quality gate remains mandatory and performs version equality validation before application checks.
- [x] Existing template-derived repository publishing regression remains green.
- [ ] Parent records Docker RED before workflow implementation; targeted tests and real GitHub native builds subsequently pass.
