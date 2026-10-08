---
name: lts-upgrade-monitor
description: Proactively verifies Laravel LTS releases, security advisories, and generates safe migration proposals.
---

# LTS Upgrade Monitor Skill

## Goal
Ensure the application runs on the latest supported patch of Laravel LTS, without breaking changes or unpatched vulnerabilities.

## Procedure
1. Execute `./bin/sail-run composer outdated --direct` to detect newer package versions.
2. Execute `./bin/sail-run composer audit` to scan for published CVEs.
3. Compare the current `laravel/framework` constraint in `composer.json` against the active LTS release window.
4. If updates are available:
   - Create a structured proposal in `docs/LTS_UPGRADE_PROPOSAL.md`.
   - List breaking change warnings, deprecated methods, and migration steps.
   - Run the test suite `./bin/sail-run pest` in a dry-run test container before recommending the upgrade.
