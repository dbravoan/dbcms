<?php

declare(strict_types=1);

namespace App\Services\Projects;

use App\Contracts\Services\SourceRevisionResolverInterface;
use RuntimeException;
use Symfony\Component\Process\Process;

final class GitSourceRevisionResolver implements SourceRevisionResolverInterface
{
    public function resolve(string $version): string
    {
        if (! preg_match('/^[0-9]+\.[0-9]+\.[0-9]+$/D', $version)) {
            throw new RuntimeException('An official stable version is required.');
        }
        $process = new Process(['git', 'ls-remote', 'https://github.com/dbravoan/dbcms.git', 'refs/tags/v'.$version, 'refs/tags/v'.$version.'^{}']);
        $process->setTimeout(60);
        $process->mustRun();
        $refs = [];
        foreach (preg_split('/\R/', trim($process->getOutput())) ?: [] as $line) {
            if (preg_match('/^([a-f0-9]{40})\s+(refs\/tags\/v[0-9.]+(?:\^\{\})?)$/D', $line, $matches)) {
                $refs[$matches[2]] = $matches[1];
            }
        }

        return $refs['refs/tags/v'.$version.'^{}'] ?? $refs['refs/tags/v'.$version] ?? throw new RuntimeException('Official source tag unavailable; no project files were removed.');
    }
}
