<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

$originalInstalledLock = null;

beforeEach(function () use (&$originalInstalledLock): void {
    $this->seed(PlatformShowcaseSeeder::class);
    if (File::exists(storage_path('installed'))) {
        $originalInstalledLock = (string) File::get(storage_path('installed'));
        File::delete(storage_path('installed'));
    }
});

afterEach(function () use (&$originalInstalledLock): void {
    if ($originalInstalledLock !== null) {
        File::put(storage_path('installed'), $originalInstalledLock);
    } else {
        File::put(storage_path('installed'), (string) json_encode([
            'installed_at' => now()->toIso8601String(),
            'admin_email' => 'admin@db-cms.io',
            'admin_path' => 'admin',
            'version' => '1.0.0',
            'platform' => 'dbCMS Enterprise',
        ], JSON_PRETTY_PRINT));
    }
});

test('cli command cms:install executes unattended installation with options', function (): void {
    $exitCode = $this->artisan('cms:install', [
        '--db-host' => env('DB_HOST', 'mysql'),
        '--db-port' => (string) env('DB_PORT', '3306'),
        '--db-database' => 'testing',
        '--db-username' => env('DB_USERNAME', 'sail'),
        '--db-password' => env('DB_PASSWORD', 'secret'),
        '--without-demo' => true,
        '--admin-name' => 'CLI Sysadmin',
        '--admin-email' => 'cli-admin@db-cms.io',
        '--admin-password' => 'secret12345',
        '--admin-path' => 'control',
        '--site-name' => 'dbCMS CLI Platform',
        '--force' => true,
    ])->run();

    expect($exitCode)->toBe(0);
    expect(User::where('email', 'cli-admin@db-cms.io')->exists())->toBeTrue();
    expect(File::exists(storage_path('installed')))->toBeTrue();

    $lockContent = json_decode((string) File::get(storage_path('installed')), true);
    expect($lockContent['admin_email'])->toBe('cli-admin@db-cms.io');
    expect($lockContent['admin_path'])->toBe('control');
});

test('cli command cms:install aborts if already installed without --force', function (): void {
    File::put(storage_path('installed'), json_encode(['installed' => true]));

    $exitCode = $this->artisan('cms:install')->run();

    expect($exitCode)->toBe(1);
});
