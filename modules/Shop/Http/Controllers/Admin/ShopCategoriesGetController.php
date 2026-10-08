<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Modules\Shop\Models\ProductCategory;

final class ShopCategoriesGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = ProductCategory::with(['translations', 'parent.translations', 'markets'])
            ->withCount('products');

        $categories = $this->applyDatatable(
            $query,
            $request,
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
