<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\ProjectUpdateServiceInterface;
use Illuminate\Console\Command;

final class ProjectUpdateReconcileCommand extends Command
{
    protected $signature = 'cms:project:reconcile';

    protected $description = 'Record the deployed signed core base and retained project customizations after health verification';

    public function handle(ProjectUpdateServiceInterface $service): int
    {
        try {
            $service->reconcile(base_path());
            $this->info('Deployed dbCMS base reconciled.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
