<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Seo;

use App\Http\Controllers\Controller;
use App\Models\FilterPage;
use Illuminate\Http\RedirectResponse;

/**
 * Removes a curated landing: the filter page keeps working at runtime (composed
 * text, noindex) but stops being an indexable curated surface.
 */
final class FilterDeleteController extends Controller
{
    public function __invoke(FilterPage $filterPage): RedirectResponse
    {
        $path = (string) ($filterPage->path ?? $filterPage->signature);

        $filterPage->delete();

        return redirect()->route('admin.seo.filters.index')
            ->with('success', 'Landing de filtro eliminada: '.' '.$path);
    }
}
