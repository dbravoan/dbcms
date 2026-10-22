# dbCMS agent entry point

Follow [AGENTS.md](../AGENTS.md),
[Core and extensions](../specs/CORE_AND_EXTENSIONS.md), the area specification and
[TESTING](../docs/TESTING.md). These are the shared sources for all coding agents.

- Keep PHP/Composer/Artisan/Pest in Sail or an existing CI/bootstrap container.
- Preserve current files, keys, data and runtime locks; installation/update tests
  require disposable filesystems and databases.
- Keep decisions in their owner and controllers as transport adapters. Add types
  and interfaces for useful boundaries, not to satisfy a pattern or line quota.
- Public discovery is explicit and enforces publication, market, locale and
  availability. Administrative/private entities do not enter the GEO corpus.
- Read only relevant guides and report measured checks and limits. Framework
  support checks and dependency audits are separate; neither runs merely because
  an agent starts.
