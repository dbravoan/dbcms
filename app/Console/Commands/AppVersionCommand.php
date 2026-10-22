<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

final class AppVersionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:version
                            {--bump= : Increment SemVer level (major, minor, patch)}
                            {--set= : Set an explicit SemVer version string (e.g. 2.1.0)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Inspect, bump, or synchronize the canonical SemVer application version';

    public function handle(): int
    {
        $versionFile = base_path('VERSION');
        $currentVersion = $this->getCurrentVersion();

        $bump = $this->option('bump');
        $set = $this->option('set');

        if ($set !== null) {
            $newVersion = (string) $set;
            if (! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $newVersion)) {
                $this->error("Invalid SemVer format '{$newVersion}'. Must be formatted as X.Y.Z (e.g. 1.2.0).");

                return self::FAILURE;
            }

            $this->applyVersion($newVersion, 'Manual explicit set');

            return self::SUCCESS;
        }

        if ($bump !== null) {
            $bumpType = strtolower((string) $bump);
            $newVersion = $this->calculateBump($currentVersion, $bumpType);

            if ($newVersion === null) {
                $this->error("Invalid bump type '{$bump}'. Supported values: 'major' (cambio grande), 'minor' (cambio menor/funcionalidad), 'patch' (parche).");

                return self::FAILURE;
            }

            $this->applyVersion($newVersion, ucfirst($bumpType).' bump');

            return self::SUCCESS;
        }

        // Just inspecting current version
        $gitCommit = $this->getGitCommitHash();
        $this->components->info('dbCMS Application Version');
        $this->table(
            ['Property', 'Value'],
            [
                ['Version (SemVer)', $currentVersion],
                ['Environment', (string) config('app.env')],
                ['Laravel Framework', app()->version()],
                ['Git Commit', $gitCommit ?? 'N/A'],
                ['Version Source', File::exists($versionFile) ? 'VERSION file' : 'config/cms.php default'],
            ]
        );

        return self::SUCCESS;
    }

    public function getCurrentVersion(): string
    {
        $versionFile = base_path('VERSION');
        if (File::exists($versionFile)) {
            $content = trim((string) File::get($versionFile));
            if (preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/', $content)) {
                return $content;
            }
        }

        return (string) config('cms.version');
    }

    public function calculateBump(string $current, string $type): ?string
    {
        if (! preg_match('/^([0-9]+)\.([0-9]+)\.([0-9]+)$/', $current, $matches)) {
            return null;
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $patch = (int) $matches[3];

        return match ($type) {
            'major' => sprintf('%d.0.0', $major + 1),
            'minor' => sprintf('%d.%d.0', $major, $minor + 1),
            'patch' => sprintf('%d.%d.%d', $major, $minor, $patch + 1),
            default => null,
        };
    }

    protected function applyVersion(string $newVersion, string $reason): void
    {
        $versionFile = base_path('VERSION');
        File::put($versionFile, $newVersion.PHP_EOL);

        // Update .env APP_VERSION if .env exists
        $envPath = base_path('.env');
        if (File::exists($envPath)) {
            $envContent = (string) File::get($envPath);
            if (str_contains($envContent, 'APP_VERSION=')) {
                $updatedEnv = preg_replace('/^APP_VERSION=.*$/m', "APP_VERSION={$newVersion}", $envContent);
                File::put($envPath, (string) $updatedEnv);
            }
        }

        $this->components->info("Application version successfully updated to [{$newVersion}] ({$reason}).");
    }

    protected function getGitCommitHash(): ?string
    {
        try {
            $hash = trim((string) @shell_exec('git rev-parse --short HEAD 2>/dev/null'));

            return $hash !== '' ? $hash : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
