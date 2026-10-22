<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Shop\Models\ProductCategory;

final class ShopCategoriesGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = ProductCategory::with(['translations', 'parent.translations', 'markets'])
            ->withCount('products');

        $categories = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
            ['translations.name', 'translations.slug'],
            [
                'is_active' => 'is_active',
                'parent_id' => 'parent_id',
            ],
            'order',
            'asc'
        );

        $allCategories = ProductCategory::with(['translations', 'markets'])->orderBy('order', 'asc')->get();

        return view('admin.shop.categories.index', [
            'categories' => $categories,
            'allCategories' => $allCategories,
            'languages' => Language::query()->where('is_active', true)->orderBy('name')->get(),
            'markets' => Market::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }
}
