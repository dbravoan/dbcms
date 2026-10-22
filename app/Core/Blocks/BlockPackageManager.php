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
                try {
                    BlockPackageManifest::validate($manifest, basename($directory));
                } catch (\DomainException $error) {
                    $this->invalid($error->getMessage());
                }
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
        $states = $this->availability($packages);
        foreach ($packages as $identifier => &$package) {
            $package['enabled'] = $states[$identifier];
        }
        unset($package);

        return $packages;
    }

    public function isEnabled(string $identifier): bool
    {
        $package = $this->all()[$identifier] ?? null;

        return $package !== null && $this->availability([$identifier => $package])[$identifier];
    }

    /** One snapshot per operation; later operations re-read mutable state.
     * @param  array<string,array<string,mixed>>|null  $packages
     * @return array<string,bool>
     */
    public function availability(?array $packages = null): array
    {
        $packages ??= $this->all();
        $stored = Schema::hasTable('block_packages')
            ? DB::table('block_packages')->pluck('is_enabled', 'identifier')->all()
            : [];
        $states = [];
        $moduleStates = [];
        foreach ($packages as $identifier => $package) {
            $states[$identifier] = (bool) ($stored[$identifier] ?? $package['bundled']) && $this->unmetRequirement($package, $moduleStates) === null;
        }

        return $states;
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
            try {
                BlockPackageManifest::validate($manifest, $folder);
            } catch (\DomainException $error) {
                $this->invalid($error->getMessage());
            }
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

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['package_zip' => $message]);
    }

    /** @param array<string,mixed> $package
     * @param  array<array-key,bool>  $moduleStates
     */
    private function unmetRequirement(array $package, array &$moduleStates = []): ?string
    {
        foreach ((array) ($package['requires'] ?? []) as $identifier => $constraint) {
            $manifest = $identifier === 'core'
                ? ['version' => (string) config('cms.version')]
                : app(ModuleManager::class)->getModuleManifest((string) $identifier);
            if ($manifest === null || ! Semver::satisfies((string) $manifest['version'], (string) $constraint)
                || ($identifier !== 'core' && ! ($moduleStates[$identifier] ??= app(ModuleManager::class)->isModuleEnabled((string) $identifier)))) {
                return (string) $identifier;
            }
        }

        return null;
    }
}
