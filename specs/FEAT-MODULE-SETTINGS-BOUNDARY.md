# Feature Specification: Module settings domain boundary

> **Spec ID**: `FEAT-MODULE-SETTINGS-BOUNDARY`  
> **Status**: IMPLEMENTED  
> **Date**: 2026-10-12

## Objective
Read and write module settings through typed services rather than controller queries or references to a particular module. Preserve URLs, theme rendering and schema-driven validation.

## Contracts
`ModuleSettingsAdministrationInterface`, immutable update/view DTOs, and `ModuleSettingsExtensionRegistry`. Modules register their own settings view data, custom update handler and optional common mutation normalizer. Core has no Maintenance implementation knowledge.

## Invariants
- Only installed, active and discovered modules expose settings screens; setting `is_enabled` remains editable to re-enable a module.
- Generic update validates schema data before writing. Secret arrays are rejected and blank/masked values preserve existing encrypted credentials.
- `setMany` is serialized with a row lock; cache invalidation and runtime refresh follow commit.
- AI normalization delegates to the existing provider endpoint/modalities policy for HTTP, MCP and direct shared-service callers. An endpoint change requires a genuine explicit replacement credential; a mask or whitespace does not count.
- Maintenance owns its custom validation and synchronization through a registered adapter. Preview reuses the same public renderer with an explicit preview response.

## Verification
- [x] RED common mask, endpoint association, credential array and architecture tests.
- [x] RED whitespace replacement edge case.
- [x] Legacy settings, Maintenance, MCP and provider-contract tests.
- [x] Pint and PHPStan without ignores.

No provider generation or external email is part of validation.
