# Anti-Patterns to Avoid in dbCMS

## 1. Eloquent/SQL Inline in Blade Views
❌ **FORBIDDEN**:
```blade
@php
    $activeModules = \App\Models\CmsModule::where('is_active', true)->pluck('identifier')->toArray();
    $pendingAuditCount = \App\Models\ContentAudit::where('status', 'pending')->count();
@endphp
```
✅ **CANONICAL SOLUTION**:
Use a View Composer (`AdminLayoutComposer`) or pass data explicitly from the Controller. Blade should only contain directives (`@foreach`, `@if`, `@isset`) rendering already prepared view models or DTOs.

---

## 2. Hardcoded Module Conditionals in Core Controllers
❌ **FORBIDDEN**:
```php
if ($identifier === 'maintenance') {
    return view('admin.maintenance.index');
}
```
✅ **CANONICAL SOLUTION**:
Convention over configuration: Check `view()->exists("{$identifier}::admin.settings")` and render the module's registered view, otherwise fall back to dynamic schema generation.

---

## 3. Monolithic Controllers & Fat Actions
❌ **FORBIDDEN**:
```php
class AdminController extends Controller {
    public function showSettings() { ... }
    public function updateSettings() { ... }
    public function generateContent() { /* 150 lines of API calls & prompts */ }
}
```
✅ **CANONICAL SOLUTION**:
Single Responsibility Principle: Split into invokable verb controllers (`Settings\GetController`, `Settings\PutController`, `Generate\PostController`). Delegate prompts and API interactions to dedicated Domain Services.

---

## 4. Bypassing Module Enabled Status
❌ **FORBIDDEN**:
Assuming a module is active just because its folder exists in `modules/`, or hardcoding an array of "always active" modules in views.
✅ **CANONICAL SOLUTION**:
Always query `app(ModuleManager::class)->isModuleEnabled($identifier)` which checks both DB `is_active` state and configuration `is_enabled`.

---

## 5. Host Runtime Execution
❌ **FORBIDDEN**:
Running `php artisan migrate` or `composer update` on the host machine.
✅ **CANONICAL SOLUTION**:
Always run containerized commands: `./vendor/bin/sail artisan migrate`.
