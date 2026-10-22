<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Core\Blocks\BlockPackageManifest;
use App\Core\Modules\ModuleManifestGraph;
use App\Core\Updates\ProjectUpdatePlanner;
use Composer\Semver\Semver;
use DomainException;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;

/** Source projection for preparation and quality checks; never installs extensions. */
final class ProjectSourceSelection
{
    /** @var array<string, array{directory:string,manifest:array<string,mixed>}> */
    private array $modules;

    /** @var array<string, array{directory:string,manifest:array<string,mixed>}> */
    private array $packages;

    private ModuleManifestGraph $graph;

    public function __construct(private readonly Filesystem $files, private readonly string $root, private readonly string $coreVersion)
    {
        $this->modules = $this->catalogue('modules', 'module.json');
        $this->packages = $this->catalogue('block-packages', 'block-package.json');
        $this->graph = new ModuleManifestGraph(array_column($this->modules, 'manifest'), $coreVersion);
        foreach ($this->packages as $package) {
            BlockPackageManifest::validate($package['manifest'], $package['directory']);
        }
    }

    /** @return array{module_directories:list<string>,block_package_directories:list<string>,include_showcase:bool} */
    public function resolve(string $modules = 'all', string $blockPackages = 'all'): array
    {
        $selected = $this->parse($modules, array_keys($this->modules), 'modules');
        $packages = $this->parse($blockPackages, array_keys($this->packages), 'block packages');
        foreach ($packages as $identifier) {
            foreach ($this->packages[$identifier]['manifest']['requires'] ?? [] as $dependency => $constraint) {
                $version = $dependency === 'core' ? $this->coreVersion : ($this->modules[$dependency]['manifest']['version'] ?? null);
                if (! is_string($version) || ! Semver::satisfies($version, $constraint)) {
                    throw new DomainException('Missing or incompatible block package requirement: '.$dependency);
                }
                if ($dependency !== 'core') {
                    $selected[] = $dependency;
                }
            }
        }
        $selected = array_values(array_unique($selected));
        for ($index = 0; $index < count($selected); $index++) {
            $manifest = $this->modules[$selected[$index]]['manifest'] ?? throw new DomainException('Unavailable module: '.$selected[$index]);
            foreach (array_keys($manifest['requires'] ?? []) as $dependency) {
                if ($dependency !== 'core' && ! in_array($dependency, $selected, true)) {
                    $selected[] = $dependency;
                }
            }
        }
        $selected = $this->graph->ordered($selected);
        $moduleDirectories = array_map(fn (string $id): string => $this->modules[$id]['directory'], $selected);
        $packageDirectories = array_map(fn (string $id): string => $this->packages[$id]['directory'], $packages);
        sort($moduleDirectories);
        sort($packageDirectories);

        return ['module_directories' => $moduleDirectories, 'block_package_directories' => $packageDirectories, 'include_showcase' => false];
    }

    /**
     * @param  array{module_directories:list<string>,block_package_directories:list<string>,include_showcase:bool}  $selection
     * @return array{modules:list<string>,block_packages:list<string>}
     */
    public function identifiers(array $selection): array
    {
        $modules = array_keys(array_filter($this->modules, fn (array $entry): bool => in_array($entry['directory'], $selection['module_directories'], true)));
        $packages = array_keys(array_filter($this->packages, fn (array $entry): bool => in_array($entry['directory'], $selection['block_package_directories'], true)));

        return ['modules' => $modules, 'block_packages' => $packages];
    }

