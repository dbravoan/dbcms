---
name: lts-upgrade-monitor
description: Assess dbCMS framework support and dependency advisories when an upgrade or support review is requested; prepare a scoped upgrade proposal if needed.
---

# Framework support review

The skill identifier is historical. Laravel support assessment uses annual
release windows, not an assumed LTS edition. Read
[the support runbook](../../../docs/08_LTS_UPGRADE_AGENT_SPEC.md).

Use `make support-check` or `./vendor/bin/sail artisan system:check-framework-support`
after starting Sail. The command reports support without writing documentation.
`make support-check` also runs the independent dependency audit, even if the
support command fails. To run only that audit use
`./vendor/bin/sail composer audit --locked`.
Review the current constraint and lockfile against official release information.

Do not install upgrades as a side effect of assessment. For an authorized upgrade,
prepare the dependency changes and verify them in the isolated lanes from
[TESTING](../../../docs/TESTING.md). Report versions, observed results and limits;
historical reports are not current guarantees. Do not schedule monitoring unless
requested.
