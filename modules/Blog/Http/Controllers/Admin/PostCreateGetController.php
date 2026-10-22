<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Modules\Blog\Models\Category;

final class PostCreateGetController extends Controller
{
    public function __invoke(): View
    {
        $categories = Category::with('translations')->get();
        $markets = Market::with('languages')->where('is_active', true)->get();
        $languages = Language::where('is_active', true)->get();

        return view('admin.blog.posts.create', [
            'categories' => $categories,
            'markets' => $markets,
            'languages' => $languages,
        ]);
    }
}
