<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\ProjectSourceSnapshotRepositoryInterface;
use App\Contracts\Services\SourceRevisionResolverInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdatePathPolicy;
use App\DTOs\ProjectProfile;
use App\Services\Updates\GithubReleaseGateway;
use DomainException;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;

/** Source-only preparation. No installed state, database or environment writes. */
final class ProjectSourceBootstrapService
{
    public function __construct(private readonly Filesystem $files, private readonly GithubReleaseGateway $releases, private readonly SignedManifestVerifier $verifier, private readonly ProjectSourceRepositoryInterface $sources) {}

    public function prepare(string $root): ProjectBootstrapSession
    {
        $root = rtrim($root, '/');
        $this->ordinary($root, 'cms-project.json');
        if (is_file($root.'/cms-project.json')) {
            $profile = $this->resume($root);

            return new ProjectBootstrapSession($root, '', $profile->coreVersion, $profile->coreSha, existingProfile: $profile);
        }
        $release = $this->releases->listStable()[0] ?? throw new DomainException('No published stable dbCMS release supports guided project bootstrap.');
        $raw = $this->releases->contents($this->releases->asset($release, 'manifest.json')['browser_download_url'], 16777216);
        $signature = $this->releases->contents($this->releases->asset($release, 'manifest.sig')['browser_download_url'], 16384);
        $manifest = $this->verifier->verify($raw, $signature, config('updates.trusted_keys', []));
        $version = $manifest->data['version'];
        $sha = $manifest->data['source_sha'];
        if ($version !== $release['version'] || version_compare(PHP_VERSION, $manifest->data['min_php'], '<')) {
            throw new DomainException('Release identity or PHP requirement is not satisfied.');
        }
        foreach ($manifest->data['extensions'] as $extension) {
            if (! extension_loaded($extension)) {
                throw new DomainException('Required PHP extension: '.$extension);
            }
        }
        $temporary = sys_get_temp_dir().'/dbcms-bootstrap-'.bin2hex(random_bytes(12));
        $stage = $temporary.'/source';
        try {
            $this->files->ensureDirectoryExists($stage, 0700);
            $this->sources->fetch($version, $sha, $temporary.'/git');
            $inventory = $manifest->distribution('source')['files'];
            if (! isset($inventory['resources/updates/core-version.json'], $inventory['bin/project-init'])) {
                throw new DomainException('The latest published stable dbCMS release does not support guided project bootstrap (project_bootstrap: 1 required).');
            }
            foreach ($inventory as $path => $digest) {
                if (str_starts_with($path, 'vendor/')) {
                    throw new DomainException('A source release cannot own installed dependencies.');
                }
                $bytes = $this->sources->read($temporary.'/git', $sha, $path);
                if (hash('sha256', $bytes) !== $digest) {
                    throw new DomainException('Git source differs from the signed inventory: '.$path);
                }
                $this->write($stage, $path, $bytes);
            }
            $descriptor = json_decode($this->files->get($stage.'/resources/updates/core-version.json'), true, flags: JSON_THROW_ON_ERROR);
            if (! is_array($descriptor) || ($descriptor['version'] ?? null) !== $version || ($descriptor['project_bootstrap'] ?? null) !== 1 || ! isset($inventory['bin/project-init'])) {
                throw new DomainException('The latest published stable dbCMS release does not support guided project bootstrap (project_bootstrap: 1 required).');
            }
            // Stable Composer, template and ZIP entries must contain the same official bytes.
            $baseline = $inventory;
            if (! $this->matches($root, $baseline)) {
                $baseline = $this->declaredStableBaseline($root, $version, $temporary);
                if ($baseline === [] || ! $this->matches($root, $baseline)) {
                    if (! $this->sources instanceof ProjectSourceSnapshotRepositoryInterface) {
                        throw new DomainException('Project sources differ from the stable release; official development snapshot verification is unavailable.');
                    }
                    try {
                        $snapshot = $this->sources->snapshot($temporary.'/snapshot', $root);
                    } catch (DomainException $exception) {
                        throw new DomainException($exception->getMessage().' Differing stable source paths: '.implode(', ', $this->differingPaths($root, $inventory)), 0, $exception);
                    }
                    $baseline = $snapshot['files'];
                    if ($baseline === [] || ! isset($baseline['resources/updates/core-version.json'], $baseline['composer.json'], $baseline['bin/project-init']) || ! $this->matches($root, $baseline)) {
                        throw new DomainException('Project origin is unverifiable or official source files have local changes. Differing stable source paths: '.implode(', ', $this->differingPaths($root, $inventory)));
                    }
                }
            }
            $session = new ProjectBootstrapSession($root, $stage, $version, $sha, $baseline, $inventory, $this->changes($root, $baseline, $inventory));
            $this->choices($session);

            return $session;
        } catch (\Throwable $exception) {
            $this->files->deleteDirectory($temporary);
            throw $exception;
        }
    }

