<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Contracts\Services\AdminNavigationServiceInterface;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Modules\Contracts\MaintenanceModeInterface;
use App\Core\Modules\ModuleManager;
use App\DTOs\Admin\AdminNavigationItem;
use App\DTOs\Admin\AdminNavigationSection;
use App\Models\CmsModule;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Modules\Shop\Models\ShopSetting;

class AdminNavigationService implements AdminNavigationServiceInterface
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getActiveModuleIdentifiers(): array
    {
        try {
            if (Schema::hasTable('cms_modules')) {
                $modules = CmsModule::all();
                $enabled = [];
                foreach ($modules as $module) {
                    $id = (string) $module->identifier;
                    if ($this->moduleManager->isModuleEnabled($id)) {
                        $enabled[] = strtolower($id);
                    }
                }

                return $enabled;
            }
        } catch (\Throwable) {
            // DB not ready or migrated
        }

        return [];
    }

    /**
     * {@inheritDoc}
     */
    public function isModuleVisible(string $identifier): bool
    {
        $normalized = strtolower($identifier);

        return in_array($normalized, $this->getActiveModuleIdentifiers(), true);
    }

    /**
     * {@inheritDoc}
     */
    public function getPendingAuditCount(): int
    {
        if (! $this->isModuleVisible('content_audit')) {
            return 0;
        }

        try {
            $actor = auth()->user();
            if ($actor instanceof User) {
                return app(EditorialWorkflowInterface::class)->pendingReviewCount($actor);
            }
        } catch (\Throwable) {
            // Table or model issue
        }

        return 0;
    }

    /**
     * {@inheritDoc}
     */
    public function isMaintenanceActive(): bool
    {
        try {
            return app(MaintenanceModeInterface::class)->isActive();
        } catch (\Throwable) {
            // Maintenance service unavailable
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getShopMode(): string
    {
        if (! $this->isModuleVisible('shop')) {
            return 'none';
        }

        try {
            if (class_exists(ShopSetting::class)) {
                return (string) ShopSetting::get('shop.mode', 'full_shop');
            }
        } catch (\Throwable) {
            // Settings store not ready
        }

        return 'full_shop';
    }

    /**
     * {@inheritDoc}
     */
    public function getNavigationSections(?User $user = null): array
    {
        /** @var User|null $user */
        $user = $user ?? auth()->user();

        $context = $this->permissionContext($user);

        return array_values(array_filter([
            $this->generalSection($context),
            $this->contentSection($context),
            $this->shopSection($context),
            $this->formsSection($context),
            $this->designSection($context),
            $this->extensionsSection($context),
            $this->systemSection($context),
        ]));
    }

    /**
     * Resolve the permission flags shared by every navigation section.
     *
     * @return array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}
     */
    private function permissionContext(?User $user): array
    {
        return [
            'isAdmin' => $user?->isAdmin() ?? false,
            'isViewer' => $user?->hasRole('viewer') ?? false,
            'isEditor' => $user?->hasRole('editor') ?? false,
            'isAuthor' => $user?->hasRole('author') ?? false,
            'isMarketing' => $user?->hasRole('marketing') ?? false,
            'can' => fn (string $ability): bool => $user !== null && $user->can($ability),
        ];
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function generalSection(array $ctx): AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isEditor = $ctx['isEditor'];
        $isMarketing = $ctx['isMarketing'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        $items = [];
        $items[] = new AdminNavigationItem(
            id: 'nav-dashboard',
            label: __('admin.nav.items.dashboard'),
            route: 'admin.dashboard',
            icon: 'fa-solid fa-chart-line',
            active: request()->routeIs('admin.dashboard')
        );

        if ($isAdmin || $isEditor || $isMarketing || $isViewer || $can('manage:seo')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-seo',
                label: __('admin.nav.items.seo'),
                route: 'admin.seo.audit',
                icon: 'fa-solid fa-robot',
                active: request()->routeIs('admin.seo.audit')
            );

            $items[] = new AdminNavigationItem(
                id: 'nav-seo-filters',
                label: __('admin.nav.items.filters'),
                route: 'admin.seo.filters.index',
                icon: 'fa-solid fa-filter',
                active: request()->routeIs('admin.seo.filters.*')
            );
        }

        return new AdminNavigationSection(__('admin.nav.sections.general'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function contentSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isEditor = $ctx['isEditor'];
        $isAuthor = $ctx['isAuthor'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        $items = [];

        // Pages
        if ($this->isModuleVisible('pages') && ($isAdmin || $isEditor || $isViewer || $can('manage:pages'))) {
            $items[] = new AdminNavigationItem(
                id: 'nav-pages',
                label: __('admin.nav.items.pages'),
                route: 'admin.pages.index',
                icon: 'fa-solid fa-file-lines',
                createRoute: ($isAdmin || $isEditor || $can('manage:pages')) ? 'admin.pages.create' : null,
                createTooltip: __('admin.nav.create.page'),
                active: request()->routeIs('admin.pages.*'),
                module: 'pages'
            );
        }

        // Blog
        if ($this->isModuleVisible('blog') && ($isAdmin || $isEditor || $isAuthor || $isViewer || $can('manage:posts'))) {
            $blogChildren = [];
            if ($isAdmin || $isEditor || $isViewer || $can('manage:posts')) {
                $blogChildren[] = new AdminNavigationItem(
                    id: 'nav-blog-categories',
                    label: __('admin.nav.items.blog_categories'),
                    route: 'admin.blog.categories.index',
                    icon: 'fa-solid fa-tag',
                    active: request()->routeIs('admin.blog.categories.*'),
                    module: 'blog'
                );
            }

            $items[] = new AdminNavigationItem(
                id: 'nav-blog',
                label: __('admin.nav.items.blog_posts'),
                route: 'admin.blog.posts.index',
                icon: 'fa-solid fa-pen-to-square',
                createRoute: ($isAdmin || $isEditor || $isAuthor || $can('manage:posts')) ? 'admin.blog.posts.create' : null,
                createTooltip: __('admin.nav.create.post'),
                active: request()->routeIs('admin.blog.posts.*'),
                module: 'blog',
                children: $blogChildren
            );
        }

        // Gallery
        if ($this->isModuleVisible('gallery') && ($isAdmin || $isEditor || $isViewer || $can('manage:media'))) {
            $items[] = new AdminNavigationItem(
                id: 'nav-gallery',
                label: __('admin.nav.items.galleries'),
                route: 'admin.galleries.index',
                icon: 'fa-solid fa-images',
                createRoute: ($isAdmin || $isEditor || $can('manage:media')) ? 'admin.galleries.create' : null,
                createTooltip: __('admin.nav.create.album'),
                active: request()->routeIs('admin.galleries.*'),
                module: 'gallery'
            );
        }

        // Content Audit
        if ($this->isModuleVisible('content_audit') && ($isAdmin || $isEditor || $isAuthor || $isViewer || $can('access:admin'))) {
            $pendingCount = $this->getPendingAuditCount();
            $items[] = new AdminNavigationItem(
                id: 'nav-audit',
                label: __('admin.nav.items.content_audit'),
                route: 'admin.audit.index',
                icon: 'fa-solid fa-clipboard-check',
                badgeText: $pendingCount > 0 ? (string) $pendingCount : null,
                badgeClass: 'px-1.5 py-0.5 rounded-full bg-amber-500 text-black text-[10px] font-extrabold animate-pulse',
                active: request()->routeIs('admin.audit.*'),
                module: 'content_audit'
            );
        }

        // AI Copilot Studio
        if ($this->isModuleVisible('ai_assistant') && ($isAdmin || $isEditor || $isAuthor || $isViewer || $can('access:admin'))) {
            $items[] = new AdminNavigationItem(
                id: 'nav-ai-assistant',
                label: __('admin.nav.items.ai_assistant'),
                route: 'admin.ai_assistant.workbench',
                icon: 'fa-solid fa-wand-magic-sparkles text-purple-400',
                badgeText: 'IA',
                badgeClass: 'px-1.5 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30 text-[9px] font-bold',
                active: request()->routeIs('admin.ai_assistant.*'),
                module: 'ai_assistant'
            );
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.content'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function shopSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isEditor = $ctx['isEditor'];
        $isMarketing = $ctx['isMarketing'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        if (! $this->isModuleVisible('shop') || ! ($isAdmin || $isEditor || $isMarketing || $isViewer || $can('manage:shop') || $can('manage:posts'))) {
            return null;
        }

        $shopChildren = [
            new AdminNavigationItem(
                id: 'nav-shop-categories',
                label: __('admin.nav.items.shop_categories'),
                route: 'admin.shop.categories.index',
                icon: 'fa-solid fa-tags',
                active: request()->routeIs('admin.shop.categories.*'),
                module: 'shop'
            ),
        ];

        $items = [];
        $items[] = new AdminNavigationItem(
            id: 'nav-products',
            label: __('admin.nav.items.shop_products'),
            route: 'admin.shop.products.index',
            icon: 'fa-solid fa-bag-shopping',
            createRoute: ($isAdmin || $isEditor || $can('manage:shop')) ? 'admin.shop.products.create' : null,
            createTooltip: __('admin.nav.create.product'),
            active: request()->routeIs('admin.shop.products.*'),
            module: 'shop',
            children: $shopChildren
        );

        if ($this->getShopMode() !== 'catalog_only') {
            $items[] = new AdminNavigationItem(
                id: 'nav-orders',
                label: __('admin.nav.items.shop_orders'),
                route: 'admin.shop.orders.index',
                icon: 'fa-solid fa-box',
                active: request()->routeIs('admin.shop.orders.*'),
                module: 'shop'
            );
        }

        return new AdminNavigationSection(__('admin.nav.sections.shop'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function formsSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isMarketing = $ctx['isMarketing'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        if (! $this->isModuleVisible('forms') || ! ($isAdmin || $isMarketing || $isViewer || $can('manage:forms'))) {
            return null;
        }

        $items = [
            new AdminNavigationItem(
                id: 'nav-forms',
                label: __('admin.nav.items.forms'),
                route: 'admin.forms.index',
                icon: 'fa-solid fa-clipboard-list',
                active: request()->routeIs('admin.forms.index'),
                module: 'forms'
            ),
            new AdminNavigationItem(
                id: 'nav-form-submissions',
                label: __('admin.nav.items.form_submissions'),
                route: 'admin.forms.submissions',
                icon: 'fa-solid fa-inbox',
                active: request()->routeIs('admin.forms.submissions'),
                module: 'forms'
            ),
        ];

        return new AdminNavigationSection(__('admin.nav.sections.forms'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function designSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isEditor = $ctx['isEditor'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        if (! ($isAdmin || $isEditor || $isViewer || $can('manage:menus') || $can('manage:themes') || $can('manage:media'))) {
            return null;
        }

        $items = [];

        if ($isAdmin || $isEditor || $isViewer || $can('manage:menus')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-menus',
                label: __('admin.nav.items.menus'),
                route: 'admin.menus.index',
                icon: 'fa-solid fa-compass',
                active: request()->routeIs('admin.menus.*')
            );
        }

        if ($isAdmin || $isEditor || $isViewer || $can('manage:themes')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-themes',
                label: __('admin.nav.items.themes'),
                route: 'admin.themes.index',
                icon: 'fa-solid fa-palette',
                active: request()->routeIs('admin.themes.*')
            );
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.design'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function extensionsSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $can = $ctx['can'];

        $knownCoreModules = ['pages', 'blog', 'gallery', 'content_audit', 'ai_assistant', 'shop', 'forms', 'maintenance', 'mcp'];
        $items = [];

        foreach ($this->moduleManager->getActiveModules() as $identifier => $moduleInstance) {
            if (in_array(strtolower($identifier), $knownCoreModules, true)) {
                continue;
            }

            try {
                $navConfig = $moduleInstance->getAdminNavigation();
                if (empty($navConfig)) {
                    continue;
                }

                foreach ($navConfig as $item) {
                    if (! empty($item['permission']) && ! ($isAdmin || $can($item['permission']))) {
                        continue;
                    }

                    $route = $item['route'];
                    $children = [];
                    if (isset($item['children'])) {
                        foreach ($item['children'] as $child) {
                            $childRoute = $child['route'];
                            $children[] = new AdminNavigationItem(
                                id: 'nav-'.strtolower($identifier).'-'.Str::slug($child['title']),
                                label: $child['title'],
                                route: $childRoute,
                                icon: $child['icon'] ?? 'fa-solid fa-circle-dot',
                                active: $this->routeIsActive($childRoute),
                                module: $identifier
                            );
                        }
                    }

                    $items[] = new AdminNavigationItem(
                        id: 'nav-'.strtolower($identifier).'-'.Str::slug($item['title']),
                        label: $item['title'],
                        route: $route,
                        icon: $item['icon'],
                        createRoute: $item['createRoute'] ?? null,
                        createTooltip: $item['createTooltip'] ?? null,
                        badgeText: $item['badgeText'] ?? null,
                        badgeClass: $item['badgeClass'] ?? null,
                        active: $this->routeIsActive($route),
                        module: $identifier,
                        children: $children
                    );
                }
            } catch (\Throwable) {
                // Safeguard against third-party module navigation errors
            }
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.extensions'), $items, true);
    }

    /**
     * @param  array{isAdmin: bool, isViewer: bool, isEditor: bool, isAuthor: bool, isMarketing: bool, can: callable(string): bool}  $ctx
     */
    private function systemSection(array $ctx): ?AdminNavigationSection
    {
        $isAdmin = $ctx['isAdmin'];
        $isViewer = $ctx['isViewer'];
        $can = $ctx['can'];

        if (! ($isAdmin || $isViewer || $can('manage:modules') || $can('manage:users') || $can('manage:storage'))) {
            return null;
        }

        $items = [];

        if ($isAdmin || $isViewer || $can('manage:modules')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-settings',
                label: __('admin.nav.items.site_settings'),
                route: 'admin.settings.index',
                icon: 'fa-solid fa-sliders',
                active: request()->routeIs('admin.settings.*')
            );

            $items[] = new AdminNavigationItem(
                id: 'nav-modules',
                label: __('admin.nav.items.modules'),
                route: 'admin.modules.index',
                icon: 'fa-solid fa-puzzle-piece',
                active: request()->routeIs('admin.modules.index')
            );

            if ($this->isModuleVisible('maintenance')) {
                $items[] = new AdminNavigationItem(
                    id: 'nav-maintenance',
                    label: __('admin.nav.items.maintenance'),
                    route: 'admin.modules.settings',
                    icon: 'fa-solid fa-screwdriver-wrench',
                    active: request()->is('*modules/maintenance/settings*'),
                    module: 'maintenance',
                    routeParameters: ['identifier' => 'maintenance']
                );
            }

            if ($this->isModuleVisible('mcp')) {
                $items[] = new AdminNavigationItem(
                    id: 'nav-mcp',
                    label: __('admin.nav.items.mcp'),
                    route: 'admin.modules.settings',
                    icon: 'fa-solid fa-network-wired',
                    active: request()->is('*modules/mcp/settings*'),
                    module: 'mcp',
                    routeParameters: ['identifier' => 'mcp']
                );
            }

            $items[] = new AdminNavigationItem(
                id: 'nav-markets',
                label: __('admin.nav.items.markets'),
                route: 'admin.markets.index',
                icon: 'fa-solid fa-globe',
                active: request()->routeIs('admin.markets.*')
            );
        }

        if ($isAdmin || $can('manage:storage')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-connections',
                label: __('admin.nav.items.connections'),
                route: 'admin.connections.index',
                icon: 'fa-solid fa-cloud-arrow-up',
                active: request()->routeIs('admin.connections.*')
            );
        }

        if ($isAdmin || $isViewer || $can('manage:users')) {
            $items[] = new AdminNavigationItem(
                id: 'nav-users',
                label: __('admin.nav.items.users'),
                route: 'admin.users.index',
                icon: 'fa-solid fa-users',
                active: request()->routeIs('admin.users.*')
            );

            $items[] = new AdminNavigationItem(
                id: 'nav-tokens',
                label: __('admin.nav.items.tokens'),
                route: 'admin.tokens.index',
                icon: 'fa-solid fa-key',
                active: request()->routeIs('admin.tokens.*')
            );
        }

        return empty($items) ? null : new AdminNavigationSection(__('admin.nav.sections.system'), $items, true);
    }

    /**
     * Whether a route name (or its wildcard) is the current request.
     */
    private function routeIsActive(string $route): bool
    {
        try {
            return request()->routeIs($route) || request()->routeIs($route.'.*');
        } catch (\Throwable) {
            return false;
        }
    }
}
