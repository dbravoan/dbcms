<?php

declare(strict_types=1);

namespace Modules\Orchestration;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Contracts\PlannerInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Http\Middleware\AuthenticateMcpPassport;
use Illuminate\Support\Facades\Route;
use Modules\AiAssistant\Http\Middleware\EnsureAiAuthorized;
use Modules\Orchestration\Services\CmsNativePlanner;
use Modules\Orchestration\Services\OrchestrationCapabilities;

final class OrchestrationModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'orchestration';

    protected string $name = 'Orchestration';

    public function register(): void
    {
        $this->app->bind(PlannerInterface::class, CmsNativePlanner::class);
    }

    public function boot(): void
    {
        parent::boot();
        Route::prefix('api/orchestration')->name('api.orchestration.')->middleware(['api', 'module.enabled:orchestration', AuthenticateMcpPassport::class.':ai:use', EnsureAiAuthorized::class.':use:ai'])->group(__DIR__.'/Routes/orchestration.php');
        $this->app->booted(fn () => $this->app->make(OrchestrationCapabilities::class)->register($this->app->make(CapabilityRegistry::class)));
    }
}
