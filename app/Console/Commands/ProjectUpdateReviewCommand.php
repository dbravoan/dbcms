<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\Services\ProjectUpdateServiceInterface;
use Illuminate\Console\Command;

final class ProjectUpdateReviewCommand extends Command
{
    protected $signature = 'cms:project:review';

    protected $description = 'Check signed update provenance and refuse unresolved source conflicts';

    public function handle(ProjectUpdateServiceInterface $service): int
    {
        try {
            $service->review(base_path());

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
