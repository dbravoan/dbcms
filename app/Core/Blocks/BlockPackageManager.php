<?php

declare(strict_types=1);

namespace App\Core\Blocks;

use App\Core\Blocks\Events\BlockPackageAvailabilityChanged;
use App\Core\Extensions\SafeExtensionArchive;
use App\Core\Modules\ModuleManager;
use App\Services\Cms\PageCacheService;
use Composer\Semver\Semver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Throwable;

/** Manifests are code-owned; persisted state only controls availability. */
final class BlockPackageManager
{
    public function __construct(private readonly SafeExtensionArchive $archives, private readonly BlockPackageVerifier $verifier) {}

    public function bundledRoot(): string
    {
        return (string) config('cms.block_packages.bundled_root', base_path('block-packages'));
    }

    public function uploadedRoot(): string
    {
        return (string) config('cms.block_packages.root', storage_path('app/extensions/blocks'));
    }

    /** @return array<string,array<string,mixed>> */
    public function all(): array
    {
        $packages = [];
        $namespaces = [];
        foreach ([$this->bundledRoot() => true, $this->uploadedRoot() => false] as $root => $bundled) {
            if (! is_dir($root)) {
                continue;
            }
            foreach (File::directories($root) as $directory) {
                $path = $directory.'/block-package.json';
                if (! is_file($path)) {
                    continue;
                }
                $manifest = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
                if (! is_array($manifest)) {
                    throw new \DomainException('Invalid block package manifest.');
                }
                $this->validate($manifest, basename($directory));
                $namespace = strtolower(basename($directory));
                if (isset($namespaces[$namespace])) {
                    throw new \DomainException('Duplicate block package namespace: '.basename($directory));
                }
                $namespaces[$namespace] = true;
                $id = (string) $manifest['identifier'];
                if (isset($packages[$id])) {
                    throw new \DomainException('Duplicate block package identifier: '.$id);
                }
                $packages[$id] = array_replace($manifest, ['directory' => $directory, 'bundled' => $bundled]);
            }
        }

        return $packages;
    }

    /** @return array<string,array<string,mixed>> */
    public function forAdministration(): array
    {
        $packages = $this->all();
        foreach ($packages as $identifier => &$package) {
            $package['enabled'] = $this->isEnabled($identifier);
        }
        unset($package);

        return $packages;
    }

    public function isEnabled(string $identifier): bool
    {
        $package = $this->all()[$identifier] ?? null;
        if ($package === null) {
            return false;
        }
        $enabled = (bool) $package['bundled'];
        if (Schema::hasTable('block_packages')) {
            $record = DB::table('block_packages')->where('identifier', $identifier)->first();
            $enabled = $record === null ? $enabled : (bool) $record->is_enabled;
        }
        if (! $enabled) {
            return false;
        }

        return $this->unmetRequirement($package) === null;
    }

    public function setEnabled(string $identifier, bool $enabled): void
    {
        $package = $this->all()[$identifier] ?? null;
        if ($package === null) {
            $this->invalid('Block package not found.');
        }
        if ($enabled) {
            $unmet = $this->unmetRequirement($package);
            if ($unmet !== null) {
                $this->invalid('Missing or incompatible requirement: '.$unmet);
            }
        }
        $wasEnabled = $this->isEnabled($identifier);
        DB::transaction(static function () use ($identifier, $enabled, $wasEnabled): void {
            DB::table('block_packages')->updateOrInsert(['identifier' => $identifier], ['is_enabled' => $enabled, 'updated_at' => now(), 'created_at' => now()]);
            if ($wasEnabled !== $enabled) {
                event(new BlockPackageAvailabilityChanged($identifier, $enabled));
            }
        });
        app(PageCacheService::class)->invalidate();
    }

    public function install(string $path, int $bytes): string
    {
        $result = $this->archives->install($path, $bytes, $this->uploadedRoot(), 'block-package.json', function (array $manifest, string $folder, array $entries): void {
            $this->validate($manifest, $folder);
            foreach ($manifest['blocks'] as $class) {
                $file = str_replace('\\', '/', substr((string) $class, strlen('BlockPackages\\'))).'.php';
                if (! isset($entries[$file]) || $entries[$file]['directory']) {
                    $this->invalid('A declared block class file is missing.');
                }
            }
        }, function (array $manifest): bool {
            $namespace = strtolower(explode('\\', $manifest['blocks'][0])[1]);
            foreach ($this->all() as $existing) {
                if ($existing['identifier'] === $manifest['identifier'] || strtolower(basename($existing['directory'])) === $namespace) {
                    return true;
                }
            }

            return DB::table('block_packages')->where('identifier', $manifest['identifier'])->exists();
        }, fn (string $directory, array $manifest) => $this->verifier->verify($directory, $manifest));
        try {
            DB::transaction(static fn () => DB::table('block_packages')->insert(['identifier' => $result['manifest']['identifier'], 'is_enabled' => false, 'created_at' => now(), 'updated_at' => now()]));
        } catch (Throwable $error) {
            $this->archives->remove($result['directory'], $this->uploadedRoot());
            throw $error;
        }

        return (string) $result['manifest']['identifier'];
    }

    public function uninstall(string $identifier): void
    {
        $package = $this->all()[$identifier] ?? null;
        if ($package === null || $package['bundled']) {
            $this->invalid('Bundled block packages cannot be uninstalled.');
        }
        if ($this->isEnabled($identifier)) {
            $this->invalid('Disable the package before uninstalling it.');
        }
        $this->archives->remove((string) $package['directory'], $this->uploadedRoot());
        DB::table('block_packages')->where('identifier', $identifier)->delete();
    }

    /** @param array<string,mixed> $manifest */
    private function validate(array $manifest, string $folder): void
    {
        if (! is_string($manifest['identifier'] ?? null) || ! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $manifest['identifier']) || ! is_string($manifest['name'] ?? null) || trim($manifest['name']) === '' || ! is_string($manifest['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+(?:[-+][0-9A-Za-z.-]+)?$/D', $manifest['version']) || ! is_array($manifest['blocks'] ?? null) || $manifest['blocks'] === [] || ! array_is_list($manifest['blocks'])) {
            $this->invalid('Invalid block package identity or definitions.');
        }
        foreach ($manifest['blocks'] as $class) {
            if (! is_string($class) || ! str_starts_with($class, 'BlockPackages\\'.$folder.'\\') || ! preg_match('/^BlockPackages\\\\[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)+$/D', $class)) {
                $this->invalid('Block definitions must belong to the package namespace.');
            }
        }
        foreach ((array) ($manifest['requires'] ?? []) as $id => $constraint) {
            if (! is_string($id) || ! is_string($constraint)) {
                $this->invalid('Invalid package requirements.');
            }
            try {
                Semver::satisfies('1.0.0', $constraint);
            } catch (\UnexpectedValueException) {
                $this->invalid('Invalid package version constraint.');
            }
        }
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['package_zip' => $message]);
    }

    /** @param array<string,mixed> $package */
    private function unmetRequirement(array $package): ?string
    {
        foreach ((array) ($package['requires'] ?? []) as $identifier => $constraint) {
            $manifest = $identifier === 'core'
                ? ['version' => (string) config('cms.version', '1.0.0')]
                : app(ModuleManager::class)->getModuleManifest((string) $identifier);
            if ($manifest === null || ! Semver::satisfies((string) $manifest['version'], (string) $constraint)
                || ($identifier !== 'core' && ! app(ModuleManager::class)->isModuleEnabled((string) $identifier))) {
                return (string) $identifier;
            }
        }

        return null;
    }
}
