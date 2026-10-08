<?php

declare(strict_types=1);

namespace Modules\ContentAudit;

use App\Core\Capabilities\CapabilityRegistry;
use App\Core\Capabilities\Contracts\ContentRevisionInspectorInterface;
use App\Core\Capabilities\Resources\CapabilityResourceRegistry;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Modules\BaseModuleServiceProvider;
use App\Core\Notifications\NotificationRecipientPolicyRegistry;
use App\Services\Capabilities\CmsActionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use Modules\Blog\Models\Post;
use Modules\ContentAudit\Capabilities\AuditResources;
use Modules\ContentAudit\Capabilities\EditorialActionCatalog;
use Modules\ContentAudit\Contracts\Services\AuditWorkbenchServiceInterface;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\ContentAudit\Contracts\Services\DiffEngineServiceInterface;
use Modules\ContentAudit\Contracts\Services\EditorialVisibilityInterface;
use Modules\ContentAudit\Models\ContentAudit;
use Modules\ContentAudit\Models\ContentAuditRevision;
use Modules\ContentAudit\Observers\ContentAuditObserver;
use Modules\ContentAudit\Services\AuditNotificationRecipientPolicy;
use Modules\ContentAudit\Services\AuditWorkbenchService;
use Modules\ContentAudit\Services\ContentAuditService;
use Modules\ContentAudit\Services\ContentRevisionInspector;
use Modules\ContentAudit\Services\DiffEngineService;
use Modules\ContentAudit\Services\EditorialVisibility;
use Modules\ContentAudit\Services\EditorialWorkflowAdapter;
use Modules\Gallery\Models\Gallery;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;

class ContentAuditModuleServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'content_audit';

    protected string $name = 'Auditoría de Contenidos & Control de Calidad';

    protected string $version = '1.0.0';

    protected bool $isPageable = false;

    public function register(): void
    {
        $this->app->bind(ContentRevisionInspectorInterface::class, ContentRevisionInspector::class);
        $this->app->bind(EditorialWorkflowInterface::class, EditorialWorkflowAdapter::class);
        $this->app->singleton(DiffEngineServiceInterface::class, DiffEngineService::class);
        $this->app->singleton(ContentAuditServiceInterface::class, ContentAuditService::class);
        $this->app->bind(EditorialVisibilityInterface::class, EditorialVisibility::class);
        $this->app->bind(AuditWorkbenchServiceInterface::class, AuditWorkbenchService::class);
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            EditorialActionCatalog::register(
                $this->app->make(CapabilityRegistry::class),
                $this->app->make(CmsActionService::class),
            );
        });
        $this->app->make(CapabilityResourceRegistry::class)->register(AuditResources::descriptor());
        $this->app->make(NotificationRecipientPolicyRegistry::class)->register('content_audit', $this->app->make(AuditNotificationRecipientPolicy::class));
        parent::boot();

        $this->loadViewsFrom(__DIR__.'/Resources/views', 'content_audit');
        $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');

        $this->registerAuditableInference();
        $this->registerSidebarViewComposer();
    }

    protected function registerAuditableInference(): void
    {
        $auditedClasses = [
            Post::class,
            Page::class,
            Product::class,
            Gallery::class,
        ];

        foreach ($auditedClasses as $modelClass) {
            if (class_exists($modelClass)) {
                $modelClass::observe(ContentAuditObserver::class);

                $modelClass::resolveRelationUsing('contentAudit', function (Model $model) {
                    return $model->morphOne(ContentAudit::class, 'auditable');
                });

                $modelClass::resolveRelationUsing('auditRevisions', function (Model $model) {
                    return $model->morphMany(ContentAuditRevision::class, 'auditable')->orderByDesc('version');
                });
            }
        }
    }

    protected function registerSidebarViewComposer(): void
    {
        View::composer(['admin.pages._form', 'admin.blog.posts._form', 'admin.galleries._form', 'admin.shop.products._form'], function ($view): void {
            if (! $this->app->make(ContentAuditServiceInterface::class)->isEnabled()) {
                return;
            }
            $data = $view->getData();
            $entity = $data['page'] ?? $data['post'] ?? $data['gallery'] ?? $data['product'] ?? null;
            $isEdit = (bool) ($data['isEdit'] ?? false);

            if ($isEdit && $entity instanceof Model && $entity->exists) {
                $view->getFactory()->startPush(
                    'admin_form_sidebar',
                    view('content_audit::admin.partials.audit-card', ['entity' => $entity] + $this->app->make(AuditWorkbenchServiceInterface::class)->sidebar($entity, auth()->user()))->render()
                );
            }
        });
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
        return [
            [
                'title' => 'Auditoría & Calidad',
                'route' => 'admin.audit.index',
                'icon' => 'clipboard-check',
                'permission' => 'access:admin',
            ],
        ];
    }
}
