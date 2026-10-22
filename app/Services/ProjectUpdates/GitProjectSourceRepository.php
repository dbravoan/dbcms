<?php

declare(strict_types=1);

namespace App\Services\ProjectUpdates;

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\ProjectSourceSnapshotRepositoryInterface;
use App\Core\Updates\UpdatePathPolicy;
use Symfony\Component\Process\Process;

final class GitProjectSourceRepository implements ProjectSourceRepositoryInterface, ProjectSourceSnapshotRepositoryInterface
{
    public function fetch(string $version, string $sha, string $directory): void
    {
        if (! preg_match('/^\d+\.\d+\.\d+$/D', $version) || ! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            throw new \DomainException('Invalid official Git revision.');
        }
        if (! is_dir($directory)) {
            $this->run(['git', 'init', '--bare', $directory]);
        }
        $this->run(['git', '--git-dir='.$directory, '-c', 'core.hooksPath=/dev/null', 'fetch', '--depth=1', '--no-tags', 'https://github.com/dbravoan/dbcms.git', 'refs/tags/v'.$version]);
        if (trim($this->run(['git', '--git-dir='.$directory, 'rev-parse', 'FETCH_HEAD^{commit}'])) !== $sha) {
            throw new \DomainException('Official tag differs from the signed release commit.');
        }
    }

    /** @return array{sha:string,files:array<string,string>} */
    public function snapshot(string $directory, ?string $root = null): array
    {
        if (! is_dir($directory)) {
            $this->run(['git', 'init', '--bare', $directory]);
        }
        $this->run(['git', '--git-dir='.$directory, '-c', 'core.hooksPath=/dev/null', 'fetch', '--no-tags', 'https://github.com/dbravoan/dbcms.git', 'refs/heads/main']);
        $commits = preg_split('/\R/', trim($this->run(['git', '--git-dir='.$directory, 'rev-list', '--first-parent', 'FETCH_HEAD']))) ?: [];
        foreach ($commits as $sha) {
            if (! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
                throw new \DomainException('Invalid official development snapshot.');
            }
            if ($root !== null && ! $this->candidateAnchorsMatch($directory, $sha, $root)) {
                continue;
            }
            $files = $this->exportedInventory($directory, $sha);
            if ($root === null || $this->inventoryMatches($files, $root)) {
                return ['sha' => $sha, 'files' => $files];
            }
        }
        throw new \DomainException('Project origin is unverifiable or official source files have local changes.');
    }

    private function candidateAnchorsMatch(string $directory, string $sha, string $root): bool
    {
        foreach (['composer.json', 'resources/updates/core-version.json', 'bin/project-init'] as $path) {
            if (is_link($root.'/'.$path) || ! is_file($root.'/'.$path)) {
                return false;
            }
            $process = new Process(['git', '--git-dir='.$directory, 'show', $sha.':'.$path]);
            $process->setTimeout(60);
            $process->run();
            if (! $process->isSuccessful() || hash('sha256', $process->getOutput()) !== hash_file('sha256', $root.'/'.$path)) {
                return false;
            }
        }

        return true;
    }

    /** @param array<string,string> $files */
    private function inventoryMatches(array $files, string $root): bool
    {
        foreach ($files as $path => $digest) {
            for ($ancestor = $root.'/'.$path; strlen($ancestor) > strlen($root); $ancestor = dirname($ancestor)) {
                if (is_link($ancestor)) {
                    throw new \DomainException('Project source paths cannot traverse symbolic links.');
                }
            }
            if (! is_file($root.'/'.$path) || hash_file('sha256', $root.'/'.$path) !== $digest) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,string> */
    private function exportedInventory(string $directory, string $sha): array
    {
        $archive = $directory.'/snapshot-'.$sha.'.tar';
        $this->run(['git', '--git-dir='.$directory, '-c', 'core.attributesFile=/dev/null', 'archive', '--format=tar', '--output='.$archive, $sha]);
        $files = [];
        try {
            $iterator = new \RecursiveIteratorIterator(new \PharData($archive));
            foreach ($iterator as $entry) {
                $path = substr($entry->getPathname(), strlen('phar://'.$archive.'/'));
                if (! $entry->isFile() || $entry->isLink()) {
                    throw new \DomainException('Official snapshot contains non-ordinary files.');
                }
                if ((new UpdatePathPolicy)->allows($path) && ! str_starts_with($path, 'vendor/')) {
                    $files[$path] = hash('sha256', $entry->getContent());
                }
            }
        } finally {
            @unlink($archive);
        }

        return $files;
    }

    public function read(string $directory, string $sha, string $path): string
    {
        (new UpdatePathPolicy)->requireAllowed($path);
        if (! preg_match('/^[a-f0-9]{40}$/D', $sha)) {
            throw new \DomainException('Invalid source commit.');
        }
        $entry = $this->run(['git', '--git-dir='.$directory, 'ls-tree', $sha, '--', $path]);
        if (! preg_match('/^100(?:644|755) blob [a-f0-9]{40}\t/', $entry)) {
            throw new \DomainException('Source must be an ordinary tracked file: '.$path);
        }

        return $this->run(['git', '--git-dir='.$directory, 'show', $sha.':'.$path]);
    }

    /** @param list<string> $arguments */
    private function run(array $arguments): string
    {
        $process = new Process($arguments);
        $process->setTimeout(300);
        $process->mustRun();

        return $process->getOutput();
    }
}
