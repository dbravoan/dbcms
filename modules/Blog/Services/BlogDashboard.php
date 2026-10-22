<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Modules\Blog\Models\Post;

final class BlogDashboard
{
    public function __construct(private readonly EditorialWorkflowInterface $workflow) {}

    /** @return array{views: array<string, string>, data: array<string, mixed>}|null */
    public function data(User $user): ?array
    {
        if (! $user->isAdmin() && ! $user->hasPermission('manage:posts')) {
            return null;
        }
        $posts = Post::query();
        if (! $user->isAdmin() && ! $this->workflow->canReview($user)) {
            $posts->where(static fn (Builder $query) => $query->where('user_id', $user->id)->orWhere(static fn (Builder $public) => $public->published()));
        }

        return [
            'views' => ['metrics' => 'blog::admin.dashboard.metrics', 'actions' => 'blog::admin.dashboard.actions', 'details' => 'blog::admin.dashboard.details'],
            'data' => ['stats' => ['total_posts' => (clone $posts)->count()], 'recentPosts' => $posts->with(['translations', 'author'])->latest()->take(5)->get()],
        ];
    }
}
