<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Contracts\Services\AdminDashboardServiceInterface;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Modules\ModuleManager;
use App\Models\CmsModule;
use App\Models\Market;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use Modules\Blog\Models\Post;
use Modules\Forms\Models\FormSubmission;
use Modules\Pages\Models\Page;
use Modules\Shop\Models\Product;

final class AdminDashboardService implements AdminDashboardServiceInterface
{
    public function __construct(private readonly ModuleManager $modules) {}

    /** @return array<string, mixed> */
    public function data(User $user): array
    {
        $fresh = User::findOrFail((int) $user->getKey());
        abort_unless(! $fresh->is_service_account && ($fresh->isAdmin() || $fresh->hasPermission('access:admin')), 403);
        $allowed = static fn (string $permission): bool => $fresh->isAdmin() || $fresh->hasPermission($permission);
        $hasBlog = $allowed('manage:posts') && $this->modules->isModuleEnabled('blog') && class_exists(Post::class) && Schema::hasTable('posts');
        $hasPages = $allowed('manage:pages') && $this->modules->isModuleEnabled('pages') && class_exists(Page::class) && Schema::hasTable('pages');
        $hasShop = $allowed('manage:shop') && $this->modules->isModuleEnabled('shop') && class_exists(Product::class) && Schema::hasTable('products');
        $hasForms = $allowed('manage:forms') && $this->modules->isModuleEnabled('forms') && class_exists(FormSubmission::class) && Schema::hasTable('form_submissions');
        $reviewer = $fresh->isAdmin() || app(EditorialWorkflowInterface::class)->canReview($fresh);
        $recentPosts = collect();
        $totalPosts = 0;
        if ($hasBlog) {
            $posts = Post::query();
            if (! $reviewer) {
                $posts->where(static fn (Builder $query) => $query->where('user_id', $fresh->id)->orWhere(static fn (Builder $public) => $public->published()));
            }
            $totalPosts = (clone $posts)->count();
            $recentPosts = $posts->with(['translations', 'author'])->latest()->take(5)->get();
        }
        $stats = [
            'total_users' => $allowed('manage:users') ? User::count() : 0,
            'active_markets' => $allowed('manage:modules') ? Market::where('is_active', true)->count() : 0,
            'installed_modules' => $allowed('manage:modules') ? CmsModule::where('is_installed', true)->count() : 0,
            'total_posts' => $totalPosts,
            'total_pages' => $hasPages ? Page::count() : 0,
            'total_products' => $hasShop ? Product::count() : 0,
            'total_submissions' => $hasForms ? FormSubmission::count() : 0,
        ];

        return [
            'stats' => $stats,
            'recentSubmissions' => $hasForms ? FormSubmission::with('form')->latest()->take(5)->get() : collect(),
            'recentPosts' => $recentPosts,
            'activeModules' => $allowed('manage:modules') ? $this->modules->getActiveModules() : [],
            'hasBlog' => $hasBlog, 'hasPages' => $hasPages, 'hasShop' => $hasShop, 'hasForms' => $hasForms,
            'canManageModules' => $allowed('manage:modules'), 'canManageUsers' => $allowed('manage:users'), 'hasSeo' => $allowed('manage:seo'),
        ];
    }
}
