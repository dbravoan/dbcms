@php
    /**
     * Compact site search for theme headers.
     *
     * Included by every theme header with a one-liner, so the general search is
     * reachable from any page without each theme reimplementing it:
     *
     *   @include('themes.components.search-box', ['variant' => 'dark'])
     *
     * The box is a plain GET form; the query is consolidated by a 301 into the
     * canonical friendly URL (`/es/es/buscar/termino`).
     */
    use App\Core\Modules\ModuleManager;

    $searchVariant = $variant ?? 'dark';
    $searchEnabled = true;

    try {
        $searchEnabled = app(ModuleManager::class)->isModuleEnabled('search');
    } catch (\Throwable) {
        $searchEnabled = false;
    }
@endphp

@if($searchEnabled)
    <div class="hidden md:block w-56 lg:w-64" data-cms-site-search>
        <x-cms-search.box size="md" :variant="$searchVariant" />
    </div>
@endif
