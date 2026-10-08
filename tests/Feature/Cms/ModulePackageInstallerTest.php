<?php

declare(strict_types=1);

use App\Core\Modules\ModuleManager;
use App\DTOs\Modules\ModulePackageDTO;
use App\Models\CmsModule;
use App\Services\Cms\ModulePackageInstallerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->packageRoot = storage_path('app/private/package-test-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($this->packageRoot, 0700);
    config(['cms.module_packages.root' => $this->packageRoot.'/modules']);
});

afterEach(function (): void {
    File::deleteDirectory($this->packageRoot);
});

function moduleArchive(string $directory, array $extra = [], ?array $manifest = null): ModulePackageDTO
{
    $path = $directory.'/package.zip';
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('PackageQa/module.json', json_encode($manifest ?? ['identifier' => 'package_qa', 'name' => 'Package QA', 'version' => '1.0.0', 'provider' => 'Modules\\PackageQa\\PackageQaModuleServiceProvider'], JSON_THROW_ON_ERROR));
    $zip->addFromString('PackageQa/PackageQaModuleServiceProvider.php', '<?php namespace Modules\\PackageQa; class PackageQaModuleServiceProvider {}');
    foreach ($extra as $name => $content) {
        $zip->addFromString($name, $content);
    }
    $zip->close();

    return new ModulePackageDTO($path, (int) filesize($path));
}

test('valid module package installs atomically with private staging cleaned', function (): void {
    $result = app(ModulePackageInstallerService::class)->install(moduleArchive($this->packageRoot));
    expect($result->manifest['identifier'])->toBe('package_qa')
        ->and(is_file($result->directory.'/module.json'))->toBeTrue()
        ->and(File::directories($this->packageRoot.'/modules/.package-staging'))->toBe([]);
});

test('module package rejects dangerous paths without installing anything', function (string $name): void {
    expect(fn () => app(ModulePackageInstallerService::class)->install(moduleArchive($this->packageRoot, [$name => 'danger'])))->toThrow(ValidationException::class);
    expect(is_dir($this->packageRoot.'/modules/PackageQa'))->toBeFalse();
})->with(['../escape.php', '/escape.php', 'C:/escape.php', 'PackageQa\\escape.php', 'PackageQa/../escape.php', 'Another/module.json', 'PackageQa/.env', 'PackageQa/x:stream', 'PackageQa/a//b']);

test('module package rejects existing module directory without overwriting code', function (): void {
    File::ensureDirectoryExists($this->packageRoot.'/modules/PackageQa');
    File::put($this->packageRoot.'/modules/PackageQa/module.json', 'original');
    expect(fn () => app(ModulePackageInstallerService::class)->install(moduleArchive($this->packageRoot)))->toThrow(ValidationException::class);
    expect(File::get($this->packageRoot.'/modules/PackageQa/module.json'))->toBe('original');
});

test('module package enforces expanded byte count and ratio limits', function (): void {
    config(['cms.module_packages.max_expanded_bytes' => 512]);
    expect(fn () => app(ModulePackageInstallerService::class)->install(moduleArchive($this->packageRoot, ['PackageQa/large.txt' => str_repeat('a', 1024)])))->toThrow(ValidationException::class);
});

test('module package rejects mismatched provider namespace', function (): void {
    $manifest = ['identifier' => 'package_qa', 'name' => 'QA', 'version' => '1.0.0', 'provider' => 'App\\Providers\\AppServiceProvider'];
    expect(fn () => app(ModulePackageInstallerService::class)->install(moduleArchive($this->packageRoot, manifest: $manifest)))->toThrow(ValidationException::class);
});

test('module package rejects symlink entries', function (): void {
    $package = moduleArchive($this->packageRoot, ['PackageQa/link' => '/etc/passwd']);
    $zip = new ZipArchive;
    $zip->open($package->path);
    $zip->setExternalAttributesName('PackageQa/link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
    $zip->close();
    expect(fn () => app(ModulePackageInstallerService::class)->install(new ModulePackageDTO($package->path, (int) filesize($package->path))))->toThrow(ValidationException::class);
});

test('module package enforces upload, individual file, entry count and compression ratio limits', function (string $limit, int $maximum): void {
    $package = moduleArchive($this->packageRoot, ['PackageQa/large.txt' => str_repeat('a', 10000)]);
    config(['cms.module_packages.'.$limit => $maximum]);
    expect(fn () => app(ModulePackageInstallerService::class)->install($package))->toThrow(ValidationException::class);
    expect(is_dir($this->packageRoot.'/modules/PackageQa'))->toBeFalse();
})->with([['max_upload_bytes', 10], ['max_file_bytes', 1000], ['max_entries', 2], ['max_compression_ratio', 2]]);

test('module package rejects duplicate case paths and file directory collisions', function (array $entries): void {
    expect(fn () => app(ModulePackageInstallerService::class)->install(moduleArchive($this->packageRoot, $entries)))->toThrow(ValidationException::class);
})->with([
    [['PackageQa/readme.txt' => 'one', 'PackageQa/README.txt' => 'two']],
    [['PackageQa/file' => 'file', 'PackageQa/file/nested.php' => 'nested']],
]);

test('module package rejects an existing identifier in another folder', function (): void {
    CmsModule::create(['identifier' => 'package_qa', 'name' => 'Previous', 'provider' => 'Modules\\Existing\\ExistingModuleServiceProvider', 'version' => '1.0.0', 'is_installed' => false, 'is_active' => false]);
    expect(fn () => app(ModulePackageInstallerService::class)->install(moduleArchive($this->packageRoot)))->toThrow(ValidationException::class);
});

test('module manager registers packages inactive and rolls back newly installed code on database failure', function (): void {
    $package = moduleArchive($this->packageRoot);
    $file = new UploadedFile($package->path, 'package.zip', 'application/zip', null, true);
    app(ModuleManager::class)->uploadPackage($file);
    $record = CmsModule::where('identifier', 'package_qa')->firstOrFail();
    expect($record->is_active)->toBeFalse()->and($record->is_installed)->toBeFalse();
    $record->delete();
    File::deleteDirectory($this->packageRoot.'/modules/PackageQa');
    CmsModule::creating(fn () => throw new RuntimeException('Registration failed'));
    try {
        expect(fn () => app(ModuleManager::class)->uploadPackage($file))->toThrow(RuntimeException::class, 'Registration failed');
        expect(is_dir($this->packageRoot.'/modules/PackageQa'))->toBeFalse();
    } finally {
        CmsModule::flushEventListeners();
    }
});
