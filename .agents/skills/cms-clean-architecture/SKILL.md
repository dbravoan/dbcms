---
name: cms-clean-architecture
description: Official architecture and clean code guidelines for the CMS project. Enforces SOLID principles, invokable verb controllers (GetController, PostController, PutController, DeleteController), thin controllers, fat domain services, decoupled module lifecycles, and zero-host containerized execution.
---

# CMS Clean Architecture & Conventions Guide

> **Ecosystem**: Laravel 13 / dbCMS Enterprise Multi-Agent Architecture  
> **Paradigm**: SOLID, Clean Architecture, Domain-Driven Modular Decoupling, Less is More  
> **Target Runtimes**: Docker Sail (Zero-Host Execution)

---

## 1. The 10 Architectural Commandments

1. **Zero-Host Runtime Containment**:
   Never run `php`, `composer`, or `artisan` on the host OS. Local tools MUST execute via official `./vendor/bin/sail <command>` after explicit `up -d` (`--build` on first launch). Bootstrap missing vendor through the compatible Docker CI target. Within CI/bootstrap containers run PHP/Composer directly; do not nest Sail.
2. **Descriptive Invokable Verb Controllers**:
   Controllers MUST be single-action invokable classes (`__invoke`) named descriptively with the entity prefix and HTTP verb suffix (e.g., `PagesGetController`, `PageCreateGetController`, `PagePostController`, `PageEditGetController`, `PagePutController`, `PageDeleteController`). Group all actions for an entity directly in a single domain directory without deep nested action folders.
3. **Thin Controllers ($\le 30$ lines)**:
   Controllers only accept requests, validate through FormRequests/DTOs, delegate to a domain service contract, and return a view or JsonResponse. Maximum 10 lines inside `__invoke()`. No raw SQL, no business algorithms.
4. **Domain Services & Useful Contracts**:
   Business logic resides in focused services. Add an interface for an actual substitution/subsystem boundary, not every helper. Use transactions when related writes require atomicity; external effects follow commit.
5. **Typed Boundaries**:
   Use immutable DTOs where they make input/output contracts clearer. Do not create DTOs or forwarding wrappers solely to satisfy a pattern.
6. **Decoupled Module Lifecycle & Visibility**:
   Optional modules own their code in `modules/{Module}`. Discovery reads manifests without database writes; explicit installation reconciles state and runs selected migrations. `ModuleManager::isModuleEnabled($id)` controls availability. Pages is core in `app/Content/Pages`; Charts/BeforeAfter are visual packages. Follow `specs/CORE_AND_EXTENSIONS.md`.
7. **Zero SQL/Eloquent Queries in Blade**:
   Blade templates (`.blade.php`) MUST NEVER execute queries or call `Model::where()`. Navigation and global layouts receive data exclusively via View Composers (`App\Http\ViewComposers\*Composer`) or dedicated navigation services.
8. **Canonical Backoffice UI Design System & Dual Themes**:
   All backoffice views and modules MUST exclusively use the shared `<x-admin.*>` component library (`<x-admin.table>`, `<x-admin.datatable-header>`, `<x-admin.empty-state>`, `<x-admin.form-card>`, `<x-admin.input>`, `<x-admin.modal>`, etc.). All components dynamically adapt to `CMS_BRAND=dbcms` (Dark Cyberpunk) and `CMS_BRAND=termocms` (Clean Medical White) without modifying Blade markup.
9. **Convention Over Configuration for Module Settings & Navigation**:
   No hardcoded module conditionals in core controllers (`if ($identifier === 'foo')`). Dynamic custom modules register routes and settings schemas in `module.json`, and inject backoffice navigation seamlessly via `getAdminNavigation()` (documented in `docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md`).
10. **Strict Static Analysis & Type Safety**:
    Every file must declare `declare(strict_types=1);`. Code must satisfy PHPStan Level 8+ with zero baseline ignores.

---

## 2. Directory & Namespace Conventions

