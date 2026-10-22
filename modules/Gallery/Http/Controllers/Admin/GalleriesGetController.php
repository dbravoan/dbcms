<?php

declare(strict_types=1);

namespace Modules\Gallery\Http\Controllers\Admin;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Gallery\Models\Gallery;

final class GalleriesGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = Gallery::with(['translations', 'items', 'markets'])->withCount('items');

        $galleries = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
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
