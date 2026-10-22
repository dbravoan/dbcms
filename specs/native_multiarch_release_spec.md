# Native multi-architecture publication

Status: current v3 delivery contract.

## Objective and boundaries

Publish native AMD64 and ARM64 images independently of web deployment and ZIP/TAR
publication. The [v3 delivery contract](V3_DELIVERY.md) governs mandatory local
quality evidence and exact-commit verification. No CMS API or data changes.

## Build and promotion

- Use native `ubuntu-24.04` for `linux/amd64` and `ubuntu-24.04-arm` for `linux/arm64`; no QEMU.
- Verify requested/tag version against `VERSION`, exact source SHA, local signed release evidence and successful main CI before builds.
- Build each architecture once, in parallel, with matching cache read/write scope.
- Run the newly built immutable image on its architecture and verify application/bootstrap runtime before exporting its digest artifact.
- Only after both smoke checks pass, assemble from immutable digests, verify both architectures and promote version/latest aliases.
- Canonical image names use `ghcr.io/${{ github.repository }}`. This workflow is
  restricted to `dbravoan/dbcms` and is removed from derived profiles; a derived
  Docker project publishes through its own `bin/deploy-docker` registry wrapper.
- A failed image build reports its own failure and leaves web delivery independent. Stable release files are never rebuilt or replaced by this workflow.

## Verification

Check native runner/platform selection, coherent caches, digest-only staging,
failed-smoke rejection, exact-commit evidence and both architectures before tag
promotion. Measure architecture builds separately from web delivery.
