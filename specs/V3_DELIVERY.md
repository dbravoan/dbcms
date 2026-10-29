# dbCMS v3 delivery

Status: current distribution contract
Owner: core distribution and deployment

## Baseline and scope

The canonical repository is `dbravoan/dbcms`. Version **3.1.1** is the current security patch and updates the published 3.0.1
and 3.1.0 lines. Product metadata and the release policy use 3.1.1,
`supported_from: [3.0.1, 3.1.0]` and `rollback_compatible: false`. There is no automatic
conversion of an existing installation, historical payloads or database schemas.
[Core and extensions](CORE_AND_EXTENSIONS.md) governs application behavior.

Preserve private environments, installed data, keys and runtime locks. Release
publication distributes software; it does not deploy a site or update an
installation automatically.

## Local preparation

`bin/prepare-delivery --kind=release|deploy --sha=<40 hex>` exports the exact
committed source into a disposable Docker filesystem and database. PHP never
runs on the host. Both kinds currently require the full release lane: dependency
audits, Pint, PHPStan, tests, frontend compilation and distribution checks.
Preparation has no skip-quality option. The disposable MySQL datadir uses tmpfs;
Sail and production storage are unaffected.

The preparer builds and smokes the runtime, creates ZIP/TAR distributions and
writes `dist/delivery-<sha>/`. Existing output is not silently overwritten.
No upload occurs by default. `--upload` stages a GitHub draft with `gh`;
`--dispatch` also launches the selected delivery workflow.

`manifest.sig` authenticates the update manifest and its inventories.
`delivery.sig` authenticates the receipt: schema, kind, source SHA, version,
successful quality lane, filenames, byte sizes and SHA256 hashes. Both use
RSA/SHA-256. Canonical releases use the existing trusted signing identity;
private keys remain outside the repository and are never uploaded. The public
key is `resources/updates/trusted-keys/releases-2026.pub`. A derived project owns
its signing key and public `resources/delivery/trusted-key.pub`.

Runtime distributions contain no installed private state. Transfer retries reuse
the signed bytes. Any changed source requires a new commit and preparation.
Store publication summaries outside the signed directory.

## GitHub publication and deployment

A draft named `delivery-<sha>` stages the signed bundle. GitHub verifies its
signatures, hashes, version, exact successful main-push CI and runtime bootstrap.
Archive publication and deployment do not reinstall dependencies, compile assets
or construct ZIP/TAR distributions. Missing or invalid evidence blocks delivery.

Stable publication requires an annotated `v<version>` tag on the prepared commit.
The publisher promotes the verified staging files and marks the release latest.
It refuses an existing stable publication. GitHub and Packagist must resolve a
stable version to the same commit; never move a published stable tag or replace
its bytes. Publish a later version for any change. Package registries, caches and
third-party copies can retain published identities: deleting a repository or
release cannot guarantee erasure outside the controlled account.

Deployment additionally requires the selected profile, explicit authorization,
protected environment, pinned SSH identity, serialization, controlled migrations,
recovery and HTTP health checks. It consumes the prepared runtime. Production
credentials and installed state remain on the destination.

CI performs basic source and delivery-contract checks. Full local gates remain
mandatory. Native AMD64/ARM64 image builds run independently with separate caches
and smoke checks before promoting `3.1.1` and `latest`; archive publication does
not wait for them. Images have their own build and verification evidence.

## Acceptance and measurements

Reject missing or invalid signatures, wrong SHA/kind/version, insufficient or
failed quality evidence, changed bytes and missing or unsafe archives. Accept a
valid bundle. Verify installer bootstrap, runtime extraction, source packaging,
deployment failure handling and serialization. Run release checks on the exact
commit being published.

Record local quality and packaging, GitHub CI, archive publication, transfer,
migration, health and image timings separately. Initial targets are quick CI
below three minutes and deploy runner preparation below two minutes; queue,
server operations and cold/warm caches must be reported separately. Targets are
not guarantees. See [the operating guide](../docs/DEPLOYMENT_AND_VERSIONING.md)
for commands and signature verification.
