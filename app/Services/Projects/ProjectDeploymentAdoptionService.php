<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Services\ProjectDeploymentAdoptionServiceInterface;
use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Core\Updates\SignedManifestVerifier;
use App\Services\Updates\GithubReleaseGateway;
use Illuminate\Filesystem\Filesystem;

final class ProjectDeploymentAdoptionService implements ProjectDeploymentAdoptionServiceInterface
{
    public function __construct(private readonly GithubReleaseGateway $releases, private readonly SignedManifestVerifier $verifier, private readonly ProjectSourceRepositoryInterface $sources) {}

    public function assertRemovable(string $root, array $core, array $paths): void
    {
        $release = $this->releases->release($core['version']);
        $raw = $this->releases->contents($this->releases->asset($release, 'manifest.json')['browser_download_url'], 16777216);
        $signature = $this->releases->contents($this->releases->asset($release, 'manifest.sig')['browser_download_url'], 16384);
        $manifest = $this->verifier->verify($raw, $signature, config('updates.trusted_keys', []));
        if ($manifest->data['version'] !== $core['version'] || $manifest->data['source_sha'] !== $core['sha']) {
            throw new \DomainException('The signed baseline does not match this legacy project.');
        }
        $directory = sys_get_temp_dir().'/dbcms-deployment-adoption-'.bin2hex(random_bytes(12));
        try {
            $this->sources->fetch($core['version'], $core['sha'], $directory);
            $inventory = $manifest->distribution('source')['files'];
            foreach ($paths as $path) {
                $expected = $inventory[$path] ?? null;
                if ($expected === null || ! is_file($root.'/'.$path) || is_link($root.'/'.$path) || hash_file('sha256', $root.'/'.$path) !== $expected || hash('sha256', $this->sources->read($directory, $core['sha'], $path)) !== $expected) {
                    throw new \DomainException('Deployment selection would remove a customized or unproven control: '.$path.'. Review it against the signed baseline first.');
                }
            }
        } finally {
            (new Filesystem)->deleteDirectory($directory);
        }
    }
}