    /** @return array{brands:array<string,string>,themes:array<string,string>,modules:array<string,string>,block_packages:array<string,string>} */
    public function choices(ProjectBootstrapSession $session): array
    {
        $root = $session->existingProfile === null ? $session->stagePath : $session->root;
        $choices = ['brands' => [], 'themes' => []];
        foreach (['brands' => 'resources/views/themes/admin', 'themes' => 'themes'] as $group => $relative) {
            ProjectSourceSelection::assertOrdinaryTree($root.'/'.$relative);
            foreach ($this->files->directories($root.'/'.$relative) as $directory) {
                if (! is_file($directory.'/theme.json')) {
                    continue;
                }
                $manifest = json_decode($this->files->get($directory.'/theme.json'), true, flags: JSON_THROW_ON_ERROR);
                $id = basename($directory);
                if (! is_array($manifest) || ($manifest['identifier'] ?? null) !== $id || ! preg_match('/^[a-z][a-z0-9-]*$/D', $id)) {
                    throw new DomainException('Invalid theme source identity: '.$id);
                }
                $name = $manifest['name'] ?? $id;
                $choices[$group][$id] = is_string($name) ? $name : $id;
            }
            ksort($choices[$group]);
        }

        return [...$choices, ...(new ProjectSourceSelection($this->files, $root, $session->version))->choices()];
    }

    /** Callback receives the projected profile, resolved extension IDs and source changes before writes. */
    public function apply(ProjectBootstrapSession $session, string $name, string $brand, string $themes, string $deployment, string $modules = 'all', string $packages = 'all', ?\Closure $beforeApply = null): ProjectProfile
    {
        if ($session->existingProfile !== null) {
            return $session->existingProfile;
        }
        if (! $this->matches($session->stagePath, $session->inventory)) {
            throw new DomainException('Verified staged sources changed before composition.');
        }
        $resolver = new class($session->sha) implements SourceRevisionResolverInterface
        {
            public function __construct(private readonly string $sha) {}

            public function resolve(string $version): string
            {
                return $this->sha;
            }
        };
        // Exclusion checks read the frozen verified stage, never the network a second time.
        $sources = new class($session->stagePath) implements ProjectSourceRepositoryInterface
        {
            public function __construct(private readonly string $root) {}

            public function fetch(string $version, string $sha, string $directory): void {}

            public function read(string $directory, string $sha, string $path): string
            {
                (new UpdatePathPolicy)->requireAllowed($path);

                $bytes = file_get_contents($this->root.'/'.$path);

                return $bytes !== false ? $bytes : throw new DomainException('Missing staged source: '.$path);
            }
        };
        $initializer = new ProjectInitializationService($this->files, $resolver, $session->stagePath, $sources);
        $profile = $initializer->initialize($name, $brand, $themes, $deployment, $modules, $packages);
        $projected = [];
        foreach ([...array_keys($session->inventory), 'cms-project.json'] as $path) {
            if (is_file($session->stagePath.'/'.$path)) {
                $projected[$path] = hash_file('sha256', $session->stagePath.'/'.$path) ?: throw new RuntimeException('Cannot hash staged source.');
            }
        }
        $this->assertNoStrandedAdditions($session, $projected);
        $changes = $this->changes($session->root, $session->baseline, $projected);
        $identifiers = (new ProjectSourceSelection($this->files, $session->stagePath, $session->version))->identifiers($profile->sourceSelection ?? throw new DomainException('Missing projected composition.'));
        if ($beforeApply !== null) {
            $beforeApply($profile, $identifiers, $changes);
        }
        // Confirmation may take arbitrarily long. Revalidate every owned file and collision.
        if (! $this->matches($session->root, $session->baseline)) {
            throw new DomainException('Project sources changed while the bootstrap plan was being reviewed.');
        }
        $this->changes($session->root, $session->baseline, $projected);
        $this->assertStage($session, $projected);
        $this->assertNoStrandedAdditions($session, $projected);
        $backups = [];
        foreach ($changes as $change) {
            $full = $this->ordinary($session->root, $change['path']);
            $backups[$change['path']] = is_file($full) ? ['bytes' => $this->files->get($full), 'mode' => fileperms($full) & 0777] : null;
        }
        $applied = [];
        try {
            foreach ($changes as $change) {
                $applied[] = $change['path'];
                $path = $change['path'];
                if ($change['action'] === 'delete') {
                    if (! unlink($this->ordinary($session->root, $path))) {
                        throw new RuntimeException('Cannot remove official source: '.$path);
                    }
                    $this->pruneEmptyParents($session->root, $path);
                } else {
                    $this->write($session->root, $path, $this->files->get($session->stagePath.'/'.$path));
                }
            }
        } catch (\Throwable $exception) {
            foreach (array_reverse($applied) as $path) {
                $backup = $backups[$path];
                if ($backup === null) {
                    if (is_file($session->root.'/'.$path)) {
                        $this->files->delete($session->root.'/'.$path);
                    }
                } else {
                    $this->write($session->root, $path, $backup['bytes']);
                    chmod($session->root.'/'.$path, $backup['mode']);
                }
            }
            throw $exception;
        }

        return $profile;
    }

