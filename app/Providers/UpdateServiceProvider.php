<?php

declare(strict_types=1);

namespace App\Providers;

use App\Console\Commands\UpdatesCommand;
use App\Contracts\Services\ProjectMigrationServiceInterface;
use App\Contracts\Services\ProjectSourceRepositoryInterface;
use App\Contracts\Services\ProjectUpdateServiceInterface;
use App\Contracts\Services\UpdateBackupServiceInterface;
use App\Contracts\Services\UpdateCatalogServiceInterface;
use App\Contracts\Services\UpdateDeploymentServiceInterface;
use App\Contracts\Services\UpdateExecutionServiceInterface;
use App\Contracts\Services\UpdateHistoryServiceInterface;
use App\Contracts\Services\UpdateManagedReconcilerServiceInterface;
use App\Contracts\Services\UpdateMigrationServiceInterface;
use App\Contracts\Services\UpdatePreflightServiceInterface;
use App\Contracts\Services\UpdatePreparationServiceInterface;
use App\Core\Updates\QueueUpdateAdmission;
use App\Core\Updates\UpdateDeploymentClassifier;
use App\Models\User;
use App\Services\ProjectUpdates\GitProjectSourceRepository;
use App\Services\ProjectUpdates\ProjectMigrationService;
use App\Services\ProjectUpdates\ProjectUpdateService;
use App\Services\Updates\MysqlUpdateBackupService;
use App\Services\Updates\UpdateCatalogService;
use App\Services\Updates\UpdateExecutionService;
use App\Services\Updates\UpdateHistoryService;
use App\Services\Updates\UpdateManagedReconcilerService;
use App\Services\Updates\UpdateMigrationService;
use App\Services\Updates\UpdatePreflightService;
use App\Services\Updates\UpdatePreparationService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobAttempted;
use Illuminate\Queue\Events\JobPopping;
use Illuminate\Queue\Events\Looping;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

final class UpdateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ProjectSourceRepositoryInterface::class, GitProjectSourceRepository::class);
        $this->app->singleton(ProjectMigrationServiceInterface::class, ProjectMigrationService::class);
        $this->app->singleton(ProjectUpdateServiceInterface::class, ProjectUpdateService::class);
        $this->app->singleton(QueueUpdateAdmission::class);
        $this->app->singleton(UpdateDeploymentServiceInterface::class, UpdateDeploymentClassifier::class);
        $this->app->singleton(UpdateHistoryServiceInterface::class, UpdateHistoryService::class);
        $this->app->singleton(UpdateCatalogServiceInterface::class, UpdateCatalogService::class);
        $this->app->singleton(UpdateBackupServiceInterface::class, MysqlUpdateBackupService::class);
        $this->app->singleton(UpdateMigrationServiceInterface::class, UpdateMigrationService::class);
        $this->app->singleton(UpdateManagedReconcilerServiceInterface::class, UpdateManagedReconcilerService::class);
        $this->app->singleton(UpdatePreflightServiceInterface::class, UpdatePreflightService::class);
        $this->app->singleton(UpdatePreparationServiceInterface::class, UpdatePreparationService::class);
        $this->app->singleton(UpdateExecutionServiceInterface::class, UpdateExecutionService::class);
    }

    public function boot(): void
    {
        Event::listen(JobPopping::class, fn (JobPopping $event) => $this->app->make(QueueUpdateAdmission::class)->beforePop((string) $event->connectionName));
        Event::listen(JobAttempted::class, fn (JobAttempted $event) => $this->app->make(QueueUpdateAdmission::class)->attempted((string) $event->connectionName));
        Event::listen(Looping::class, fn () => $this->app->make(QueueUpdateAdmission::class)->looping());
        Gate::define('manage:updates', fn (User $user): bool => $user->hasPermission('manage:updates'));
        $this->loadRoutesFrom(base_path('routes/updates.php'));
        $this->commands([UpdatesCommand::class]);
        $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
            $schedule->command('cms:updates check')->twiceDaily()->withoutOverlapping();
        });
        $this->app->make(ExceptionHandler::class)->renderable(function (\DomainException $exception, Request $request) {
            if ($request->routeIs('admin.updates.*')) {
                return response()->json(['message' => $exception->getMessage(), 'code' => 'update_conflict'], 409);
            }

            return null;
        });
    }
}
