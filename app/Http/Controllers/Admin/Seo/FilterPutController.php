<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Seo;

use App\Core\Sections\SectionSyncService;
use App\Http\Controllers\Controller;
use App\Models\FilterPage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Actualiza el texto y los bloques de introducción de un filtro indexado.
 *
 * The address/signature identify the page and are immutable: to move a landing,
 * stop indexing it and index the new address.
 */
final class FilterPutController extends Controller
{
    public function __construct(
        private readonly SectionSyncService $sections,
    ) {}

    public function __invoke(Request $request, FilterPage $filterPage): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'nullable|string|max:255',
            'heading' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'is_indexable' => 'nullable|boolean',
            'intro_blocks_json' => 'nullable|string',
        ]);

        $filterPage->update([
            'title' => $validated['title'] ?? null,
            'heading' => $validated['heading'] ?? null,
            'meta_description' => $validated['meta_description'] ?? null,
            'is_indexable' => (bool) ($validated['is_indexable'] ?? false),
        ]);

        $this->sections->sync($filterPage, (string) ($validated['intro_blocks_json'] ?? '[]'));

        return redirect()->route('admin.seo.filters.index')
            ->with('success', 'Filtro actualizado.');
    }
}