    public function cleanup(ProjectBootstrapSession $session): void
    {
        if ($session->stagePath !== '' && str_starts_with(dirname($session->stagePath), sys_get_temp_dir().'/dbcms-bootstrap-')) {
            $this->files->deleteDirectory(dirname($session->stagePath));
        }
    }

    private function pruneEmptyParents(string $root, string $path): void
    {
        for ($directory = dirname($root.'/'.$path); $directory !== $root && str_starts_with($directory, $root.'/'); $directory = dirname($directory)) {
            if (is_link($directory) || ! is_dir($directory) || (scandir($directory) ?: []) !== ['.', '..']) {
                break;
            }
            if (! rmdir($directory)) {
                throw new RuntimeException('Cannot remove empty source directory: '.$directory);
            }
        }
    }

    /** @param array<string,string> $projected */
    private function assertNoStrandedAdditions(ProjectBootstrapSession $session, array $projected): void
    {
        foreach (array_keys($session->baseline + $session->inventory) as $manifest) {
            if (! preg_match('~^(?:themes/[^/]+/theme\.json|resources/views/themes/admin/[^/]+/theme\.json|modules/[^/]+/module\.json|block-packages/[^/]+/block-package\.json)$~D', $manifest) || isset($projected[$manifest])) {
                continue;
            }
            $relative = dirname($manifest);
            $directory = $session->root.'/'.$relative;
            if (! is_dir($directory)) {
                continue;
            }
            ProjectSourceSelection::assertOrdinaryTree($directory);
            $entries = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($entries as $file) {
                $path = substr($file->getPathname(), strlen($session->root) + 1);
                $owned = isset($session->baseline[$path]);
                if ($file->isDir()) {
                    foreach (array_keys($session->baseline) as $official) {
                        if (str_starts_with($official, $path.'/')) {
                            $owned = true;
                            break;
                        }
                    }
                }
                if (! $owned) {
                    throw new DomainException('Excluded source resource contains project-owned additions; retain it or move the additions: '.$path);
                }
            }
        }
    }

    /** @return array<string,string> */
    private function declaredStableBaseline(string $root, string $latest, string $temporary): array
    {
        $path = $this->ordinary($root, 'resources/updates/core-version.json');
        if (! is_file($path)) {
            return [];
        }
        $descriptor = json_decode($this->files->get($path), true, flags: JSON_THROW_ON_ERROR);
        $version = is_array($descriptor) ? ($descriptor['version'] ?? null) : null;
        if (! is_string($version) || ! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version) || ! version_compare($version, $latest, '<')) {
            return [];
        }
        $release = $this->releases->release($version);
        $raw = $this->releases->contents($this->releases->asset($release, 'manifest.json')['browser_download_url'], 16777216);
        $signature = $this->releases->contents($this->releases->asset($release, 'manifest.sig')['browser_download_url'], 16384);
        $manifest = $this->verifier->verify($raw, $signature, config('updates.trusted_keys', []));
        if ($manifest->data['version'] !== $version) {
            throw new DomainException('Declared stable source differs from the signed release identity.');
        }
        $sha = $manifest->data['source_sha'];
        $this->sources->fetch($version, $sha, $temporary.'/baseline-git');
        $inventory = $manifest->distribution('source')['files'];
        foreach ($inventory as $path => $digest) {
            if (str_starts_with($path, 'vendor/') || hash('sha256', $this->sources->read($temporary.'/baseline-git', $sha, $path)) !== $digest) {
                throw new DomainException('Baseline Git source differs from the signed inventory: '.$path);
            }
        }

