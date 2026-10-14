<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Models\CmsModule;
use Illuminate\Database\Migrations\Migrator;
use RuntimeException;

final class UpdateMigrationService implements UpdateMigrationServiceInterface
{
    public function __construct(private readonly Migrator $migrator) {}

    /** @param array<string, mixed> $manifest
     * @return list<string>
     */
    public function pending(array $manifest): array
    {
        $paths = [];
        $ran = $this->migrator->getRepository()->getRan();
        $root = (string) config('updates.root_path');
        $migrations = $manifest['migrations'] ?? [];
        foreach (($migrations['core'] ?? []) as $path) {
            if (in_array(pathinfo($path, PATHINFO_FILENAME), $ran, true)) {
                continue;
            }
            $paths[] = $this->validatedPath($root, $path, 'database/migrations/', $manifest);
        }
        $installed = CmsModule::query()->where('is_installed', true)->pluck('identifier')->all();
        foreach (($migrations['modules'] ?? []) as $identifier => $modulePaths) {
            if (! in_array($identifier, $installed, true)) {
                continue;
            }
            foreach ($modulePaths as $path) {
                if (in_array(pathinfo($path, PATHINFO_FILENAME), $ran, true)) {
                    continue;
                }
                $paths[] = $this->validatedPath($root, $path, 'modules/', $manifest);
            }
        }
        $paths = array_values(array_unique($paths));
        $names = array_map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME), $paths);
        if (count(array_unique($names)) !== count($names)) {
            throw new RuntimeException('Dos migraciones pendientes comparten identidad; requieren revisión gestionada.');
        }

        return $paths;
    }

    /**
     * The caller persists migrations_running BEFORE entering this method. Never automatically retry.
     *
     * @param  list<string>  $paths
     */
    public function apply(array $paths): void
    {
        if ($paths !== []) {
            $this->migrator->run($paths, ['pretend' => false, 'step' => true]);
        }
    }

    /** @param array<string,mixed> $manifest */
    private function validatedPath(string $root, string $path, string $prefix, array $manifest): string
    {
        if (! str_starts_with($path, $prefix) || str_contains($path, '..') || str_contains($path, '\\') || ! str_ends_with($path, '.php')) {
            throw new RuntimeException('Ruta de migración no permitida.');
        }
        $real = realpath($root.'/'.$path);
        if ($real === false || ! str_starts_with($real, $root.'/') || is_link($root.'/'.$path)) {
            throw new RuntimeException('Migración ausente o fuera del núcleo verificado.');
        }

        $expected = $manifest['distributions']['runtime']['files'][$path] ?? $manifest['distributions']['source']['files'][$path] ?? null;
        if (! is_string($expected) || ! hash_equals($expected, (string) hash_file('sha256', $real))) {
            throw new RuntimeException('La migración ha cambiado respecto al manifiesto firmado.');
        }

        return $real;
    }
}