### 2.1. Core Application Structure (`app/`)
```text
app/
├── Contracts/
│   └── Services/          # Central domain service interfaces
├── DTOs/                  # Readonly immutable DTOs
├── Http/
│   ├── Controllers/
│   │   └── Admin/
│   │       ├── Dashboard/
│   │       │   └── DashboardGetController.php
│   │       ├── Pages/
│   │       │   ├── PagesGetController.php          # List collection (index)
│   │       │   ├── PageCreateGetController.php     # Create form view
│   │       │   ├── PagePostController.php          # Store action
│   │       │   ├── PageEditGetController.php       # Edit form view
│   │       │   ├── PagePutController.php           # Update action
│   │       │   └── PageDeleteController.php        # Destroy action
│   │       └── Users/
│   │           ├── UsersGetController.php
│   │           ├── UserPostController.php
│   │           ├── UserRolePostController.php
│   │           └── UserDeleteController.php
│   ├── Middleware/
│   │   └── EnsureModuleEnabled.php
│   └── ViewComposers/
│       └── AdminLayoutComposer.php
├── Models/                # Schema definitions, relations, casts (no business algorithms)
└── Services/              # Domain service implementations
    └── Admin/
        └── AdminNavigationService.php
```

### 2.2. Modular Domain Structure (`modules/{Module}/`)
```text
modules/{ModuleName}/
├── Contracts/             # Module-specific contracts & service interfaces
├── Database/
│   └── Migrations/        # Module table definitions
├── DTOs/                  # Module data transfer objects
├── Http/
│   └── Controllers/
│       ├── Admin/
│       │   ├── WorkbenchGetController.php
│       │   ├── GeneratePostController.php
│       │   ├── TestConnectionPostController.php
│       │   ├── BrandProfilePutController.php
│       │   ├── PresetPostController.php
│       │   └── PreviewPromptPostController.php
│       └── Front/
│           └── IndexGetController.php
├── Models/                # Module Eloquent models
├── Resources/
│   └── views/
│       ├── admin/
│       │   ├── settings.blade.php  # Auto-discovered as {module}::admin.settings
│       │   └── workbench.blade.php
│       └── front/
├── Routes/
│   ├── admin.php          # or web.php with route prefixes
│   └── api.php
├── Services/              # Fat domain services
├── {ModuleName}ModuleServiceProvider.php
└── module.json            # Manifest & settings schema
```

---

## 3. SOLID Principles Applied to the CMS

| Principle | Application in this CMS | Concrete Rule |
| :--- | :--- | :--- |
| **S** (Single Responsibility) | Controllers handle only HTTP routing/response. Services handle business logic. Models define schema/relations. View Composers supply Blade data. | Short invokable actions delegate business decisions and transaction boundaries. |
| **O** (Open/Closed) | Extend features via modules and service providers without editing core controllers. | Module settings load dynamically via `{$identifier}::admin.settings`. |
| **L** (Liskov Substitution) | Implementations preserve the behavior promised by their contract. | `EditorialWorkflowInterface` supports the optional workflow and its neutral core implementation. |
| **I** (Interface Segregation) | Keep contracts focused on real consumers. | Entity mutation, revision inspection and editorial workflow have separate boundaries. |
| **D** (Dependency Inversion) | Depend on contracts across subsystem boundaries; inject concrete helpers when substitution is unnecessary. | Do not introduce forwarding interfaces solely for dependency injection. |

---

## 4. Descriptive Invokable Controller Pattern

Controllers are strictly named with their entity prefix and HTTP verb suffix, residing directly in their domain folder:

```php
declare(strict_types=1);

namespace App\Content\Pages\Http\Controllers\Admin;

use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;

final class PagePutController extends Controller
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request, Page $page): RedirectResponse
    {
        $this->service->updatePage($page, $request->validated());

        return redirect()->route('admin.pages.index')->with('success', 'Página actualizada.');
    }
}
```

