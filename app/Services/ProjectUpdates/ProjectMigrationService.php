<?php

declare(strict_types=1);

namespace App\Services\ProjectUpdates;

use App\Contracts\Services\ProjectMigrationServiceInterface;
use App\Contracts\Services\ProjectUpdateServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Models\CmsModule;
use Illuminate\Database\Migrations\Migrator;

final class ProjectMigrationService implements ProjectMigrationServiceInterface
{
    public function __construct(
        private readonly Migrator $migrator,
        private readonly CmsModule $modules,
        private readonly ProjectUpdateServiceInterface $updates,
        private readonly UpdateMigrationServiceInterface $signedMigrations,
        private readonly SignedManifestVerifier $verifier,
    ) {}

    public function migrate(string $root): void
    {
        $root = rtrim($root, '/');
        if (! is_file($root.'/storage/installed') || ! is_file($this->ordinary($root, 'cms-project.json'))) {
            throw new \DomainException('Complete the project web installation before deploying migrations.');
        }
        $report = $root.'/resources/updates/project-update.json';
        if (file_exists($report) || is_link($report)) {
            $this->validateSignedUpdate($root);
        }
        $directories = [];
        foreach (['database/migrations', 'vendor/laravel/passport/database/migrations'] as $relative) {
            $path = $this->ordinary($root, $relative);
            if (is_dir($path)) {
                $directories[] = $path;
            }
        }
        $installed = $this->modules->newQuery()->where('is_installed', true)->pluck('identifier')->all();
        foreach (glob($root.'/modules/*', GLOB_ONLYDIR) ?: [] as $directory) {
            $metadataPath = $this->ordinary($root, substr($directory, strlen($root) + 1).'/module.json');
            if (! is_file($metadataPath)) {
                continue;
            }
            $metadata = json_decode((string) file_get_contents($metadataPath), true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($metadata) || ! is_string($metadata['identifier'] ?? null)) {
                throw new \DomainException('Invalid module migration ownership metadata.');
            }
            if (in_array($metadata['identifier'], $installed, true)) {
                $path = $this->ordinary($root, substr($directory, strlen($root) + 1).'/Database/Migrations');
                if (is_dir($path)) {
                    $directories[] = $path;
                }
            }
        }
        $files = [];
        $names = [];
        foreach ($directories as $directory) {
            foreach (glob($directory.'/*_*.php') ?: [] as $path) {
                $path = $this->ordinary($root, substr($path, strlen($root) + 1));
                if (! is_file($path)) {
                    throw new \DomainException('Migration must be an ordinary file.');
                }
                $name = pathinfo($path, PATHINFO_FILENAME);
                if (isset($names[$name])) {
                    throw new \DomainException('Duplicate migration identity requires explicit review: '.$name);
                }
                $names[$name] = true;
                $files[] = $path;
            }
        }
        // Explicit files bypass all globally registered module migration paths.
        $this->migrator->run($files, ['pretend' => false, 'step' => true]);
    }

    private function validateSignedUpdate(string $root): void
    {
        $this->updates->review($root);
        $raw = $this->ordinary($root, 'resources/updates/installed-manifest.json');
        $signature = $this->ordinary($root, 'resources/updates/installed-manifest.sig');
        if (! is_file($raw) || ! is_file($signature)) {
            throw new \DomainException('Missing signed project migration manifest.');
        }
        $manifest = $this->verifier->verify((string) file_get_contents($raw), (string) file_get_contents($signature), config('updates.trusted_keys', []));
        $previousRoot = config('updates.root_path');
        try {
            config(['updates.root_path' => $root]);
            $this->signedMigrations->pending($manifest->data);
        } finally {
            config(['updates.root_path' => $previousRoot]);
        }
    }

    private function ordinary(string $root, string $relative): string
    {
        $path = $root.'/'.$relative;
        for ($current = $path; $current !== dirname($current); $current = dirname($current)) {
            if (is_link($current)) {
                throw new \DomainException('Project migrations cannot traverse symbolic links.');
            }
        }
        if (file_exists($path) && ! str_starts_with((string) realpath($path), $root.'/')) {
            throw new \DomainException('Migration path is outside the project.');
        }

        return $path;
    }
}
