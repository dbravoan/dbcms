<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Core\Updates\ProjectDeploymentPolicy;
use App\DTOs\ProjectProfile;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

final class ProjectInitializationService
{
    public function __construct(private Filesystem $files, private SourceRevisionResolverInterface $source, private string $root, private ?ProjectSourceRepositoryInterface $sources = null) {}

    public function initialize(string $name, string $brand, string $themes, string $deployment, string $modules = 'all', string $blockPackages = 'all', ?\Closure $beforePrune = null): ProjectProfile
    {
        if (! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $name) || ! in_array($brand, ['dbcms', 'termocms'], true)) {
            throw new InvalidArgumentException('Use a project slug and --brand=dbcms or --brand=termocms.');
        }
        if (! in_array($deployment, ProjectDeploymentPolicy::DRIVERS, true)) {
            throw new InvalidArgumentException('Select --deploy=github-actions, ansistrano, rsync, docker or none.');
        }
        $this->preflight($brand);
        $removals = (new ProjectDeploymentPolicy)->removals($this->root, $deployment);
        $brandPath = $this->root.'/resources/views/themes/admin/'.$brand;
        if (! $this->files->isDirectory($brandPath) || is_link($brandPath)) {
            throw new InvalidArgumentException('Selected administrative brand is not installed.');
        }
        $catalogue = $this->catalogue();
        $selection = $themes === 'all' ? $catalogue : array_values(array_unique(array_map('trim', explode(',', $themes))));
        if ($selection === [] || array_diff($selection, $catalogue) !== []) {
            throw new InvalidArgumentException('Select --theme=all or a CSV list of installed public themes.');
        }
        $default = $themes === 'all' && in_array('db-cyber', $selection, true) ? 'db-cyber' : $selection[0];
        $metadata = json_decode($this->files->get($this->root.'/resources/updates/core-version.json'), true, 512, JSON_THROW_ON_ERROR);
        $version = is_array($metadata) && is_string($metadata['version'] ?? null) ? $metadata['version'] : '';
        if (! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version)) {
            throw new InvalidArgumentException('An official stable core version is required.');
        }
        $extensions = new ProjectSourceSelection($this->files, $this->root, $version);
        $sourceSelection = $extensions->resolve($modules, $blockPackages);
        $existingPath = $this->root.'/cms-project.json';
        if ($this->files->exists($existingPath)) {
            return $this->existing($existingPath, $name, $brand, $selection, $default, $deployment, $sourceSelection, $modules, $blockPackages, $extensions);
        }
        $profile = new ProjectProfile($name, $brand, $selection, $default, $version, $this->source->resolve($version), $deployment, $sourceSelection);
        if (! preg_match('/^[a-f0-9]{40}$/D', $profile->coreSha)) {
            throw new RuntimeException('Invalid official source revision.');
        }
        $directories = [];
        foreach (array_diff($catalogue, $selection) as $theme) {
            $directories[] = 'themes/'.$theme;
        }
        foreach (array_diff(['dbcms', 'termocms'], [$brand]) as $excluded) {
            if (is_dir($this->root.'/resources/views/themes/admin/'.$excluded)) {
                $directories[] = 'resources/views/themes/admin/'.$excluded;
            }
        }
        foreach (['modules' => [$extensions->moduleDirectories(), $sourceSelection['module_directories']], 'block-packages' => [$extensions->packageDirectories(), $sourceSelection['block_package_directories']]] as $path => [$available, $selected]) {
            foreach (array_diff($available, $selected) as $directory) {
                $directories[] = $path.'/'.$directory;
            }
        }
        $showcase = 'database/seeders/PlatformShowcaseSeeder.php';
        if (is_link($this->root.'/'.$showcase)) {
            throw new InvalidArgumentException('The showcase must be an ordinary source file.');
        }
        if (is_file($this->root.'/'.$showcase)) {
            $removals[] = $showcase;
        }
        $this->assertUnmodifiedExclusions($directories, $removals, $profile);
        if ($beforePrune !== null) {
            $beforePrune($profile, $extensions->identifiers($sourceSelection));
        }
        foreach ($directories as $directory) {
            $this->files->deleteDirectory($this->root.'/'.$directory);
        }
        $this->pruneDeployment($removals);
        $this->updateExamples($profile);
        $this->files->replace($existingPath, json_encode($profile->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        return $profile;
    }

    private function preflight(string $brand): void
    {
        for ($ancestor = $this->root; $ancestor !== dirname($ancestor); $ancestor = dirname($ancestor)) {
            if (is_link($ancestor)) {
                throw new InvalidArgumentException('Project preparation may not traverse symbolic links.');
            }
        }
        foreach (['', '/themes', '/resources', '/resources/views', '/resources/views/themes', '/resources/views/themes/admin', '/resources/updates', '/resources/updates/core-version.json', '/cms-project.json', '/database', '/database/seeders', '/.github', '/.github/workflows'] as $relative) {
            if (is_link($this->root.$relative)) {
                throw new InvalidArgumentException('Project preparation may not traverse symbolic links.');
            }
        }
        foreach (['dbcms', 'termocms'] as $identifier) {
            $directory = $this->root.'/resources/views/themes/admin/'.$identifier;
            if (is_link($directory) || is_link($directory.'/theme.json')) {
                throw new InvalidArgumentException('Administrative brands may not contain symbolic links.');
            }
        }
        if (! $this->files->isFile($this->root.'/resources/views/themes/admin/'.$brand.'/theme.json')) {
            throw new InvalidArgumentException('Selected administrative brand manifest is not installed.');
        }
        foreach (['dbcms', 'termocms'] as $identifier) {
            $manifest = $this->root.'/resources/views/themes/admin/'.$identifier.'/theme.json';
            if (is_file($manifest)) {
                $metadata = json_decode($this->files->get($manifest), flags: JSON_THROW_ON_ERROR);
                if (! $metadata instanceof \stdClass || ($metadata->identifier ?? null) !== $identifier) {
                    throw new InvalidArgumentException('Administrative brand manifests must identify their directory.');
                }
            }
        }
        foreach (['themes', 'resources/views/themes/admin'] as $directory) {
            if (is_dir($this->root.'/'.$directory)) {
                ProjectSourceSelection::assertOrdinaryTree($this->root.'/'.$directory);
            }
        }
        foreach (glob($this->root.'/.env*example') ?: [] as $path) {
            if (is_link($path) || ! $this->files->isFile($path)) {
                throw new InvalidArgumentException('Environment examples must be regular project files.');
            }
        }
    }

    /** @return list<string> */
    private function catalogue(): array
    {
        $themes = [];
        foreach ($this->files->directories($this->root.'/themes') as $directory) {
            if (is_link($directory)) {
                throw new InvalidArgumentException('Theme catalogue may not contain symbolic links.');
            }
            if (is_link($directory.'/theme.json')) {
                throw new InvalidArgumentException('Theme manifests may not be symbolic links.');
            }
            if ($this->files->exists($directory.'/theme.json')) {
                $manifest = json_decode($this->files->get($directory.'/theme.json'), flags: JSON_THROW_ON_ERROR);
                if (! $manifest instanceof \stdClass || ($manifest->identifier ?? null) !== basename($directory)) {
                    throw new InvalidArgumentException('Theme manifests must identify their directory.');
                }
                $themes[] = basename($directory);
            }
        }
        sort($themes);

        return $themes;
    }

    /**
     * @param  list<string>  $selection
     * @param  array{module_directories:list<string>,block_package_directories:list<string>,include_showcase:bool}  $sourceSelection
     */
    private function existing(string $path, string $name, string $brand, array $selection, string $default, string $deployment, array $sourceSelection, string $modules, string $blockPackages, ProjectSourceSelection $extensions): ProjectProfile
    {
        $data = json_decode($this->files->get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['schema'] ?? null) !== 1 || ($data['name'] ?? null) !== $name || ($data['brand'] ?? null) !== $brand || ($data['themes'] ?? null) !== $selection || ! is_string($data['default_theme'] ?? null) || ! in_array($data['default_theme'], $selection, true)) {
            throw new InvalidArgumentException('Project already initialized with a different profile.');
        }
        $core = $data['core'] ?? null;
        if (! is_array($core) || ($core['repository'] ?? null) !== 'dbravoan/dbcms' || ! is_string($core['version'] ?? null) || ! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $core['version']) || ! is_string($core['sha'] ?? null) || ! preg_match('/^[a-f0-9]{40}$/D', $core['sha'])) {
            throw new InvalidArgumentException('Invalid initialized project provenance.');
        }

        if (($data['deployment'] ?? null) !== $deployment) {
            throw new InvalidArgumentException('Project already initialized with a different deployment profile.');
        }

        /** @var array{module_directories:list<string>,block_package_directories:list<string>,include_showcase:bool}|null $storedSelection */
        $storedSelection = $data['source_selection'] ?? null;
        if (array_key_exists('source_selection', $data)) {
            if (! is_array($storedSelection)) {
                throw new InvalidArgumentException('Invalid initialized source selection.');
            }
            $extensions->assertPhysicalSelection($storedSelection);
            foreach (['module_directories', 'block_package_directories'] as $key) {
                sort($storedSelection[$key]);
            }
            $expectedSelection = [...$sourceSelection, 'include_showcase' => $storedSelection['include_showcase']];
            if ($storedSelection !== $expectedSelection) {
                throw new InvalidArgumentException('Project already initialized with a different source selection.');
            }
        } elseif ($modules !== 'all' || $blockPackages !== 'all') {
            throw new InvalidArgumentException('An initialized project cannot change its source selection.');
        }

        return new ProjectProfile($name, $brand, $selection, $data['default_theme'], $core['version'], $core['sha'], $deployment, $storedSelection);
    }

    /**
     * Refuse to delete any excluded resource containing local additions or edits.
     * All comparison happens against the already resolved official Git commit.
     *
     * @param  list<string>  $directories
     * @param  list<string>  $paths
     */
    private function assertUnmodifiedExclusions(array $directories, array $paths, ProjectProfile $profile): void
    {
        foreach ($directories as $relative) {
            ProjectSourceSelection::assertOrdinaryTree($this->root.'/'.$relative);
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root.'/'.$relative, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $paths[] = substr($file->getPathname(), strlen($this->root) + 1);
                }
            }
        }
        if ($paths === []) {
            return;
        }
        $sources = $this->sources ?? app(ProjectSourceRepositoryInterface::class);
        $temporary = sys_get_temp_dir().'/dbcms-project-source-'.bin2hex(random_bytes(12));
        try {
            $sources->fetch($profile->coreVersion, $profile->coreSha, $temporary);
            foreach (array_unique($paths) as $path) {
                $official = $sources->read($temporary, $profile->coreSha, $path);
                if ($official !== $this->files->get($this->root.'/'.$path)) {
                    throw new RuntimeException('Excluded source resource has local changes; retain it or restore the official version: '.$path);
                }
            }
        } finally {
            $this->files->deleteDirectory($temporary);
        }
    }

    /** @param list<string> $paths */
    private function pruneDeployment(array $paths): void
    {
        foreach ($paths as $path) {
            $this->files->delete($this->root.'/'.$path);
        }
        foreach (['.github', 'deploy/ansistrano'] as $relative) {
            $directory = $this->root.'/'.$relative;
            if (! is_dir($directory)) {
                continue;
            }
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $file) {
                if (! $file->isLink() && $file->isDir() && (scandir($file->getPathname()) ?: []) === ['.', '..']) {
                    rmdir($file->getPathname());
                }
            }
            if ((scandir($directory) ?: []) === ['.', '..']) {
                rmdir($directory);
            }
        }
    }

    private function updateExamples(ProjectProfile $profile): void
    {
        foreach (glob($this->root.'/.env*example') ?: [] as $path) {
            $content = $this->files->get($path);
            foreach (['CMS_BRAND' => $profile->brand, 'CMS_THEME' => $profile->defaultTheme, 'CMS_SEED_DEMO' => 'false'] as $key => $value) {
                $content = preg_match('/^'.$key.'=/m', $content) ? (string) preg_replace('/^'.$key.'=.*$/m', $key.'='.$value, $content) : $content."\n".$key.'='.$value."\n";
            }
            $this->files->put($path, $content);
        }
    }
}
