# Command: /lts-check

Instruction recipe for an explicitly requested support review; not a background
watchdog or an installed Artisan command. Follow
[the support runbook](../../docs/08_LTS_UPGRADE_AGENT_SPEC.md):
1. Start Sail and run `make support-check` for the local support table and the
   independent Composer advisory audit.
2. Compare the locked Laravel version with the official annual release policy;
   the historical skill name does not imply an LTS edition.
3. Propose scoped changes. Install upgrades only when authorized and verify in
   disposable filesystem/database lanes from [TESTING](../../docs/TESTING.md).
