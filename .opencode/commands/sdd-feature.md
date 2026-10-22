# Command: /sdd-feature

Instruction recipe for feature work, not an executable CMS command. Follow
[Core and extensions](../../specs/CORE_AND_EXTENSIONS.md) and
[TESTING](../../docs/TESTING.md):
1. Specify observable behavior, owner, affected contracts and acceptance scenarios.
2. Reproduce relevant failures with the smallest useful isolated tests.
3. Implement in the owning core area or optional module. Add DTOs/interfaces only
   for meaningful boundaries; use Sail or the existing CI container for PHP.
4. Check public discovery only when affected; private entities remain private.
5. Verify current authorization and required lanes, then report actual results.
Independent subsystem work may be delegated with explicit file ownership; this
recipe does not require a fixed team or centralize all domain services in core.
