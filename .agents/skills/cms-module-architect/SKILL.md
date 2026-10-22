---
name: cms-module-architect
description: Design, scaffold and verify optional dbCMS modules with owned contributions, explicit lifecycle and shared editorial contracts.
---

# dbCMS modules

Read [Core and extensions](../../../specs/CORE_AND_EXTENSIONS.md) and the
[module guide](../../../docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md).
Pages is core; visual packages use the block lifecycle, not business modules.

For a new scaffold, start Sail and use `./vendor/bin/sail artisan make:cms-module
Events --pageable --crud` with only the options the feature needs. Inspect current
`stubs/cms-module/` and generated output. The generator provides persistence,
routes and views; it does not automatically supply a complete editorial/MCP
integration or DTOs for every operation.

Keep the provider in the module root so `BaseModuleServiceProvider` finds
`Routes/admin.php`. Declare versions and dependencies in `module.json`.
Discovery does not write state or install packages; explicit installation does.
Register navigation, settings, entity schemas, handlers, blocks and capabilities
through their owners. Never add module-specific conditionals to generic core.

Localizable entities use a shared base row, unique translation per locale and
market associations. Use shared mutation/section/editorial services for declared
entities. Interfaces/DTOs need a real boundary; controllers remain transport
adapters without arbitrary line limits. Related writes use transactions and
remote effects follow commit.

Use semantic `<x-admin.*>` components for both brands and the current
[front theme resolver](../../../docs/17_FRONTOFFICE_THEME_GUIDE.md). Inspect actual
props and resolver behavior instead of inventing a fallback chain. Public content
must explicitly opt in to discovery; private business entities remain private.

Verify real generated behavior in a disposable artifact, then absence/disabled
module behavior, permissions and relevant market/locale/publication invariants.
Use explicit test compositions and [TESTING](../../../docs/TESTING.md). A scaffold
with files on disk is not proof of a working module.
