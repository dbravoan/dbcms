<?php

declare(strict_types=1);

namespace App\Services\ProjectUpdates;

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\ProjectUpdateServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Core\Updates\ProjectDeploymentPolicy;
use App\Core\Updates\ProjectUpdatePlanner;
use App\Core\Updates\ReleaseManifest;
use App\Core\Updates\SignedManifestVerifier;
use App\Core\Updates\UpdatePathPolicy;
use App\Core\Updates\UpdateRunStore;
use App\DTOs\ProjectUpdatePlan;
use App\Services\Updates\GithubReleaseGateway;
use Illuminate\Filesystem\Filesystem;

final class ProjectUpdateService implements ProjectUpdateServiceInterface
{
    public function __construct(private readonly GithubReleaseGateway $releases, private readonly SignedManifestVerifier $verifier, private readonly ProjectSourceRepositoryInterface $sources, private readonly UpdateRunStore $store, private readonly UpdateMigrationServiceInterface $migrations) {}

    public function prepare(string $root, string $version): ProjectUpdatePlan
    {
        $this->review($root);
        $profile = $this->profile($root);
        if (! preg_match('/^\d+\.\d+\.\d+$/D', $version) || version_compare($version, $profile['core']['version'], '<=')) {
            throw new \DomainException('Select a stable version newer than the recorded dbCMS base.');
        }
        [$target, $raw, $signature] = $this->manifest($version);
        [$base] = $this->manifest($profile['core']['version']);
        if ($base->data['source_sha'] !== $profile['core']['sha'] || ! in_array($profile['core']['version'], $target->data['supported_from'], true)) {
            throw new \DomainException('Unknown baseline or unsupported upgrade path.');
        }
        $baseFiles = $base->distribution('source')['files'];
        $targetFiles = $target->distribution('source')['files'];
        $this->requireSelectedThemes($root, $baseFiles, $targetFiles, $profile);
        foreach ($baseFiles as $path => $hash) {
            if (str_starts_with($path, 'resources/updates/trusted-keys/') && ($targetFiles[$path] ?? null) !== $hash) {
                throw new \DomainException('Historical trusted keys cannot be removed or replaced.');
            }
        }
        foreach (['cms-project.json', 'resources/updates/installed-manifest.json', 'resources/updates/installed-manifest.sig', 'resources/updates/project-update.json'] as $path) {
            $this->ordinaryPath($root, $path);
        }
        $directory = sys_get_temp_dir().'/dbcms-git-update-'.bin2hex(random_bytes(12));
        try {
            $this->sources->fetch($base->data['version'], $base->data['source_sha'], $directory);
            $this->sources->fetch($version, $target->data['source_sha'], $directory);
            $contents = [];
            $local = [];
            foreach (array_unique([...array_keys($baseFiles), ...array_keys($targetFiles)]) as $path) {
                // Validate the complete upstream inventory before touching the checkout.
                foreach ([[$base, $baseFiles], [$target, $targetFiles]] as [$manifest, $inventory]) {
                    if (isset($inventory[$path])) {
                        $bytes = $this->sources->read($directory, $manifest->data['source_sha'], $path);
                        if (hash('sha256', $bytes) !== $inventory[$path]) {
                            throw new \DomainException('Git source differs from the signed inventory: '.$path);
                        }
                        if ($manifest === $target) {
                            $contents[$path] = $bytes;
                        }
                    }
                }
                $digest = $this->digest($root, $path);
                if ($digest !== null) {
                    $local[$path] = $digest;
                }
            }
            $plan = (new ProjectUpdatePlanner)->compare($baseFiles, $local, $targetFiles, $profile);
            foreach ($plan->changes as $change) {
                $path = $this->ordinaryPath($root, $change['path']);
                if ($change['action'] === 'delete') {
                    if (! unlink($path)) {
                        throw new \RuntimeException('Cannot remove '.$change['path']);
                    }
                } else {
                    $this->write($root, $change['path'], $contents[$change['path']]);
                    if (str_starts_with($change['path'], 'bin/')) {
                        chmod($path, 0755);
                    }
                }
            }
            $previous = $profile['core'];
            $profile['core'] = ['repository' => 'dbravoan/dbcms', 'version' => $version, 'sha' => $target->data['source_sha']];
            $this->write($root, 'cms-project.json', $this->json($profile));
            $this->write($root, 'resources/updates/installed-manifest.json', $raw);
            $this->write($root, 'resources/updates/installed-manifest.sig', $signature);
            $this->write($root, 'resources/updates/project-update.json', $this->json(['schema' => 1, 'base' => $previous, 'target' => $profile['core'], 'changes' => $plan->changes, 'conflicts' => $plan->conflicts, 'preserved' => $plan->preserved, 'excluded' => $plan->excluded]));

            return $plan;
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }

    public function review(string $root): void
    {
        $reportPath = $this->ordinaryPath($root, 'resources/updates/project-update.json');
        if (! is_file($reportPath)) {
            return;
        }
        $report = $this->readJson($reportPath);
        $profile = $this->profile($root);
        $manifest = $this->installedManifest($root);
        if (($report['schema'] ?? null) !== 1 || ($report['conflicts'] ?? null) !== [] || ($report['target'] ?? null) !== $profile['core'] || $manifest->data['source_sha'] !== $profile['core']['sha'] || $manifest->data['version'] !== $profile['core']['version']) {
            throw new \DomainException('Resolve and acknowledge every conflict in project-update.json before merging.');
        }
        $descriptor = 'resources/updates/core-version.json';
        $expected = $manifest->distribution('source')['files'][$descriptor] ?? null;
        if ($expected === null || $this->digest($root, $descriptor) !== $expected) {
            throw new \DomainException('The deployed core descriptor must match the signed target, independently of the application version.');
        }
    }

    public function reconcile(string $root): void
    {
        if (! is_file($this->ordinaryPath($root, 'resources/updates/project-update.json'))) {
            throw new \DomainException('Prepare and review a signed project update before reconciliation.');
        }
        $this->review($root);
        if (! is_file($root.'/storage/installed')) {
            throw new \DomainException('Finish the web installation before reconciling an update.');
        }
        $profile = $this->profile($root);
        $manifest = $this->installedManifest($root);
        if ($this->migrations->pending($manifest->data) !== []) {
            throw new \DomainException('Pending migrations prevent update reconciliation.');
        }
        $customizations = [];
        $excluded = [];
        $files = $manifest->distribution('source')['files'];
        foreach ($files as $path => $hash) {
            if (! (new ProjectUpdatePlanner)->includes($path, $profile)) {
                $excluded[] = $path;
            } elseif ($this->digest($root, $path) !== $hash) {
                $customizations[] = $path;
            }
        }
        $identity = ['version' => $manifest->data['version'], 'source_sha' => $manifest->data['source_sha'], 'distribution' => 'source', 'files' => $files, 'manifest_raw' => file_get_contents($root.'/resources/updates/installed-manifest.json'), 'signature_raw' => file_get_contents($root.'/resources/updates/installed-manifest.sig'), 'customizations' => $customizations, 'excluded' => $excluded, 'adopted_at' => gmdate(DATE_ATOM), 'managed_project' => $profile['name']];
        $this->store->atomic($this->store->root().'/identity.json', $this->json($identity));
    }

    /** @return array{ReleaseManifest,string,string} */
    private function manifest(string $version): array
    {
        $release = $this->releases->release($version);
        $raw = $this->releases->contents($this->releases->asset($release, 'manifest.json')['browser_download_url'], 16777216);
        $signature = $this->releases->contents($this->releases->asset($release, 'manifest.sig')['browser_download_url'], 16384);
        $manifest = $this->verify($raw, $signature);
        if ($manifest->data['version'] !== $version || version_compare(PHP_VERSION, $manifest->data['min_php'], '<')) {
            throw new \DomainException('Release identity or PHP requirement is not satisfied.');
        }
        foreach ($manifest->data['extensions'] as $extension) {
            if (! extension_loaded($extension)) {
                throw new \DomainException('Required PHP extension: '.$extension);
            }
        }

        return [$manifest, $raw, $signature];
    }

    private function installedManifest(string $root): ReleaseManifest
    {
        foreach (['json', 'sig'] as $extension) {
            $file = $this->ordinaryPath($root, 'resources/updates/installed-manifest.'.$extension);
            if (! is_file($file) || is_link($file)) {
                throw new \DomainException('Missing signed project update manifest.');
            }
        }

        return $this->verify((string) file_get_contents($root.'/resources/updates/installed-manifest.json'), (string) file_get_contents($root.'/resources/updates/installed-manifest.sig'));
    }

    private function verify(string $raw, string $signature): ReleaseManifest
    {
        return $this->verifier->verify($raw, $signature, config('updates.trusted_keys', []));
    }

    /** @return array<string,mixed> */
    private function profile(string $root): array
    {
        $profile = $this->readJson($this->ordinaryPath($root, 'cms-project.json'));
        $core = $profile['core'] ?? null;
        if (($profile['schema'] ?? null) !== 1 || ! is_string($profile['name'] ?? null) || ! in_array($profile['brand'] ?? null, ['dbcms', 'termocms'], true) || ! is_array($profile['themes'] ?? null) || $profile['themes'] === [] || ! is_array($core) || ($core['repository'] ?? null) !== 'dbravoan/dbcms' || ! is_string($core['version'] ?? null) || ! preg_match('/^\d+\.\d+\.\d+$/D', $core['version']) || ! is_string($core['sha'] ?? null) || ! preg_match('/^[a-f0-9]{40}$/D', $core['sha'])) {
            throw new \DomainException('Initialize a valid independent project first.');
        }
        if (! preg_match('/^[a-z][a-z0-9]*(?:-[a-z0-9]+)*$/D', $profile['name']) || ! array_is_list($profile['themes']) || count(array_unique($profile['themes'], SORT_REGULAR)) !== count($profile['themes']) || ! is_string($profile['default_theme'] ?? null) || ! in_array($profile['default_theme'], $profile['themes'], true)) {
            throw new \DomainException('Invalid project name or theme selection.');
        }
        foreach ($profile['themes'] as $theme) {
            if (! is_string($theme) || ! preg_match('/^[a-z][a-z0-9-]*$/D', $theme)) {
                throw new \DomainException('Invalid project theme identifier.');
            }
        }
        if (! in_array($profile['deployment'] ?? null, ProjectDeploymentPolicy::DRIVERS, true)) {
            throw new \DomainException('Invalid project deployment selection.');
        }

        (new ProjectUpdatePlanner)->validateSourceSelection($profile);

        return $profile;
    }

    /** @return array<string,mixed> */
    private function readJson(string $path): array
    {
        if (! is_file($path) || is_link($path)) {
            throw new \DomainException('Missing ordinary project metadata file.');
        }
        $data = json_decode((string) file_get_contents($path), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new \DomainException('Invalid project metadata.');
        }

        return $data;
    }

    private function digest(string $root, string $path): ?string
    {
        (new UpdatePathPolicy)->requireAllowed($path);
        $full = $this->ordinaryPath($root, $path);
        if (! file_exists($full)) {
            return null;
        }

        return hash_file('sha256', $full) ?: throw new \RuntimeException('Unreadable project file.');
    }

    private function ordinaryPath(string $root, string $path): string
    {
        if (! is_dir($root) || is_link($root)) {
            throw new \DomainException('Project root must be an ordinary directory.');
        }
        for ($ancestor = $root; $ancestor !== dirname($ancestor); $ancestor = dirname($ancestor)) {
            if (is_link($ancestor)) {
                throw new \DomainException('Project root cannot traverse symbolic links.');
            }
        }
        for ($ancestor = $root.'/'.$path; strlen($ancestor) >= strlen($root); $ancestor = dirname($ancestor)) {
            if (is_link($ancestor)) {
                throw new \DomainException('Project source paths cannot traverse symbolic links: '.$path);
            }
            if ($ancestor !== $root.'/'.$path && file_exists($ancestor) && ! is_dir($ancestor)) {
                throw new \DomainException('Project path parent is not a directory: '.$path);
            }
            if ($ancestor === $root) {
                break;
            }
        }
        $full = $root.'/'.$path;
        if (file_exists($full) && ! is_file($full)) {
            throw new \DomainException('Project source path is not a file: '.$path);
        }

        return $full;
    }

    private function write(string $root, string $relativePath, string $bytes): void
    {
        $path = $this->ordinaryPath($root, $relativePath);
        (new Filesystem)->ensureDirectoryExists(dirname($path));
        (new Filesystem)->replace($path, $bytes);
    }

    /**
     * @param  array<string,string>  $base
     * @param  array<string,string>  $target
     * @param  array<string,mixed>  $profile
     */
    private function requireSelectedThemes(string $root, array $base, array $target, array $profile): void
    {
        $required = ['resources/views/themes/admin/'.$profile['brand'].'/theme.json'];
        foreach ($profile['themes'] as $theme) {
            $path = 'themes/'.$theme.'/theme.json';
            if (isset($base[$path])) {
                $required[] = $path;
            } elseif (! is_file($this->ordinaryPath($root, $path))) {
                throw new \DomainException('Project-owned selected theme is missing: '.$path);
            }
        }
        foreach ($required as $path) {
            if (! isset($target[$path])) {
                throw new \DomainException('The target release no longer contains the selected theme: '.$path);
            }
        }
    }

    /** @param array<string,mixed> $data */
    private function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n";
    }
}
