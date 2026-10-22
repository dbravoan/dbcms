<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\File;

test('cli command cms:install executes unattended installation with options', function (): void {
    $exitCode = $this->artisan('cms:install', [
        '--db-host' => env('DB_HOST', 'mysql'),
        '--db-port' => (string) env('DB_PORT', '3306'),
        '--db-database' => env('DB_DATABASE'),
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
