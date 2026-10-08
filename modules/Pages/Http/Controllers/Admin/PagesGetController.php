<?php

declare(strict_types=1);

namespace Modules\Pages\Http\Controllers\Admin;

use App\Core\Traits\HandlesDatatableQuery;
use App\Http\Controllers\Controller;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Pages\Models\Page;

final class PagesGetController extends Controller
{
    use HandlesDatatableQuery;

    public function __invoke(Request $request): View
    {
        $query = Page::with(['translations', 'sections', 'markets']);

        $pages = $this->applyDatatable(
            $query,
            $request,
            ['translations.title', 'translations.slug'],
            [
                'is_published' => 'is_published',
                'template' => 'template',
            ],
            'created_at',
            'desc'
        );

        $markets = Market::where('is_active', true)->get();

        return view('admin.pages.index', [
            'pages' => $pages,
            'markets' => $markets,
        ]);
    }
}