        return $inventory;
    }

    /** @param array<string,string> $inventory
     * @return list<string>
     */
    private function differingPaths(string $root, array $inventory): array
    {
        $paths = [];
        foreach ($inventory as $path => $digest) {
            if ($this->digest($root, $path) !== $digest) {
                $paths[] = $path;
            }
        }

        return array_slice($paths, 0, 20);
    }

    /** @param array<string,string> $inventory */
    private function matches(string $root, array $inventory): bool
    {
        $matches = true;
        foreach ($inventory as $path => $digest) {
            if ($this->digest($root, $path) !== $digest) {
                $matches = false;
            }
        }

        return $matches;
    }

    /**
     * @param  array<string,string>  $baseline
     * @param  array<string,string>  $target
     * @return list<array{path:string,action:string}>
     */
    private function changes(string $root, array $baseline, array $target): array
    {
        $changes = [];
        foreach (array_unique([...array_keys($baseline), ...array_keys($target)]) as $path) {
            $local = $this->digest($root, $path);
            if (! isset($baseline[$path]) && $local !== null) {
                throw new DomainException('Official source collides with a project-owned file: '.$path);
            }
            if (($target[$path] ?? null) !== $local) {
                $changes[] = ['path' => $path, 'action' => ! isset($target[$path]) ? 'delete' : ($local === null ? 'add' : 'replace')];
            }
        }

        return $changes;
    }

    /** @param array<string,string> $projected */
    private function assertStage(ProjectBootstrapSession $session, array $projected): void
    {
        if (! $this->matches($session->stagePath, $projected)) {
            throw new DomainException('Staged project sources changed while the plan was being reviewed.');
        }
    }

    private function digest(string $root, string $path): ?string
    {
        (new UpdatePathPolicy)->requireAllowed($path);
        $file = $this->ordinary($root, $path);

        return is_file($file) ? (hash_file('sha256', $file) ?: throw new RuntimeException('Cannot read project source.')) : null;
    }

    private function ordinary(string $root, string $path): string
    {
        if (! is_dir($root) || is_link($root)) {
            throw new DomainException('Project root must be an ordinary directory.');
        }
        for ($ancestor = $root; $ancestor !== dirname($ancestor); $ancestor = dirname($ancestor)) {
            if (is_link($ancestor)) {
                throw new DomainException('Project root cannot traverse symbolic links.');
            }
        }
        $cursor = $root;
        foreach (explode('/', $path) as $component) {
            if (is_dir($cursor) && ! is_link($cursor)) {
                foreach (scandir($cursor) ?: [] as $entry) {
                    if (strtolower($entry) === strtolower($component) && $entry !== $component) {
                        throw new DomainException('Source path has a case-insensitive collision: '.$path);
                    }
                }
            }
            $cursor .= '/'.$component;
        }
        $full = $root.'/'.$path;
        for ($ancestor = $full; strlen($ancestor) >= strlen($root); $ancestor = dirname($ancestor)) {
            if (is_link($ancestor) || ($ancestor !== $full && file_exists($ancestor) && ! is_dir($ancestor))) {
                throw new DomainException('Source paths may not traverse symbolic links or file parents: '.$path);
            }
            if ($ancestor === $root) {
                break;
            }
        }
        if (file_exists($full) && ! is_file($full)) {
            throw new DomainException('Source path is not an ordinary file: '.$path);
        }

        return $full;
    }

    private function write(string $root, string $path, string $bytes): void
    {
        (new UpdatePathPolicy)->requireAllowed($path);
        $full = $this->ordinary($root, $path);
        $this->files->ensureDirectoryExists(dirname($full));
        $this->files->replace($full, $bytes);
        if (str_starts_with($path, 'bin/') || $path === 'artisan') {
            chmod($full, 0755);
        }
    }

    private function resume(string $root): ProjectProfile
    {
        $data = json_decode($this->files->get($root.'/cms-project.json'), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! is_string($data['name'] ?? null) || ! is_string($data['brand'] ?? null) || ! is_array($data['themes'] ?? null) || ! array_is_list($data['themes']) || $data['themes'] === [] || ! is_string($data['deployment'] ?? null)) {
            throw new DomainException('Invalid initialized project profile.');
        }
        foreach ($data['themes'] as $theme) {
            if (! is_string($theme) || ! preg_match('/^[a-z][a-z0-9-]*$/D', $theme)) {
                throw new DomainException('Invalid initialized theme selection.');
            }
        }
        $resolver = new class implements SourceRevisionResolverInterface
        {
            public function resolve(string $version): string
            {
                throw new DomainException('An initialized profile must retain its pinned source.');
            }
        };

        // Existing initialization validates schema, provenance, physical composition and dependencies.
        return (new ProjectInitializationService($this->files, $resolver, $root, $this->sources))->initialize($data['name'], $data['brand'], implode(',', $data['themes']), $data['deployment']);
    }
}
