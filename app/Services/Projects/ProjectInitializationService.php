<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Services\ProjectDeploymentAdoptionServiceInterface;
use App\Contracts\Services\ProjectInitializationServiceInterface;
use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Core\Updates\ProjectDeploymentPolicy;
use App\DTOs\ProjectProfile;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;

final class ProjectInitializationService implements ProjectInitializationServiceInterface
{
    public function __construct(private Filesystem $files, private SourceRevisionResolverInterface $source, private string $root, private ?ProjectDeploymentAdoptionServiceInterface $adoption = null) {}

    public function initialize(string $name, string $brand, string $themes, string $deployment): ProjectProfile
    {
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $name) || ! in_array($brand, ['dbcms', 'termocms'], true)) {
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
        $existingPath = $this->root.'/cms-project.json';
        if ($this->files->exists($existingPath)) {
            return $this->existing($existingPath, $name, $brand, $selection, $default, $deployment, $removals);
        }
        $metadata = json_decode($this->files->get($this->root.'/resources/updates/core-version.json'), true, 512, JSON_THROW_ON_ERROR);
        $version = is_array($metadata) && is_string($metadata['version'] ?? null) ? $metadata['version'] : '';
        $profile = new ProjectProfile($name, $brand, $selection, $default, $version, $this->source->resolve($version), $deployment);
        if (! preg_match('/^[a-f0-9]{40}$/D', $profile->coreSha)) {
            throw new RuntimeException('Invalid official source revision.');
        }
        $this->prune($catalogue, $selection, $brand);
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
        foreach (['', '/themes', '/resources', '/resources/views', '/resources/views/themes', '/resources/views/themes/admin', '/resources/updates', '/resources/updates/core-version.json', '/cms-project.json', '/.github', '/.github/workflows'] as $relative) {
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
                $themes[] = basename($directory);
            }
        }
        sort($themes);

        return $themes;
    }

    /**
     * @param  list<string>  $selection
     * @param  list<string>  $removals
     */
    private function existing(string $path, string $name, string $brand, array $selection, string $default, string $deployment, array $removals): ProjectProfile
    {
        $data = json_decode($this->files->get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ($data['schema'] ?? null) !== 1 || ($data['name'] ?? null) !== $name || ($data['brand'] ?? null) !== $brand || ($data['themes'] ?? null) !== $selection || ($data['default_theme'] ?? null) !== $default) {
            throw new InvalidArgumentException('Project already initialized with a different profile.');
        }
        $core = $data['core'] ?? null;
        if (! is_array($core) || ($core['repository'] ?? null) !== 'dbravoan/dbcms' || ! is_string($core['version'] ?? null) || ! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $core['version']) || ! is_string($core['sha'] ?? null) || ! preg_match('/^[a-f0-9]{40}$/D', $core['sha'])) {
            throw new InvalidArgumentException('Invalid initialized project provenance.');
        }

        $profile = new ProjectProfile($name, $brand, $selection, $default, $core['version'], $core['sha'], $deployment);
        if (array_key_exists('deployment', $data)) {
            if ($data['deployment'] !== $deployment) {
                throw new InvalidArgumentException('Project already initialized with a different deployment profile.');
            }

            return $profile;
        }
        if ($this->adoption === null) {
            throw new RuntimeException('A signed baseline verifier is required to select a legacy deployment profile.');
        }
        $this->adoption->assertRemovable($this->root, $core, $removals);
        $this->pruneDeployment($removals);
        $data['deployment'] = $deployment;
        $this->files->replace($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");

        return $profile;
    }

    /**
     * @param  list<string>  $catalogue
     * @param  list<string>  $selection
     */
    private function prune(array $catalogue, array $selection, string $brand): void
    {
        foreach (array_diff($catalogue, $selection) as $theme) {
            $this->files->deleteDirectory($this->root.'/themes/'.$theme);
        }
        foreach (array_diff(['dbcms', 'termocms'], [$brand]) as $excluded) {
            $this->files->deleteDirectory($this->root.'/resources/views/themes/admin/'.$excluded);
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
