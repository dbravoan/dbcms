<?php

declare(strict_types=1);

namespace Modules\ContentAudit;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Contracts\ContentRevisionInspectorInterface;
use App\Core\Capabilities\Resources\CapabilityResourceRegistry;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Notifications\NotificationRecipientPolicyRegistry;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Modules\ContentAudit\Capabilities\AuditResources;
use Modules\ContentAudit\Capabilities\EditorialActionCatalog;
use Modules\ContentAudit\Capabilities\EditorialActions;
use Modules\ContentAudit\Contracts\Services\AuditWorkbenchServiceInterface;
use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Models\ContentAudit;
use Modules\ContentAudit\Models\ContentAuditRevision;
use Modules\ContentAudit\Observers\ContentAuditObserver;
use Modules\ContentAudit\Services\AuditNotificationRecipientPolicy;
use Modules\ContentAudit\Services\AuditWorkbenchService;
use Modules\ContentAudit\Services\ContentRevisionInspector;
use Modules\ContentAudit\Services\DiffEngineService;
use Modules\ContentAudit\Services\EditorialPolicy;
use Modules\ContentAudit\Services\EditorialVisibility;
use Modules\ContentAudit\Services\EditorialWorkflow;

class ContentAuditModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'content_audit';

    protected string $name = 'Auditoría de Contenidos & Control de Calidad';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->bind(ContentRevisionInspectorInterface::class, ContentRevisionInspector::class);
        $this->app->bind(EditorialWorkflowInterface::class, EditorialWorkflow::class);
        $this->app->singleton(DiffEngineServiceInterface::class, DiffEngineService::class);
        $this->app->bind(EditorialVisibilityInterface::class, EditorialVisibility::class);
        $this->app->bind(AuditWorkbenchServiceInterface::class, AuditWorkbenchService::class);
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            EditorialActionCatalog::register(
                $this->app->make(CapabilityRegistry::class),
                $this->app->make(EditorialActions::class),
            );
        });
        $this->app->make(CapabilityResourceRegistry::class)->register(AuditResources::descriptor());
        $this->app->make(NotificationRecipientPolicyRegistry::class)->register('content_audit', $this->app->make(AuditNotificationRecipientPolicy::class));
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'content_audit');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->app->booted(fn () => $this->registerAuditableEntities());
    }

    protected function registerAuditableEntities(): void
    {
        foreach ($this->app->make(EntitySchemaRegistryInterface::class)->all() as $schema) {
            /** @var class-string<Model> $modelClass */
            $modelClass = $schema->model;
            $modelClass::observe(ContentAuditObserver::class);
            $modelClass::resolveRelationUsing('contentAudit', fn (Model $model) => $model->morphOne(ContentAudit::class, 'auditable'));
            $modelClass::resolveRelationUsing('auditRevisions', fn (Model $model) => $model->morphMany(ContentAuditRevision::class, 'auditable')->orderByDesc('version'));
            if ($schema->adminFormView === null) {
                continue;
            }
            View::composer($schema->adminFormView, function ($view) use ($modelClass): void {
                if (! $this->app->make(EditorialPolicy::class)->isEnabled()) {
                    return;
                }
                foreach ($view->getData() as $entity) {
                    if ($entity instanceof $modelClass && $entity->exists) {
                        $view->getFactory()->startPush('admin_form_sidebar', view('content_audit::admin.partials.audit-card', ['entity' => $entity] + $this->app->make(AuditWorkbenchServiceInterface::class)->sidebar($entity, auth()->user()))->render());
                        break;
                    }
                }
            });
        }
    }

    public function hasDedicatedPanel(): bool
    {
        return true;
    }

    public function getDedicatedPanelUrl(): ?string
    {
        return route('admin.audit.index');
    }

    public function getAdminNavigation(): array
    {
        $user = auth()->user();
        $pending = $user instanceof User ? $this->app->make(EditorialWorkflowInterface::class)->pendingReviewCount($user) : 0;

        return [
            [
                'title' => 'Auditoría & Calidad',
                'route' => 'admin.audit.index',
                'icon' => 'clipboard-check',
                'permission' => 'access:admin',
                'badge' => $pending > 0 ? (string) $pending : null,
            ],
        ];
    }
}
