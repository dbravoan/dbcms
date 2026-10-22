<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Forms\Models\CustomForm;

final class FormsGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables): View
    {
        $query = CustomForm::with(['translations', 'markets', 'fields.translations'])->withCount(['submissions', 'fields']);

        $forms = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
            ['translations.name', 'slug'],
            [
                // `market_id` filters the many-to-many assignment; the unqualified
                // `id` is qualified against the related table by the trait.
                'is_active' => 'is_active',
                'market_id' => 'markets.id',
            ],
            'created_at',
            'desc'
        );

        return view('forms::admin.index', [
            'forms' => $forms,
        ]);
    }
}
