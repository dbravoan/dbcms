<?php

declare(strict_types=1);

use App\Services\Updates\MysqlUpdateBackupService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->backupTestRoot = storage_path('framework/testing/update-backup-'.bin2hex(random_bytes(8)));
    File::ensureDirectoryExists($this->backupTestRoot.'/site/storage', 0700);
    File::ensureDirectoryExists($this->backupTestRoot.'/run/backup', 0700);
    file_put_contents($this->backupTestRoot.'/site/.env', "APP_KEY=fixture-only\n");
    file_put_contents($this->backupTestRoot.'/site/storage/oauth-private.key', 'fixture-private-key');
    config(['updates.root_path' => $this->backupTestRoot.'/site']);
    $this->originalBackupConnection = config('database.default');
    $this->backupTestDatabase = null;
});

afterEach(function (): void {
    config(['database.default' => $this->originalBackupConnection]);
    if ($this->backupTestDatabase !== null) {
        DB::connection('update_backup_admin')->statement('DROP DATABASE `'.$this->backupTestDatabase.'`');
    }
    DB::purge('update_backup_fixture');
    DB::purge('update_backup_admin');
    File::deleteDirectory($this->backupTestRoot);
});

it('restores and compares a real consistent MySQL backup before accepting it', function (): void {
    $configuration = config('database.connections.mysql');
    $configuration['username'] = env('UPDATE_TEST_DB_ADMIN_USER', 'root');
    $configuration['password'] = env('UPDATE_TEST_DB_ADMIN_PASSWORD', 'ci-only-password');
    config(['database.connections.update_backup_admin' => $configuration]);
    $admin = DB::connection('update_backup_admin');
    $this->backupTestDatabase = 'update_fixture_'.bin2hex(random_bytes(8));
    $admin->statement('CREATE DATABASE `'.$this->backupTestDatabase.'` CHARACTER SET utf8mb4');
    $configuration['database'] = $this->backupTestDatabase;
    config(['database.connections.update_backup_fixture' => $configuration, 'database.default' => 'update_backup_fixture']);
    $fixture = DB::connection('update_backup_fixture');
    $fixture->statement('CREATE TABLE documents (id BIGINT PRIMARY KEY, body LONGTEXT NULL, binary_data LONGBLOB, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
    $fixture->table('documents')->insert([
        ['id' => 1, 'body' => 'Español, café y normativa europea', 'binary_data' => "\x00\xff\x01", 'created_at' => '2026-10-01 12:00:00'],
        ['id' => 2, 'body' => null, 'binary_data' => '', 'created_at' => '2026-10-01 12:00:01'],
    ]);
    $scratchBefore = $admin->select("SHOW DATABASES LIKE 'dbcms_restore_%'");
    $result = app(MysqlUpdateBackupService::class)->create($this->backupTestRoot.'/run/backup');
    expect($result['database']['restoration_verified'])->toBeTrue()
        ->and($result['database']['tables'])->toBe(1)
        ->and(hash_file('sha256', $this->backupTestRoot.'/run/backup/database.ndjson'))->toBe($result['database']['sha256'])
        ->and(file_get_contents($this->backupTestRoot.'/run/backup/environment.backup'))->toBe("APP_KEY=fixture-only\n")
        ->and(file_get_contents($this->backupTestRoot.'/run/backup/oauth-private.key'))->toBe('fixture-private-key')
        ->and($admin->select("SHOW DATABASES LIKE 'dbcms_restore_%'"))->toEqual($scratchBefore)
        ->and($fixture->table('documents')->count())->toBe(2);
});

it('requires restoration evidence instead of an external backup checkbox', function (): void {
    expect(fn () => app(MysqlUpdateBackupService::class)->create($this->backupTestRoot.'/run/backup', ['verification' => ['accepted' => true]]))
        ->toThrow(RuntimeException::class, 'proveedor');
    expect(file_exists($this->backupTestRoot.'/run/backup/backup.json'))->toBeFalse();
});

it('records a private restoration report and rejects an altered report', function (): void {
    File::ensureDirectoryExists($this->backupTestRoot.'/run/evidence', 0700);
    $report = $this->backupTestRoot.'/run/evidence/restore.txt';
    file_put_contents($report, 'Fixture provider: backup restored to an isolated database and checked.');
    $evidence = ['provider' => 'Hosting fixture', 'reference' => 'snapshot-0123', 'created_at' => gmdate(DATE_ATOM, time() - 120), 'restored_at' => gmdate(DATE_ATOM, time() - 60), 'verification' => ['artifact_path' => $report, 'sha256' => hash_file('sha256', $report)]];
    $service = app(MysqlUpdateBackupService::class);
    $result = $service->create($this->backupTestRoot.'/run/backup', $evidence);
    expect($result['database']['verification'])->toBe('human_attested_restore_report')
        ->and($result['database']['report_sha256'])->toBe(hash_file('sha256', $report));
    file_put_contents($report, 'altered');
    expect(fn () => $service->create($this->backupTestRoot.'/run/backup', $evidence))->toThrow(RuntimeException::class, 'informe privado');
});