    /**
     * @param  array<string,mixed>  $selection
     */
    public function assertPhysicalSelection(array $selection): void
    {
        (new ProjectUpdatePlanner)->validateSourceSelection(['source_selection' => $selection]);
        foreach (['module_directories' => $this->modules, 'block_package_directories' => $this->packages] as $key => $catalogue) {
            $actual = array_column($catalogue, 'directory');
            $expected = $selection[$key];
            sort($actual);
            sort($expected);
            if ($actual !== $expected) {
                throw new DomainException('The physical source catalogue differs from source_selection: '.$key);
            }
        }
        $resolved = $this->resolve();
        foreach (['module_directories', 'block_package_directories'] as $key) {
            $expected = $selection[$key];
            sort($expected);
            if ($resolved[$key] !== $expected) {
                throw new DomainException('The selected dependency closure differs from the physical source catalogue.');
            }
        }
        $showcase = $this->root.'/database/seeders/PlatformShowcaseSeeder.php';
        if (is_link($showcase) || is_file($showcase) !== $selection['include_showcase']) {
            throw new DomainException('The physical showcase differs from source_selection.');
        }
    }

    /** @return array{modules:array<string,string>,block_packages:array<string,string>} */
    public function choices(): array
    {
        $labels = static function (array $catalogue): array {
            $choices = [];
            foreach ($catalogue as $id => $entry) {
                $name = $entry['manifest']['name'] ?? $entry['manifest']['label'] ?? $id;
                $choices[$id] = is_string($name) ? $name : $id;
            }

            return $choices;
        };

        return ['modules' => $labels($this->modules), 'block_packages' => $labels($this->packages)];
    }

    /** @return list<string> */
    public function moduleDirectories(): array
    {
        return array_values(array_column($this->modules, 'directory'));
    }

    /** @return list<string> */
    public function packageDirectories(): array
    {
        return array_values(array_column($this->packages, 'directory'));
    }

    /**
     * @param  list<string>  $available
     * @return list<string>
     */
    private function parse(string $value, array $available, string $label): array
    {
        if ($value === 'all') {
            return $available;
        }
        if ($value === 'none') {
            return [];
        }
        $selected = array_values(array_unique(array_map('trim', explode(',', $value))));
        foreach ($selected as $identifier) {
            if (! preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $identifier) || ! in_array($identifier, $available, true)) {
                throw new InvalidArgumentException('Select '.$label.' by manifest ID: all, none or a CSV list. Unknown ID: '.$identifier);
            }
        }
        sort($selected);

        return $selected;
    }

    /** @return array<string, array{directory:string,manifest:array<string,mixed>}> */
    private function catalogue(string $path, string $manifestFile): array
    {
        $root = $this->root.'/'.$path;
        if (is_link($root) || (file_exists($root) && ! is_dir($root))) {
            throw new InvalidArgumentException('Extension source roots must be ordinary directories.');
        }
        if (! is_dir($root)) {
            return [];
        }
        self::assertOrdinaryTree($root);
        $catalogue = [];
        $namespaces = [];
        foreach ($this->files->directories($root) as $directory) {
            $name = basename($directory);
            if (str_starts_with($name, '.')) {
                continue;
            }
            if (! is_file($directory.'/'.$manifestFile)) {
                throw new DomainException('Extension source directory has no regular manifest: '.$directory);
            }
            if (! preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/D', $name)) {
                throw new InvalidArgumentException('Invalid extension source directory: '.$name);
            }
            $manifest = json_decode($this->files->get($directory.'/'.$manifestFile), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($manifest) || ! is_string($manifest['identifier'] ?? null)) {
                throw new DomainException('Invalid source manifest: '.$directory.'/'.$manifestFile);
            }
            $identifier = $manifest['identifier'];
            if (isset($catalogue[$identifier]) || isset($namespaces[strtolower($name)])) {
                throw new DomainException('Duplicate extension identity or namespace: '.$identifier);
            }
            $catalogue[$identifier] = ['directory' => $name, 'manifest' => $manifest];
            $namespaces[strtolower($name)] = true;
        }
        ksort($catalogue);

        return $catalogue;
    }

    public static function assertOrdinaryTree(string $directory): void
    {
        if (is_link($directory) || ! is_dir($directory)) {
            throw new InvalidArgumentException('Project resources must be ordinary directories.');
        }
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
        foreach ($iterator as $entry) {
            if ($entry->isLink() || (! $entry->isDir() && ! $entry->isFile())) {
                throw new InvalidArgumentException('Project resources may not contain symbolic links or special files.');
            }
        }
    }
}
