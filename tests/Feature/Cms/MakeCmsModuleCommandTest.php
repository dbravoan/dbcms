<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;

afterEach(function (): void {
    foreach (['TestPressRelease', 'TestConferenceTalk'] as $module) {
        $dir = base_path("modules/{$module}");
        if (File::exists($dir)) {
            File::deleteDirectory($dir);
        }
    }
});

/**
 * Assert every generated PHP file is syntactically valid.
 */
function assertGeneratedPhpIsValid(string $directory): void
{
    $files = File::allFiles($directory);

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $process = new Process([PHP_BINARY, '-l', $file->getPathname()]);
        $process->run();

        expect($process->isSuccessful())->toBeTrue(
            $file->getPathname().' is not valid PHP: '.$process->getErrorOutput()
        );
    }
}

test('make:cms-module artisan command scaffolds a decoupled module with clean architecture', function (): void {
    $targetDir = base_path('modules/TestPressRelease');
    if (File::exists($targetDir)) {
        File::deleteDirectory($targetDir);
    }

    $this->artisan('make:cms-module', [
        'name' => 'TestPressRelease',
        '--pageable' => true,
    ])->assertSuccessful();

    expect(File::exists($targetDir.'/module.json'))->toBeTrue();
    expect(File::exists($targetDir.'/TestPressReleaseModuleServiceProvider.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Models/TestPressRelease.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Models/TestPressReleaseTranslation.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Contracts/Services/TestPressReleaseServiceInterface.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Services/TestPressReleaseDomainService.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Http/Controllers/Admin/TestPressReleaseGetController.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Http/Controllers/TestPressReleaseFrontController.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Routes/admin.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Resources/views/admin/index.blade.php'))->toBeTrue();

    // Verify manifest contents
    $manifest = json_decode((string) File::get($targetDir.'/module.json'), true);
    expect($manifest['identifier'])->toBe('test-press-release');
    expect($manifest['is_pageable'])->toBeTrue();
    expect($manifest['provider'])->toBe('Modules\\TestPressRelease\\TestPressReleaseModuleServiceProvider');

    // Without --crud only the index route is generated
    $routes = (string) File::get($targetDir.'/Routes/admin.php');
    expect($routes)->toContain('manage:test-press-release');
    expect($routes)->toContain("->name('index')");
    expect($routes)->not->toContain("->name('create')");

    // Database identifiers use snake_case (never the hyphenated slug)
    $migrationFile = glob($targetDir.'/Database/Migrations/*_tables.php')[0] ?? '';
    $migration = (string) File::get($migrationFile);
    expect($migration)->toContain('market_test_press_release');
    expect($migration)->toContain('test_press_release_translations');
    expect($migration)->toContain("foreignId('market_id')->nullable()");

    // Model is localized + market aware
    $model = (string) File::get($targetDir.'/Models/TestPressRelease.php');
    expect($model)->toContain('public function translation(');
    expect($model)->toContain("belongsToMany(Market::class, 'market_test_press_release')");

    // Listing adopts the Admin UI Kit chips
    $index = (string) File::get($targetDir.'/Resources/views/admin/index.blade.php');
    expect($index)->toContain('x-admin.market-chips');
    expect($index)->toContain('x-admin.locale-chips');

    assertGeneratedPhpIsValid($targetDir);
});

test('make:cms-module --crud scaffolds a fully working translatable CRUD', function (): void {
    $targetDir = base_path('modules/TestConferenceTalk');
    if (File::exists($targetDir)) {
        File::deleteDirectory($targetDir);
    }

    $this->artisan('make:cms-module', [
        'name' => 'TestConferenceTalk',
        '--crud' => true,
    ])->assertSuccessful();

    $controllers = [
        'TestConferenceTalkGetController.php',
        'TestConferenceTalkCreateGetController.php',
        'TestConferenceTalkPostController.php',
        'TestConferenceTalkEditGetController.php',
        'TestConferenceTalkPutController.php',
        'TestConferenceTalkDeleteController.php',
        'Concerns/ValidatesTestConferenceTalkPayload.php',
    ];

    foreach ($controllers as $controller) {
        expect(File::exists($targetDir.'/Http/Controllers/Admin/'.$controller))->toBeTrue();
    }

    expect(File::exists($targetDir.'/Resources/views/admin/_form.blade.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Resources/views/admin/create.blade.php'))->toBeTrue();
    expect(File::exists($targetDir.'/Resources/views/admin/edit.blade.php'))->toBeTrue();

    // Routes expose the full verb set
    $routes = (string) File::get($targetDir.'/Routes/admin.php');
    foreach (['index', 'create', 'store', 'edit', 'update', 'destroy'] as $name) {
        expect($routes)->toContain("->name('{$name}')");
    }

    // Service persists translations and synchronizes markets inside a transaction
    $service = (string) File::get($targetDir.'/Services/TestConferenceTalkDomainService.php');
    expect($service)->toContain('DB::transaction');
    expect($service)->toContain('syncTranslations');
    expect($service)->toContain('syncMarkets');
    expect($service)->toContain('updateOrCreate');

    // Form uses the reusable locale tabs + market picker
    $form = (string) File::get($targetDir.'/Resources/views/admin/_form.blade.php');
    expect($form)->toContain('x-admin.locale-tabs');
    expect($form)->toContain('x-admin.market-picker');
    expect($form)->toContain('translations[');

    assertGeneratedPhpIsValid($targetDir);
});
