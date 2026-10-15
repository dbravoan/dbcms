<?php

declare(strict_types=1);

use App\Models\CmsModule;
use App\Services\Updates\UpdateMigrationService;
use Illuminate\Database\Migrations\MigrationRepositoryInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->migrationRoot = sys_get_temp_dir().'/update-module-migrations-'.bin2hex(random_bytes(8));
    mkdir($this->migrationRoot, 0700, true);
    config(['updates.root_path' => $this->migrationRoot]);
    $repository = Mockery::mock(MigrationRepositoryInterface::class);
    $repository->shouldReceive('getRan')->andReturn([]);
    $migrator = Mockery::mock(Migrator::class);
    $migrator->shouldReceive('getRepository')->andReturn($repository);
    $this->migrationService = new UpdateMigrationService($migrator);
    $this->moduleManifest = ['migrations' => ['core' => [], 'modules' => []], 'distributions' => ['runtime' => ['files' => []]]];
    $this->addMigrationModule = function (string $identifier, string $folder, bool $installed, bool $active = true): string {
        CmsModule::query()->updateOrCreate(['identifier' => $identifier], ['name' => $identifier, 'version' => '1.2.0', 'provider' => 'FixtureProvider', 'is_installed' => $installed, 'is_active' => $active]);
        $path = 'modules/'.$folder.'/Database/Migrations/2026_10_15_000001_'.str_replace('-', '_', $identifier).'.php';
        mkdir(dirname($this->migrationRoot.'/'.$path), 0700, true);
        file_put_contents($this->migrationRoot.'/'.$path, '<?php // signed migration');
        $metadata = 'modules/'.$folder.'/module.json';
        file_put_contents($this->migrationRoot.'/'.$metadata, json_encode(['identifier' => $identifier], JSON_THROW_ON_ERROR));
        $this->moduleManifest['migrations']['modules'][$identifier] = [$path];
        foreach ([$metadata, $path] as $file) {
            $this->moduleManifest['distributions']['runtime']['files'][$file] = hash_file('sha256', $this->migrationRoot.'/'.$file);
        }

        return $path;
    };
});

afterEach(function (): void {
    File::deleteDirectory($this->migrationRoot);
});

it('includes newly discovered installed and inactive modules but leaves uninstalled modules untouched', function (): void {
    $new = ($this->addMigrationModule)('new-campaign', 'NewCampaign', true);
    $inactive = ($this->addMigrationModule)('inactive-campaign', 'InactiveCampaign', true, false);
    ($this->addMigrationModule)('removed-campaign', 'RemovedCampaign', false, false);
    expect($this->migrationService->pending($this->moduleManifest))->toBe([$this->migrationRoot.'/'.$new, $this->migrationRoot.'/'.$inactive]);
});

it('does not run migrations attributed to an installed module from an uninstalled module folder', function (): void {
    ($this->addMigrationModule)('installed-campaign', 'InstalledCampaign', true);
    $uninstalled = ($this->addMigrationModule)('removed-campaign', 'RemovedCampaign', false);
    $this->moduleManifest['migrations']['modules']['installed-campaign'] = [$uninstalled];
    expect(fn () => $this->migrationService->pending($this->moduleManifest))->toThrow(RuntimeException::class);
});

it('requires module ownership metadata to match the signed release inventory', function (): void {
    ($this->addMigrationModule)('installed-campaign', 'InstalledCampaign', true);
    file_put_contents($this->migrationRoot.'/modules/InstalledCampaign/module.json', '{"identifier":"other-campaign"}');
    expect(fn () => $this->migrationService->pending($this->moduleManifest))->toThrow(RuntimeException::class);
});
