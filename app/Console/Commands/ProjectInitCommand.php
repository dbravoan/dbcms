<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\ProjectInitializationServiceInterface;
use Illuminate\Console\Command;
use Throwable;

final class ProjectInitCommand extends Command
{
    protected $signature = 'cms:project:init {name} {--brand= : dbcms or termocms} {--theme= : all, one ID or a CSV list} {--deploy= : github-actions, ansistrano, rsync, docker or none (required)}';

    protected $description = 'Prepare an independent project with a fixed brand and public theme selection';

    public function handle(ProjectInitializationServiceInterface $service): int
    {
        try {
            $profile = $service->initialize((string) $this->argument('name'), (string) $this->option('brand'), (string) $this->option('theme'), (string) $this->option('deploy'));
            $this->info('Project '.$profile->name.' prepared; upstream '.$profile->coreVersion.' at '.$profile->coreSha);

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
