# Backoffice coherence and repeatable demonstration

Status: current v3 behavior contract. Verification follows [TESTING](../docs/TESTING.md).

## Outcome

Both administrative brands share navigation, screen structure and editor controls,
while retaining their own typography and palette. Optional modules declare their
navigation through the existing provider contract. Multi-destination modules use
collapsible groups; the active descendant opens its group, independent of siblings.
Single visible destinations stay links. Permission and availability filtering
precede grouping, and empty groups disappear. Group headings are buttons without
destinations. Stable identifiers do not depend on translated labels.

## Seed ownership

Foundation creates no demonstration content or optional modules. Rerunning it
preserves valid market configuration and ensures a usable personal-access client
for the configured provider. Explicit showcase seeding restores only its owned
attributes, translations and relationships, using stable keys and stored record
IDs in technical settings. Foreign natural-key collisions fail before persistent
changes. Passwords, keys and shared permission grants remain intact. Starter
reruns retain their established preservation behavior.

Public demonstration links resolve the actual locale's destinations. Seeded
blocks use current registry payloads, translated references and explicit featured
selection. Missing localized destinations are omitted instead of linked through
another language. Demo login hints appear only with enabled local access and
existing corresponding accounts.

## Shared presentation and cleanup

Use the existing semantic admin components and tokens for lists, forms, settings,
workflow/details and block editors. Keep specialized workflows and public themes
separate from administrative styling. Preserve field names, Alpine bindings,
locale/market isolation, disabled-block placeholders and stored payloads.
Generators and current guides follow the same component contracts.

Remove confirmed unreferenced enums, helpers, unsupported upscaling methods,
obsolete widget manifest entries, the unreachable icon picker and duplicate logo.
Remove the PHP-only layout mutation branch and move its behavior coverage to the
JavaScript used by the editor. Keep live rendering and normalization contracts.

## Acceptance

- Isolated foundation/showcase runs, repeated restoration, collision rollback,
  foreign-record preservation, real token issuance and actual public link/content
  checks; starter preservation and installer boundaries remain covered.
- Navigation tests cover permission filtering, availability, catalog mode,
  parameterized destinations, active descendants and accessible independent groups.
- Render every inventoried administrative surface and block field/editor type in
  both brands. Browser checks cover each screen family and unusual workflow,
  empty/error/disabled states, keyboard and desktop/390px/320px layouts.
- Focused tests, frontend, view compilation, build, Pint, PHPStan, isolated
  integration and release lanes. Report actual measurements and visual coverage.

The working installation, environment, keys and runtime locks are preserved.
No deployment, publishing or external generation is part of this change.
