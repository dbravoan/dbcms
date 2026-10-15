<?php

declare(strict_types=1);

namespace App\Services\ProjectUpdates;

use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Core\Updates\UpdatePathPolicy;
use Symfony\Component\Process\Process;

final class GitProjectSourceRepository implements ProjectSourceRepositoryInterface
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
