<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Http\Middleware\EnsureCmsIsInstalled;
use App\Http\Middleware\RedirectIfInstalled;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\File;

trait InstallerRuntime
{
    use DatabaseMigrations;

    private string $installerRuntime;

    private string $installerOriginalEnvironment;

    private string $installerOriginalStorage;

    protected function beforeRefreshingDatabase(): void
    {
        IsolatedTestDatabase::assertConnection();
        $this->installerOriginalEnvironment = app()->environmentPath();
        $this->installerOriginalStorage = app()->storagePath();
        $this->installerRuntime = sys_get_temp_dir().'/dbcms-installer-'.bin2hex(random_bytes(8));
        foreach (['storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs'] as $directory) {
            File::ensureDirectoryExists($this->installerRuntime.'/'.$directory, 0700);
        }
        File::put($this->installerRuntime.'/.env', 'APP_KEY='.(string) config('app.key')."\n");
        app()->useEnvironmentPath($this->installerRuntime)->useStoragePath($this->installerRuntime.'/storage');
        config(['view.compiled' => storage_path('framework/views')]);
        $this->withMiddleware([EnsureCmsIsInstalled::class, RedirectIfInstalled::class]);
    }

    protected function tearDownInstallerRuntime(): void
    {
        app()->useEnvironmentPath($this->installerOriginalEnvironment)->useStoragePath($this->installerOriginalStorage);
        File::deleteDirectory($this->installerRuntime);
    }
}
