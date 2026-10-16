# IA en práctica: starter and dbCMS 2.0

Status: implementation requested, 2026-10-16.

## Product

Create a real, independently runnable sibling project `ia-en-practica`, from the
current dbCMS working tree. Keep the existing installation, data, environment and
keys intact. This request authorizes preparing and promoting dbCMS 2.0.0 once
the current release gates and the sibling's real journeys pass.

The starter is opt-in. A normal fresh dbCMS installation remains an empty CMS.
The sibling uses core + Blog and Basic blocks, one administrative brand and one
public theme (`ia-practica`). No AI credentials, remote generation, marketing
automation, shop, analytics or orchestration runtime is required to read or edit
the educational publication. Explain those technologies in editorial content.

## Editorial and design contract

Spanish publication about practical AI adoption, task-level training,
interdepartmental cheatsheets, agentic workflows and desktop software control.
Use a distinctive readable editorial design, mobile navigation, useful internal
links and accessible landmarks. Public content remains editable CMS entities,
not hardcoded articles in Blade. Each practical guide specifies goal, inputs,
steps, example prompt, human verification, deliverable and limits. Cover
marketing, sales, people, operations, finance and technology without inventing
tool capabilities, performance claims, testimonials or legal guarantees.

Include an orientation home, learning path, cheatsheet directory, methodology
and useful articles. Calls to action lead to real learning resources. No fake
newsletter form or fabricated commercial claims. Starter execution rejects an
occupied editorial installation before writing; rerunning must preserve edits.

## Boundaries and evidence

Use existing modules, publication services, sections, localization, menus and
SEO contracts. Optional starter code must not make core depend on Blog at boot.
SEO includes valid canonical URLs, language, metadata, sitemap, robots,
OpenGraph, JSON-LD and readable `/llms.txt` / `/llms-full.txt`. AEO is clear
answers and useful semantic structure; indexing/rankings cannot be guaranteed.
Never mark draft/private content public in discovery.

Tests prove provisioning isolation and real HTTP reading, article links,
administration, publication, disabled module behavior and discovery. Run the
comprehensive release gate in disposable Docker filesystems/databases and
validate a physically minimal sibling, production caches and responsive UI.
Version files must agree on 2.0.0. Upstream release provenance is only official
after a verified committed release/tag; label pre-release sibling provenance
honestly and reconcile it after publication. Do not manufacture a trusted SHA.

The curated export records its physical selection in optional
`cms-project.json.source_selection`: `module_directories` and
`block_package_directories` are lists of regular directory names, and
`include_showcase` is a boolean. Generic profiles without this declaration keep
their complete source catalogue. Update comparison and reconciliation must
preserve this declaration and must never restore excluded module/package source
or the omitted full showcase. Validate the declaration before proposing changes;
do not infer it from current missing files. This is a source projection policy,
separate from the installed/active state owned by module and package lifecycles.

The full showcase is optional code, resolved only when demo is requested through
`cms.demo_seeder`. Missing or invalid demo classes fail before migrations and
foundation writes; a selected project without demo must pass PHPStan without
ignores. Source and selected-project quality lanes remain explicit and isolated.
