<?php

declare(strict_types=1);

namespace App\Core\Updates;

use InvalidArgumentException;

final class ProjectDeploymentPolicy
{
    public const DRIVERS = ['github-actions', 'ansistrano', 'rsync', 'docker', 'none'];

    /** @var array<string,list<string>> */
    private const CONTROLS = [
        '.github/workflows/release.yml' => [],
        '.github/workflows/docker-publish.yml' => [],
        '.github/dependabot.yml' => ['github-actions'],
        '.github/workflows/prepare-project.yml' => [],
        '.github/workflows/ci.yml' => ['github-actions'],
        '.github/workflows/update-dbcms.yml' => ['github-actions'],
        '.github/workflows/deploy.yml' => ['github-actions'],
        '.github/workflows/deploy-ansistrano.yml' => [],
        '.github/workflows/deploy-docker.yml' => [],
        'bin/deploy-vps-release.sh' => ['github-actions', 'rsync', 'ansistrano'],
        'bin/vps-artisan.sh' => ['github-actions', 'rsync', 'ansistrano'],
        'bin/vps-install.sh' => ['github-actions', 'rsync', 'ansistrano'],
        'bin/vps-preflight.sh' => ['github-actions', 'rsync', 'ansistrano'],
        'bin/deploy-rsync' => ['rsync'],
        'bin/deploy-ansistrano' => ['ansistrano'],
        'bin/deploy-local-common.sh' => ['rsync', 'ansistrano', 'docker'],
        'bin/deploy-container.sh' => ['docker'],
        'bin/deploy-docker' => ['docker'],
        'docker-compose.prod.yml' => ['docker'],
        'bin/vps-bootstrap.sh' => ['github-actions', 'rsync', 'ansistrano', 'docker'],
    ];

    public function includes(string $path, string $deployment): bool
    {
        if (! in_array($deployment, self::DRIVERS, true)) {
            throw new InvalidArgumentException('Select --deploy=github-actions, ansistrano, rsync, docker or none.');
        }
        if ($deployment !== 'github-actions' && str_starts_with($path, '.github/')) {
            return false;
        }
        $owners = self::CONTROLS[$path] ?? (str_starts_with($path, 'deploy/ansistrano/') ? ['ansistrano'] : null);

        return $owners === null || in_array($deployment, $owners, true);
    }

    /** @return list<string> Existing official files excluded by the selection. */
    public function removals(string $root, string $deployment): array
    {
        $paths = array_keys(self::CONTROLS);
        $this->ordinary($root, 'deploy/ansistrano', true);
        if (is_dir($root.'/deploy/ansistrano')) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/deploy/ansistrano', \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $file) {
                $relative = substr($file->getPathname(), strlen($root) + 1);
                $this->ordinary($root, $relative, $file->isDir());
                if ($file->isFile()) {
                    $paths[] = $relative;
                }
            }
        }
        $removals = [];
        foreach ($paths as $path) {
            $this->ordinary($root, $path);
            if (! $this->includes($path, $deployment) && is_file($root.'/'.$path)) {
                $removals[] = $path;
            }
        }
        sort($removals);

        return $removals;
    }

    private function ordinary(string $root, string $relative, bool $directory = false): void
    {
        $path = $root.'/'.$relative;
        for ($ancestor = $path; strlen($ancestor) >= strlen($root); $ancestor = dirname($ancestor)) {
            if (is_link($ancestor) || ($ancestor !== $path && file_exists($ancestor) && ! is_dir($ancestor))) {
                throw new InvalidArgumentException('Deployment controls may not traverse symbolic links or non-directory parents.');
            }
            if ($ancestor === $root) {
                break;
            }
        }
        if (file_exists($path) && ($directory ? ! is_dir($path) : ! is_file($path))) {
            throw new InvalidArgumentException('Deployment control has an unexpected filesystem type: '.$relative);
        }
    }
}
