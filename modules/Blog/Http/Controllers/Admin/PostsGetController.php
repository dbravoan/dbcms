<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Blog\Models\Category;
use Modules\Blog\Models\Post;

final class PostsGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = Post::with(['translations', 'author', 'category.translations', 'markets']);

        $posts = $this->applyDatatable(
            $query,
            $request,
            ['translations.title', 'translations.summary'],
            [
                'is_published' => 'is_published',
                'category_id' => 'category_id',
                'market_id' => 'markets.id',
            ],
            'created_at',
            'desc'
        );

        $categories = Category::with('translations')->get();
        $markets = Market::where('is_active', true)->get();

        return view('admin.blog.posts.index', [
            'posts' => $posts,
            'categories' => $categories,
            'markets' => $markets,
        ]);
    }
}
