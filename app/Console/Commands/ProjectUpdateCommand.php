<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\ProjectUpdateServiceInterface;
use Illuminate\Console\Command;

final class ProjectUpdateCommand extends Command
{
    protected $signature = 'cms:project:update {version : Stable dbCMS release version} {--working-copy : Explicitly prepare a separate development copy without a Git repository}';

    protected $description = 'Prepare verified upstream sources in a development branch or separate working copy for human review';

    public function handle(ProjectUpdateServiceInterface $service): int
    {
        try {
            if ($this->laravel->environment('production') || (! file_exists(base_path('.git')) && ! $this->option('working-copy'))) {
                throw new \DomainException('Use a development Git branch or a separate copy with --working-copy, never production.');
            }
            $plan = $service->prepare(base_path(), (string) $this->argument('version'));
            $this->info(count($plan->changes).' changes prepared; '.count($plan->conflicts).' conflicts require review.');

            return $plan->conflicts === [] ? self::SUCCESS : 2;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
