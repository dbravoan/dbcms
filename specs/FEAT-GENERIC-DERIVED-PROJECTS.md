# Generic derived projects

Status: current v3 behavior contract. Verification follows [TESTING](../docs/TESTING.md).

## Outcome and ownership

The template distributes reusable CMS capabilities, not a particular downstream
publication. Remove downstream-specific themes, editorial material, provisioning,
exports and release claims, including their references in dated documents. Git
history remains unchanged. Preserve the product showcase and generic behavior
coverage; move public Blog assertions onto retained catalogue themes.

## Creation contract

`bin/project-init` and its Artisan/workflow entry points accept optional
`--modules=all|none|CSV` and `--block-packages=all|none|CSV`, defaulting to `all`.
CSV values are manifest identifiers. Resolve and report compatible transitive
dependencies before any pruning; reject unknown, duplicate-identity, cyclic,
incompatible, malformed or unsafe source trees without mutation.

Record resolved physical directory lists in the existing `source_selection`
contract of `cms-project.json`, with `include_showcase=false` for new children.
`all` freezes the current catalogue, independently of runtime installation or
activation. Matching initialization is idempotent; incompatible reinitialization
fails. Preserve private data and customized resources; excluded official files
must match their official source before removal. Project names start with a
lowercase letter and contain alphanumeric segments separated by single hyphens.

## Updates and quality

Reuse source provenance, three-way updates and the existing selected-theme
retirement guard. A removed upstream selected theme blocks before source writes.
Document how the child first moves its theme, assets and business code into its
own identifiers/paths and updates its profile; do not modify another repository
or add a downstream-specific exception or generic owned-path registry.

Derived quality validates declared source selection, ordinary manifests and
dependencies. Core checks always run; optional tests declare actual module and
package requirements. Unknown tests must not silently disappear. Product release
keeps autonomous distribution checks; derived release uses the child's real
brand, public themes and physical source selection.

## Documentation and acceptance

One documented journey covers sources, selection, initialization, no-demo install,
an independent project theme, verification, deployment and reviewed updates.
Correct incompatible update-version examples, obsolete adoption promises, theme
selection precedence and unsafe test commands. Public themes do not inherit from
one another; administrative brands retain their current supported contract.

Verify core-only, Blog/Basic, dependency closures and the complete catalogue;
both brands and all deployment modes; rejection without writes, idempotence,
personalization preservation and no-demo behavior. Exercise physical installs,
authentication and semantic publication, update exclusions and retired-theme
blocking in disposable code/database runtimes. Run focused and frontend tests,
view/build checks, Pint, level-8 PHPStan and complete integration/release checks.
Report exact results and distinguish partial coverage from a clean full run.
