<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Services\Seo\FilterEditorService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class FiltersGetController extends Controller
{
    public function __invoke(Request $request, FilterEditorService $editor): View
    {
        return view('admin.filters.index', $editor->data($request->query(), app()->getLocale()));
    }
}
