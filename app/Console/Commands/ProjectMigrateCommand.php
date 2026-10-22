<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\ProjectMigrationServiceInterface;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;

final class ProjectMigrateCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'cms:project:migrate {--force : Run the installed project migrations in production}';

    protected $description = 'Migrate project core, Passport and installed modules without installing optional modules';

    public function handle(ProjectMigrationServiceInterface $service): int
    {
        try {
            if (! $this->confirmToProceed()) {
                return self::FAILURE;
            }
            $service->migrate(base_path());
            $this->info('Project migrations completed.');

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
