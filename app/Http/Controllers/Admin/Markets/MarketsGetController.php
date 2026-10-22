<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Markets;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MarketsGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = Market::with('languages');

        $markets = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
            ['name', 'code', 'currency'],
            ['is_active' => 'is_active'],
            'created_at',
            'asc'
        );

        $allLanguages = Language::all();

        return view('admin.markets.index', [
            'markets' => $markets,
            'allLanguages' => $allLanguages,
        ]);
    }
}
