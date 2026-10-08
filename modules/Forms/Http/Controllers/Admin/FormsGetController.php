<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Forms\Models\CustomForm;

final class FormsGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = CustomForm::with(['translations', 'markets', 'fields.translations'])->withCount(['submissions', 'fields']);

        $forms = $this->applyDatatable(
            $query,
            $request,
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

        return view('admin.forms.index', [
            'forms' => $forms,
        ]);
    }
}
