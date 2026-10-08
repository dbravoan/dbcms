<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Markets;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use App\Models\Language;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class MarketsGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = Market::with('languages');

        $markets = $this->applyDatatable(
            $query,
            $request,
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
