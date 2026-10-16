<?php

declare(strict_types=1);

namespace Modules\Forms;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Capabilities\Resources\CapabilityResourceRegistry;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Http\Middleware\ResolveMarketAndLocaleMiddleware;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use Modules\Forms\Blocks\CustomFormBlock;
use Modules\Forms\Capabilities\FormResources;
use Modules\Forms\Contracts\FormAdministrationServiceInterface;
use Modules\Forms\Contracts\FormEditorServiceInterface;
use Modules\Forms\Http\Controllers\FormSubmitController;
use Modules\Forms\Schema\FormEntitySchemas;
use Modules\Forms\Services\FormAdministrationService;
use Modules\Forms\Services\FormEditorService;
use Modules\Forms\Services\FormPresentationService;
use Modules\Forms\Services\FormsDashboard;
use Modules\Forms\Widgets\EmbedFormWidget;

class FormsModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'forms';

    protected string $name = 'Dynamic Form Builder';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/Config/entity_search.php', 'entity_search.sources');
        $this->app->bind(FormAdministrationServiceInterface::class, FormAdministrationService::class);
        $this->app->bind(FormEditorServiceInterface::class, FormEditorService::class);
    }

    public function boot(): void
    {
        $this->app->make(BlockRegistryInterface::class)->register($this->app->make(EmbedFormWidget::class));
        $this->app->make(CapabilityResourceRegistry::class)->register(FormResources::descriptor());
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'forms');
        Blade::anonymousComponentNamespace('forms::components', 'forms');
        \Illuminate\Support\Facades\View::composer('forms::components.form-engine', function (View $view): void {
            $view->with($this->app->make(FormPresentationService::class)->data($view->getData()));
        });
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        if ($this->app->bound(BlockRegistryInterface::class)) {
            $this->app->make(BlockRegistryInterface::class)->register(new CustomFormBlock);
        }

        if ($this->app->bound(EntitySchemaRegistryInterface::class)) {
            foreach (FormEntitySchemas::all() as $schema) {
                $this->app->make(EntitySchemaRegistryInterface::class)->register($schema);
            }
        }

        // Form submission endpoints
        $adminPath = (string) config('cms.admin_path', 'admin');
        $blocked = "admin|{$adminPath}|api|forms|geo|install|media|theme-assets|up";

        Route::middleware(['web', 'module.enabled:forms', ResolveMarketAndLocaleMiddleware::class])
            ->prefix('{market}/{locale}')
            ->where(['market' => "^(?!{$blocked})[a-zA-Z0-9_\-]+$", 'locale' => '[a-zA-Z0-9_\-]+'])
            ->group(function (): void {
                Route::post('/forms/{formSlug}/submit', [FormSubmitController::class, 'submit'])
                    ->name('forms.market.submit');
            });

        Route::middleware(['web', 'module.enabled:forms'])->post('/forms/{formSlug}/submit', [FormSubmitController::class, 'submit'])
            ->name('forms.submit');
    }

    public function getAdminDashboard(User $user): ?array
    {
        return $this->app->make(FormsDashboard::class)->data($user);
    }

    public function getAdminNavigation(): array
    {
        return [
            ['title' => __('admin.nav.items.form_submissions'), 'route' => 'admin.forms.submissions', 'icon' => 'inbox', 'permission' => 'manage:forms'],
            [
                'title' => 'Forms & Leads',
                'route' => 'admin.forms.index',
                'icon' => 'inbox',
                'permission' => 'manage:forms',
            ],
        ];
    }
}
