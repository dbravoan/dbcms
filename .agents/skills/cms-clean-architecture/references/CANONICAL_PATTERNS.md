# Current implementation references

Examples should follow the actual owner, not introduce a parallel contract.

- `App\Content\Pages\Http\Controllers\Admin\PagePutController` delegates
  validated input through `PageDomainServiceInterface`. It does not persist the
  page directly; the domain uses the common editorial mutation path.
- `App\Core\Capabilities\CapabilityExecutor` is the execution boundary for
  permissions, availability and actions. MCP is a transport over this catalogue;
  do not recreate a separate MCP dispatcher or registry.
- `App\Core\Blocks\BlockEditorCatalogue` prepares active editor definitions
  before Blade. `SectionRendererService` renders through their shared registry.
- `BaseModuleServiceProvider` registers administrative routes relative to the root
  module provider, with authentication and module availability. The module's route
  file adds its own resource permissions.

For a new controller, use a descriptive action/HTTP verb name. Keep validation,
delegation and response visible together; a numeric line limit is not a reason to
add another class. Use a concrete collaborator when substitution is unnecessary.
Consult [conventions](../../../../docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md)
for the current example and [module guide](../../../../docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md)
for scaffolding and integration responsibilities.
