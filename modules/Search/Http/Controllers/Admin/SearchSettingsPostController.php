<?php

declare(strict_types=1);

namespace Modules\Search\Http\Controllers\Admin;

use App\Core\Search\SearchIndexer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Search\Services\SearchSettings;

/**
 * Activation and maintenance of the general search, from the
 * "Filtros y búsquedas" inbox.
 */
final class SearchSettingsPostController extends Controller
{
    public function __construct(
        private readonly SearchSettings $settings,
        private readonly SearchIndexer $indexer,
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:save,reindex'],
            'is_indexable' => ['nullable', 'boolean'],
            'track_terms' => ['nullable', 'boolean'],
            'results_per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'min_results_to_index' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validated['action'] === 'reindex') {
            $counts = $this->indexer->indexAll(fresh: false);

            return redirect()->back()->with('success', 'Indice de busqueda actualizado: '.array_sum($counts).' documentos.');
        }

        $this->settings->set('is_indexable', $request->boolean('is_indexable'));
        $this->settings->set('track_terms', $request->boolean('track_terms'));

        if (isset($validated['results_per_page'])) {
            $this->settings->set('results_per_page', (string) $validated['results_per_page']);
        }

        if (isset($validated['min_results_to_index'])) {
            $this->settings->set('min_results_to_index', (string) $validated['min_results_to_index']);
        }

        return redirect()->back()->with(
            'success',
            $request->boolean('is_indexable')
                ? 'Busqueda general activada para Google.'
                : 'Busqueda general en noindex (solo para visitantes).'
        );
    }
}
