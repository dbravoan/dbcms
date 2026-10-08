@extends('admin.layout')

@section('title', __('admin.page_titles.filters'))

@section('content')
@php
    $form = $form ?? null;
    $hasSearchOrSort = static fn ($page): bool => $page !== null
        && (str_contains((string) $page->signature, 'search=') || str_contains((string) $page->signature, 'sort='));
    $isEdit = ($form['mode'] ?? null) === 'edit';

    // Traducciones del diálogo de borrado. Se resuelven aquí y no dentro del
    // atributo @click de un componente: Blade copia los atributos de <x-...>
    // como texto literal, así que una directiva @js() llegaría sin compilar.
    $deleteLabels = [
        'title' => __('admin.filters.delete'),
        'message' => __('admin.filters.delete_confirm'),
    ];
@endphp

<div class="space-y-6 font-mono text-xs" x-data="{
    deleteLabels: {{ Illuminate\Support\Js::from($deleteLabels)->toHtml() }}
}">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-lg font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-filter text-[#00FF99]"></i>
                <span>{{ __('admin.filters.title') }}</span>
            </h1>
            <p class="text-xs text-gray-400 max-w-3xl">{{ __('admin.filters.subtitle') }}</p>
        </div>

        @if($form === null)
            <a href="{{ route('admin.seo.filters.index', ['new' => 1]) }}" class="btn-neon px-4 py-2 text-xs inline-flex items-center gap-2">
                <i class="fa-brands fa-google"></i>
                <span>{{ __('admin.filters.create') }}</span>
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="p-3 rounded-xl bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] flex items-center gap-2">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="p-3 rounded-xl bg-red-500/10 border border-red-500/30 text-red-400 flex items-start gap-2">
            <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
            <ul class="list-disc pl-4 space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- General search: activation + real usage, provided by the Search module. --}}
    @isset($searchPanel)
        <div class="bg-[#141414] border border-gray-800 rounded-xl p-5 space-y-5">
            <div class="flex flex-wrap items-start justify-between gap-3 pb-3 border-b border-gray-800">
                <div class="space-y-1">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-magnifying-glass text-[#00FF99]"></i>
                        <span>{{ __('Búsqueda general') }}</span>
                    </h2>
                    <p class="text-[11px] text-gray-500 max-w-2xl">
                        {{ __('Busca en todas las entidades instaladas con URL amigable. Nace en noindex: actívala cuando su contenido aporte valor y los resultados con suficiente contenido entrarán en el sitemap.') }}
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <span class="px-2 py-1 rounded-lg text-[10px] font-bold {{ $searchPanel['indexable'] ? 'bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30' : 'bg-gray-800 text-gray-400 border border-gray-700' }}">
                        {{ $searchPanel['indexable'] ? __('Indexable en Google') : __('noindex') }}
                    </span>
                    <a href="{{ $searchPanel['url'] }}" target="_blank" rel="noopener"
                       class="px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:text-white hover:border-[#00FF99] text-[11px] inline-flex items-center gap-2">
                        <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                        <span>{{ __('Ver la búsqueda') }}</span>
                    </a>
                </div>
            </div>

            <form method="POST" action="{{ route('admin.seo.filters.search.update') }}" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <input type="hidden" name="action" value="save">

                <label class="flex items-start gap-2 text-gray-300">
                    <input type="checkbox" name="is_indexable" value="1" @checked($searchPanel['indexable']) class="mt-0.5">
                    <span>
                        <span class="block font-bold text-white">{{ __('Indexar en Google') }}</span>
                        <span class="block text-[10px] text-gray-500">{{ __('El buscador y sus resultados salen de noindex') }}</span>
                    </span>
                </label>

                <label class="flex items-start gap-2 text-gray-300">
                    <input type="checkbox" name="track_terms" value="1" @checked($searchPanel['tracks']) class="mt-0.5">
                    <span>
                        <span class="block font-bold text-white">{{ __('Registrar términos') }}</span>
                        <span class="block text-[10px] text-gray-500">{{ __('Qué busca la gente y qué no encuentra') }}</span>
                    </span>
                </label>

                <label class="space-y-1 text-gray-300">
                    <span class="block font-bold text-white">{{ __('Resultados por página') }}</span>
                    <input type="number" name="results_per_page" min="1" max="100" value="{{ $searchPanel['perPage'] }}"
                           class="w-full bg-[#0F0F0F] border border-gray-700 rounded-lg px-3 py-2 text-white">
                </label>

                <label class="space-y-1 text-gray-300">
                    <span class="block font-bold text-white">{{ __('Mínimo de resultados para indexar') }}</span>
                    <input type="number" name="min_results_to_index" min="1" max="50" value="{{ $searchPanel['minResults'] }}"
                           class="w-full bg-[#0F0F0F] border border-gray-700 rounded-lg px-3 py-2 text-white">
                </label>

                <div class="sm:col-span-2 lg:col-span-4 flex items-center gap-3">
                    <button type="submit" class="btn-neon px-4 py-2 text-xs">{{ __('Guardar') }}</button>
                    <span class="text-[10px] text-gray-500">{{ $searchPanel['total'] }} {{ __('documentos indexados') }}</span>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.seo.filters.search.update') }}" class="flex items-center gap-3">
                @csrf
                <input type="hidden" name="action" value="reindex">
                <button type="submit" class="px-3 py-1.5 rounded-lg border border-gray-700 text-gray-300 hover:text-white hover:border-[#00FF99] text-[11px]">
                    {{ __('Reindexar ahora') }}
                </button>
                <span class="text-[10px] text-gray-500">{{ __('Vuelve a leer todo el contenido publicado de los módulos activos.') }}</span>
            </form>

            @if($searchPanel['coverage'] !== [])
                <div class="flex flex-wrap gap-2">
                    @foreach($searchPanel['coverage'] as $type => $data)
                        <span class="px-2.5 py-1 rounded-lg bg-[#0F0F0F] border border-gray-800 text-[10px] text-gray-400">
                            {{ $data['label'] }} · <span class="text-gray-200 font-bold">{{ $data['indexed'] }}</span>
                        </span>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-4 lg:grid-cols-2">
                <div class="space-y-2">
                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ __('Términos más buscados') }}</h3>
                    @forelse($searchPanel['top'] as $term)
                        <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg bg-[#0F0F0F] border border-gray-800">
                            <a href="{{ $searchPanel['url'] }}/{{ \Illuminate\Support\Str::slug($term->term) }}" target="_blank" rel="noopener" class="text-gray-200 hover:text-[#00FF99]">
                                {{ $term->term }}
                            </a>
                            <span class="text-[10px] text-gray-500">{{ $term->hits }} · {{ $term->results }} {{ __('resultados') }}</span>
                        </div>
                    @empty
                        <p class="text-[11px] text-gray-500">{{ __('Aún no hay búsquedas registradas.') }}</p>
                    @endforelse
                </div>

                <div class="space-y-2">
                    <h3 class="text-[11px] font-bold uppercase tracking-wide text-gray-500">{{ __('Búsquedas sin resultados') }}</h3>
                    @forelse($searchPanel['unsatisfied'] as $term)
                        <div class="flex items-center justify-between gap-3 px-3 py-2 rounded-lg bg-[#0F0F0F] border border-amber-500/20">
                            <span class="text-amber-300">{{ $term->term }}</span>
                            <span class="text-[10px] text-gray-500">{{ $term->zero_results }}x</span>
                        </div>
                    @empty
                        <p class="text-[11px] text-gray-500">{{ __('Nadie ha buscado todavía algo que no exista.') }}</p>
                    @endforelse
                </div>
            </div>
        </div>
    @endisset

    @if($form !== null)
        <div class="bg-[#141414] border border-[#00FF99]/30 rounded-xl p-5 space-y-5">
            <div class="flex items-center justify-between gap-3 pb-3 border-b border-gray-800">
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-brands fa-google text-[#00FF99]"></i>
                    <span>{{ $isEdit ? __('admin.filters.edit') : __('admin.filters.create') }}</span>
                </h2>
                <a href="{{ route('admin.seo.filters.index') }}" class="text-gray-400 hover:text-white">
                    &times; {{ __('admin.actions.cancel') }}
                </a>
            </div>

            @if($form['prefilled'])
                <div class="p-3 rounded-lg bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] flex items-start gap-2">
                    <i class="fa-solid fa-circle-info mt-0.5"></i>
                    <span>{{ __('admin.filters.prefilled_hint') }}</span>
                </div>
            @endif

            @if($form['warning'])
                <div class="p-3 rounded-lg bg-amber-500/10 border border-amber-500/30 text-amber-300 flex items-start gap-2">
                    <i class="fa-solid fa-triangle-exclamation mt-0.5"></i>
                    <span>{{ __('admin.filters.search_warning') }}</span>
                </div>
            @endif

            <form method="POST"
                  action="{{ $isEdit ? route('admin.seo.filters.update', $form['id']) : route('admin.seo.filters.store') }}"
                  class="space-y-5">
                @csrf
                @if($isEdit)
                    @method('PUT')
                @endif

                @include('admin.filters._form', ['form' => $form, 'languages' => $languages, 'markets' => $markets, 'filterModules' => $filterModules])

                <div class="pt-4 border-t border-gray-800 space-y-3">
                    <h3 class="text-xs font-bold text-white flex items-center gap-2">
                        <i class="fa-solid fa-layer-group text-[#00FF99]"></i>
                        <span>{{ __('admin.filters.field_intro') }}</span>
                    </h3>
                    <p class="text-[10px] text-gray-500">{{ __('admin.filters.intro_hint') }}</p>

                    <x-admin.block-manager
                        :sections="$form['sections']"
                        name="intro_blocks_json"
                        :languages="$languages"
                    />
                </div>

                <div class="flex justify-end gap-3 pt-4 border-t border-gray-800">
                    <a href="{{ route('admin.seo.filters.index') }}" class="px-4 py-2 bg-gray-800 text-gray-400 hover:text-white rounded-lg">{{ __('admin.actions.cancel') }}</a>
                    <button type="submit" class="btn-neon inline-flex items-center gap-2">
                        <i class="fa-brands fa-google"></i>
                        <span>{{ __('admin.actions.save') }}</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    <div class="overflow-x-auto bg-[#141414] border border-gray-800 rounded-xl">
        <table class="w-full">
            <thead>
                <tr class="border-b border-gray-800 text-left text-gray-400 uppercase tracking-wider text-[10px]">
                    <th class="py-3 px-4">{{ __('admin.filters.col_module') }}</th>
                    <th class="py-3 px-4">{{ __('admin.filters.col_market') }}</th>
                    <th class="py-3 px-4">{{ __('admin.filters.col_locale') }}</th>
                    <th class="py-3 px-4">{{ __('admin.filters.col_path') }}</th>
                    <th class="py-3 px-4">{{ __('admin.filters.col_title') }}</th>
                    <th class="py-3 px-4">{{ __('admin.filters.col_indexable') }}</th>
                    <th class="py-3 px-4 text-right">{{ __('admin.common.actions') }}</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-800/60">
                @forelse($filterPages as $page)
                    <tr class="hover:bg-gray-800/30 transition-colors">
                        <td class="py-3 px-4 font-bold text-[#00FF99]">{{ $page->module }}</td>
                        <td class="py-3 px-4 text-gray-300">{{ $page->market_code ?? '—' }}</td>
                        <td class="py-3 px-4 text-gray-300 uppercase">{{ $page->locale }}</td>
                        <td class="py-3 px-4 text-gray-300 font-mono break-all">
                            <span class="inline-flex items-center gap-1.5">
                                @if($hasSearchOrSort($page))
                                    <i class="fa-solid fa-triangle-exclamation text-amber-400" title="{{ __('admin.filters.search_warning') }}"></i>
                                @endif
                                <span>{{ $page->path ?? $page->signature }}</span>
                            </span>
                        </td>
                        <td class="py-3 px-4 text-white max-w-xs truncate">{{ $page->title ?? $page->heading ?? '—' }}</td>
                        <td class="py-3 px-4">
                            <x-admin.status-badge
                                :active="$page->is_indexable"
                                :activeText="__('admin.filters.in_google_yes')"
                                :inactiveText="__('admin.filters.in_google_no')"
                            />
                        </td>
                        <td class="py-3 px-4 text-right whitespace-nowrap">
                            @php
                                $modulePrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)
                                    ->getModulePrefix((string) $page->module, (string) $page->locale);
                                $marketPrefix = $page->market_code ?? (string) ($markets->firstWhere('is_default', true)?->code ?? 'es');
                            @endphp
                            <x-admin.action-button
                                icon="fa-solid fa-arrow-up-right-from-square"
                                :tooltip="__('admin.filters.view_on_site')"
                                variant="default"
                                :href="url('/'.$marketPrefix.'/'.$page->locale.'/'.$modulePrefix.'/'.ltrim((string) ($page->path ?? ''), '/'))"
                            />
                            <x-admin.action-button
                                icon="fa-solid fa-pen-to-square"
                                :tooltip="__('admin.filters.edit')"
                                variant="primary"
                                :href="route('admin.seo.filters.index', ['edit' => $page->id])"
                            />
                            <x-admin.action-button
                                icon="fa-solid fa-eye-slash"
                                :tooltip="__('admin.filters.delete')"
                                variant="danger"
                                @click="$dispatch('confirm-delete', {
                                    action: '{{ route('admin.seo.filters.destroy', $page) }}',
                                    title: deleteLabels.title,
                                    message: deleteLabels.message
                                })"
                            />
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-8 px-4 text-center text-gray-500">{{ __('admin.filters.empty') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
