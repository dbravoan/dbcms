<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Gallery\Models\Gallery;

final class GalleriesGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = Gallery::with(['translations', 'items', 'markets'])->withCount('items');

        $galleries = $this->applyDatatable(
            $query,
            $request,
            ['translations.title', 'slug'],
            [
                'is_active' => 'is_active',
                'market_id' => 'markets.id',
            ],
            'created_at',
            'desc'
        );

        $markets = Market::where('is_active', true)->get();

        return view('admin.galleries.index', [
            'galleries' => $galleries,
            'markets' => $markets,
        ]);
    }
}
