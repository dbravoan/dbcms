<?php

declare(strict_types=1);

use Tests\Support\ArchitectureBoundaryInspector;

/** @return list<string> */
function boundaryFiles(string $pattern): array
{
    $files = glob(base_path($pattern)) ?: [];
    expect($files, $pattern)->not->toBeEmpty();

    return array_values($files);
}

test('controller AST guard accepts delegation and reports model aliases loops and persistence', function (): void {
    $inspector = new ArchitectureBoundaryInspector;
    $valid = '<?php namespace Example; use App\Contracts\Services\ModuleSettingsAdministrationInterface; final class Controller { public function __construct(private ModuleSettingsAdministrationInterface $service) {} public function __invoke($request) { return $this->service->update($request); } }';
    expect($inspector->controllerViolations($valid))->toBe([]);
    $concrete = str_replace('App\\Contracts\\Services\\ModuleSettingsAdministrationInterface', 'App\\Services\\Seo\\PublicDocumentCache', $valid);
    $concrete = str_replace('ModuleSettingsAdministrationInterface', 'PublicDocumentCache', $concrete);
    expect($inspector->controllerViolations($concrete))->toBe([]);
    $invalid = '<?php namespace Example; use App\Models\User as Person; use Illuminate\Support\Facades\DB as Database; final class Controller { public function __invoke() { foreach ([1] as $value) { Person::query()->update([]); } Database::statement("bad"); } }';
    expect($inspector->controllerViolations($invalid))->not->toBeEmpty();
    expect($inspector->controllerViolations('<?php use App\Models\User; class Controller { public function __invoke($request) { $actor = $request->user(); $actor->save(); } }'))->toContain('persistence_or_external_effect');
});

test('AST guard detects interface based constructor cycles in the owned service graph', function (): void {
    $inspector = new ArchitectureBoundaryInspector;
    expect($inspector->constructorCycles(['<?php interface PortA {} class ServiceA implements PortA { public function __construct(PortB $other) {} }', '<?php interface PortB {} class ServiceB implements PortB { public function __construct(PortA $other) {} }']))->not->toBeEmpty();
    $paths = [...glob(base_path('app/Services/Install/Installation*Service.php')), base_path('app/Services/Cms/ModuleNotificationService.php'), base_path('app/Services/Cms/NotificationRecipientResolverService.php'), base_path('app/Services/Cms/ModuleSettingsService.php'), base_path('app/Services/Cms/ModuleSettingsAdministrationService.php'), base_path('app/Core/Modules/ModuleManager.php'), base_path('modules/Maintenance/Services/MaintenanceSettingsAdministrationService.php'), base_path('modules/Maintenance/Services/MaintenanceService.php')];
    expect($inspector->constructorCycles(array_map(fn (string $path): string => (string) file_get_contents($path), $paths)))->toBe([]);
});

test('services AST guard resolves HTTP aliases without requiring forwarding interfaces', function (): void {
    $inspector = new ArchitectureBoundaryInspector;
    expect($inspector->serviceViolations('<?php use Illuminate\Http\Request as Input; class Service { public function run(Input $value) {} }'))->toContain('http_dependency');
    expect($inspector->serviceViolations('<?php class Service {}'))->toBe([]);
});

test('AST guard distinguishes external effects in transactions from deferred effects', function (): void {
    $inspector = new ArchitectureBoundaryInspector;
    $immediate = '<?php use Illuminate\Support\Facades\DB as Database; use Illuminate\Support\Facades\Http as Client; Database::transaction(function () { Client::post("remote"); });';
    $deferred = '<?php use Illuminate\Support\Facades\DB as Database; use Illuminate\Support\Facades\Mail; Database::transaction(function () { Database::afterCommit(function () { Mail::send("message"); }); });';
    expect($inspector->transactionViolations($immediate))->toContain('external_effect_inside_transaction')
        ->and($inspector->transactionViolations($deferred))->toBe([]);
});

test('all extracted HTTP adapters satisfy effective AST boundaries', function (): void {
    $inspector = new ArchitectureBoundaryInspector;
    $paths = [...boundaryFiles('app/Http/Controllers/Seo/*.php'), ...boundaryFiles('app/Http/Controllers/Api/V1/*.php'), ...boundaryFiles('app/Http/Controllers/Install/*.php'), ...boundaryFiles('app/Http/Controllers/Admin/ModuleSettings/*.php'), ...boundaryFiles('modules/Maintenance/Http/Controllers/*.php'), ...boundaryFiles('app/Http/Controllers/Admin/Notifications/*.php'), ...boundaryFiles('modules/Shop/Http/Controllers/Front/*.php'), base_path('app/Http/Controllers/Admin/Modules/ModuleUploadPostController.php'), base_path('app/Http/Controllers/Admin/Menus/MenusGetController.php'), base_path('modules/Forms/Http/Controllers/Admin/FormCreateGetController.php'), base_path('modules/Forms/Http/Controllers/Admin/FormEditGetController.php')];
    foreach ($paths as $path) {
        expect(is_file($path), $path)->toBeTrue();
        expect($inspector->controllerViolations((string) file_get_contents($path)), $path)->toBe([]);
    }
});

test('application services keep transport and remote effects out of transactions', function (): void {
    $inspector = new ArchitectureBoundaryInspector;
    $paths = [...boundaryFiles('app/Services/Install/Installation*Service.php'), base_path('app/Services/Cms/ModulePackageInstallerService.php'), base_path('app/Services/Cms/ModuleNotificationService.php'), base_path('app/Services/Cms/NotificationRecipientResolverService.php'), base_path('app/Services/Cms/ModuleSettingsAdministrationService.php'), base_path('modules/Maintenance/Services/MaintenanceSettingsAdministrationService.php'), base_path('app/Services/Admin/MenuEditorService.php'), base_path('app/Services/Admin/DatatableQueryService.php'), base_path('modules/Forms/Services/FormEditorService.php'), base_path('modules/Shop/Services/CartApplicationService.php'), base_path('modules/Shop/Services/ShopCatalogueService.php'), base_path('modules/Shop/Services/ShopPurchaseWorkflow.php')];
    foreach ($paths as $path) {
        expect(is_file($path), $path)->toBeTrue();
        $source = (string) file_get_contents($path);
        expect($inspector->serviceViolations($source), $path)->toBe([])
            ->and($inspector->transactionViolations($source), $path)->toBe([]);
    }
});
