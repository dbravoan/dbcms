<?php

declare(strict_types=1);

use App\Contracts\Services\ProjectUpdateServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Models\CmsModule;
use App\Models\User;
use App\Services\ProjectUpdates\ProjectMigrationService;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('real project deployment runs installed module migrations without installing optional modules or global paths', function (): void {
    $root = sys_get_temp_dir().'/dbcms-migrate-database-'.bin2hex(random_bytes(8));
    $files = new Filesystem;
    $files->ensureDirectoryExists($root.'/storage');
    touch($root.'/storage/installed');
    file_put_contents($root.'/cms-project.json', '{"schema":1}');
    $fixtures = [
        ['database/migrations/2099_01_01_000001_project_core.php', 'fixture_project_core'],
        ['vendor/laravel/passport/database/migrations/2099_01_01_000002_project_passport.php', 'fixture_project_passport'],
        ['modules/InstalledInactive/Database/Migrations/2099_01_01_000003_project_inactive.php', 'fixture_project_inactive'],
        ['modules/OwnProject/Database/Migrations/2099_01_01_000004_project_custom.php', 'fixture_project_custom'],
        ['modules/Uninstalled/Database/Migrations/2099_01_01_000005_project_optional.php', 'fixture_project_optional'],
        ['globally-registered/2099_01_01_000006_project_global.php', 'fixture_project_global'],
    ];
    $moduleDefinitions = ['InstalledInactive' => ['fixture-project-inactive', true, false], 'OwnProject' => ['fixture-project-custom', true, true], 'Uninstalled' => ['fixture-project-optional', false, false]];
    $template = <<<'MIGRATION'
    <?php
    return new class extends \Illuminate\Database\Migrations\Migration
    {
        public function up(): void
        {
            \Illuminate\Support\Facades\Schema::create('__TABLE__', fn (\Illuminate\Database\Schema\Blueprint $table) => $table->id());
        }
        public function down(): void
        {
            \Illuminate\Support\Facades\Schema::dropIfExists('__TABLE__');
        }
    };
    MIGRATION;
    $userCount = User::query()->count();
    try {
        foreach ($fixtures as [$path, $table]) {
            $files->ensureDirectoryExists(dirname($root.'/'.$path));
            file_put_contents($root.'/'.$path, str_replace('__TABLE__', $table, $template));
        }
        foreach ($moduleDefinitions as $folder => [$identifier, $installed, $active]) {
            file_put_contents($root.'/modules/'.$folder.'/module.json', json_encode(['identifier' => $identifier], JSON_THROW_ON_ERROR));
            CmsModule::query()->create(['identifier' => $identifier, 'name' => $identifier, 'version' => '1.0.0', 'provider' => 'FixtureProvider', 'is_installed' => $installed, 'is_active' => $active]);
        }
        $migrator = app(Migrator::class);
        $migrator->path($root.'/globally-registered');
        $review = Mockery::mock(ProjectUpdateServiceInterface::class);
        $review->shouldNotReceive('review');
        $signedMigrations = Mockery::mock(UpdateMigrationServiceInterface::class);
        $signedMigrations->shouldNotReceive('pending');
        $service = new ProjectMigrationService($migrator, new CmsModule, $review, $signedMigrations, new SignedManifestVerifier);
        $service->migrate($root);

        foreach (array_slice($fixtures, 0, 4) as [, $table]) {
            expect(Schema::hasTable($table))->toBeTrue();
        }
        foreach (array_slice($fixtures, 4) as [, $table]) {
            expect(Schema::hasTable($table))->toBeFalse();
        }
        expect(CmsModule::query()->where('identifier', 'fixture-project-inactive')->firstOrFail()->is_active)->toBeFalse()
            ->and(CmsModule::query()->where('identifier', 'fixture-project-optional')->firstOrFail()->is_installed)->toBeFalse()
            ->and(User::query()->count())->toBe($userCount);
        $ran = $migrator->getRepository()->getRan();
        expect($ran)->toContain('2099_01_01_000001_project_core', '2099_01_01_000002_project_passport', '2099_01_01_000003_project_inactive', '2099_01_01_000004_project_custom')
            ->not->toContain('2099_01_01_000005_project_optional', '2099_01_01_000006_project_global');
    } finally {
        foreach ($fixtures as [, $table]) {
            Schema::dropIfExists($table);
        }
        DB::table('migrations')->whereIn('migration', array_map(fn (array $fixture): string => pathinfo($fixture[0], PATHINFO_FILENAME), $fixtures))->delete();
        CmsModule::query()->whereIn('identifier', array_column(array_values($moduleDefinitions), 0))->delete();
        $files->deleteDirectory($root);
    }
});
