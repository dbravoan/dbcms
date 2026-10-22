<?php

declare(strict_types=1);

namespace App\Content\Pages\Http\Controllers\Admin;

use App\Content\Pages\Models\Page;
use App\Content\Pages\Services\PageAdminPublicLinks;
use App\DTOs\Admin\DatatableQueryData;
use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Services\Admin\DatatableQueryService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class PagesGetController extends Controller
{
    public function __invoke(Request $request, DatatableQueryService $tables, PageAdminPublicLinks $publicLinks): View
    {
        $query = Page::with(['translations', 'sections', 'markets']);

        $pages = $tables->paginate(
            $query,
            DatatableQueryData::fromInput($request->query()),
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
            'pagePublicUrls' => $publicLinks->forPages(collect($pages->items())),
            'markets' => $markets,
        ]);
    }
}