In route files (`routes/web.php`):
```php
use App\Content\Pages\Http\Controllers\Admin\PagesGetController;
use App\Content\Pages\Http\Controllers\Admin\PageCreateGetController;
use App\Content\Pages\Http\Controllers\Admin\PagePostController;
use App\Content\Pages\Http\Controllers\Admin\PageEditGetController;
use App\Content\Pages\Http\Controllers\Admin\PagePutController;
use App\Content\Pages\Http\Controllers\Admin\PageDeleteController;

Route::prefix('admin/pages')->name('admin.pages.')->group(function (): void {
    Route::get('/', PagesGetController::class)->name('index');
    Route::get('/create', PageCreateGetController::class)->name('create');
    Route::post('/', PagePostController::class)->name('store');
    Route::get('/{page}/edit', PageEditGetController::class)->name('edit');
    Route::put('/{page}', PagePutController::class)->name('update');
    Route::delete('/{page}', PageDeleteController::class)->name('destroy');
});
```

---

## 5. Canonical Backoffice UI Design System (`<x-admin.*>`)

Every admin view (in core or modules) MUST be assembled from the following reusable components:

### 5.1. Listados & Datatables
1. **`<x-admin.datatable-header>`**: Barra superior con título, subtítulo, botón de acción (+ Nuevo), buscador reactivo con debounce y selector de registros por página (`per_page`).
2. **`<x-admin.table>`**: Contenedor de tabla con borde cyber, scroll horizontal responsivo, zebra striping y slots `#header`, `#slot` y `#pagination`.
3. **`<x-admin.sortable-th>`**: Encabezados de columna ordenables con flechas interactivas asc/desc.
4. **`<x-admin.empty-state>`**: Estado vacío estándar con icono temático, título descriptivo, mensaje de ayuda y botón CTA opcional.
5. **`<x-admin.status-badge>`**: Badge tipo pill para estados booleanos (`is_active`, `is_published`).

### 5.2. Formularios & Tarjetas
1. **`<x-admin.form-card>`**: Tarjeta contenedora de secciones de formulario con título, subtítulo, icono temático, badge opcional y slot `#footer` para acciones.
2. **`<x-admin.input>`**: Input para `text`, `email`, `password`, `number`, `url`, `tel` con label, placeholder, ayuda, icono y captura automática de errores de validación (`@error`).
3. **`<x-admin.textarea>`**: Campo multilínea con label, filas configurables y feedback de validación.
4. **`<x-admin.select>`**: Desplegable estilizado con soporte de opciones por array o slot.
5. **`<x-admin.toggle>`**: Switch interactivo en Alpine.js para campos booleanos.
6. **`<x-admin.form-actions>`**: Barra inferior estándar con botones Guardar (con feedback de carga) y Cancelar.

### 5.3. Modales & Diálogos
1. **`<x-admin.modal>`**: Modal accesible, maximizable, con backdrop blur, soporte para tecla Escape y cierre al hacer clic fuera.
2. **`<x-admin.delete-modal>`**: Modal global de confirmación de borrado permanente, disparado mediante el evento `@confirm-delete.window`.
3. **`<x-admin.confirm-modal>`**: Modal de confirmación para acciones genéricas (publicar, duplicar, revocar tokens), disparado mediante `@confirm-action.window`.

---

## 6. Module Lifecycle & Visibility Rules

1. **State Discovery**:
   A module is considered **active and visible** if and only if:
   - The manifest exists and its requirements are available.
   - The `CmsModule` record is installed and active.
   - Its optional manifest `availability_setting` is enabled in `CmsModule.settings`.
   Call `app(ModuleManager::class)->isModuleEnabled($id)` for the unified canonical check.
2. **Navigation Tabs**:
   - Módulos inactivos **no deben** renderizar pestañas de primer nivel, menús laterales ni subenlaces.
   - Si todos los elementos de un grupo están ocultos, el encabezado del grupo (`SECCIONES`, `HERRAMIENTAS`) **no se renderiza**.
3. **Route Protection**:
   Apply middleware `module.enabled:ai_assistant` to module admin and front routes to prevent unauthorized URL access when a module is toggled off.
