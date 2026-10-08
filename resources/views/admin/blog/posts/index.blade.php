@extends('admin.layout')

@section('title', __('admin.page_titles.blog_posts'))

@section('content')
<div class="space-y-6 font-mono text-xs">

    <!-- Unified Datatable Header with Search & Filters -->
    <x-admin.datatable-header
        title="{{ __('admin.listing.posts_title') }}"
        description="{{ __('admin.listing.posts_desc') }}"
        searchPlaceholder="Buscar por título o resumen..."
        :createRoute="route('admin.blog.posts.create')"
        createLabel="+ Nuevo artículo"
    >
        <!-- Filter: Status -->
        <select name="is_published" class="bg-[#1A1A1A] border border-gray-700 text-white focus:border-[#00FF99] rounded-lg px-3 py-2 text-xs">
            <option value="all">{{ __('admin.listing.all_statuses') }}</option>
            <option value="1" {{ request('is_published') === '1' ? 'selected' : '' }}>{{ __('admin.listing.published_m') }}</option>
            <option value="0" {{ request('is_published') === '0' ? 'selected' : '' }}>{{ __('admin.listing.drafts') }}</option>
        </select>

        <!-- Filter: Category -->
        <x-admin.search-select source="blog_categories" name="category_id" :value="request('category_id')" placeholder="{{ __('admin.listing.posts_all_categories') }}" />

        <!-- Filter: Market -->
        <x-admin.market-select name="market_id" value-key="id" :value="request('market_id')" placeholder="{{ __('admin.listing.posts_all_markets') }}" />
    </x-admin.datatable-header>

    <!-- Posts Table -->
    <x-admin.table
        :empty="$posts->isEmpty()"
        emptyTitle="{{ __('admin.listing.posts_empty_title') }}"
        emptyDescription="{{ __('admin.listing.posts_empty_desc') }}"
        :emptyActionRoute="route('admin.blog.posts.create')"
        emptyActionLabel="+ Nuevo artículo"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.listing.title_label') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden sm:table-cell">{{ __('admin.listing.category') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden lg:table-cell">{{ __('admin.listing.author') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden md:table-cell">{{ __('admin.listing.markets') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden xl:table-cell">{{ __('admin.listing.languages') }}</th>
            <x-admin.sortable-th column="reading_time_minutes" label="{{ __('admin.listing.reading_time') }}" class="hidden xl:table-cell" />
            <x-admin.sortable-th column="is_published" label="{{ __('admin.listing.status') }}" class="hidden sm:table-cell" />
            <x-admin.sortable-th column="created_at" label="{{ __('admin.listing.date') }}" class="hidden xl:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-400">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($posts as $post)
            @php
                $mainTrans = $post->translations->where('locale', 'es')->first()
                    ?? $post->translations->first();
                $slug = $mainTrans?->slug;
            @endphp
            <tr class="hover:bg-gray-800/30 transition-colors">
                <!-- Mobile Chevron Toggle -->
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button"
                            @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')"
                            class="w-6 h-6 rounded-md flex items-center justify-center text-gray-400 hover:text-white hover:bg-gray-800 transition-colors"
                            :title="expanded ? @js(__('admin.listing.hide_details')) : @js(__('admin.listing.show_details'))">
                        <i class="fa-solid text-[10px] transition-transform duration-200"
                           :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                    </button>
                </td>

                <td class="py-3.5 px-4 font-bold text-white max-w-xs truncate">
                    <div>{{ $mainTrans?->title ?? 'Sin título' }}</div>
                    <div class="text-[10px] text-gray-500 truncate font-mono">/{{ $slug }}</div>
                </td>
                <td class="py-3.5 px-4 text-gray-300 hidden sm:table-cell">
                    {{ $post->category?->translations->first()?->name ?? 'General' }}
                </td>
                <td class="py-3.5 px-4 text-gray-400 hidden lg:table-cell">
                    {{ $post->author->name ?? 'Admin' }}
                </td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    <x-admin.market-chips :markets="$post->markets" empty="Global" />
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.locale-chips :locales="$post->translations->pluck('locale')" />
                </td>
                <td class="py-3.5 px-4 text-gray-400 hidden xl:table-cell">
                    <x-admin.chip>{{ $post->reading_time_minutes }} min</x-admin.chip>
                </td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <x-admin.status-badge :active="$post->is_published" activeText="Publicado" inactiveText="Borrador" />
                </td>
                <td class="py-3.5 px-4 text-gray-400 whitespace-nowrap hidden xl:table-cell">
                    {{ $post->created_at->format('Y-m-d') }}
                </td>
                <td class="py-3.5 px-4 text-right">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            href="{{ route('admin.blog.posts.edit', $post) }}"
                            icon="fa-solid fa-pen-to-square"
                            tooltip="Editar artículo"
                            variant="primary"
                        />
                        @if($slug)
                            <x-admin.action-button
                                href="{{ url('/blog/' . $slug) }}"
                                target="_blank"
                                icon="fa-solid fa-arrow-up-right-from-square"
                                tooltip="Ver en blog"
                                variant="view"
                            />
                        @endif
                        <x-admin.action-button
                            type="button"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.blog.posts.destroy', $post) }}',
                                title: 'Eliminar artículo',
                                message: '¿Confirmas que deseas eliminar este artículo del blog?'
                            })"
                            icon="fa-solid fa-trash-can"
                            tooltip="Eliminar artículo"
                            variant="danger"
                        />
                    </div>
                </td>
            </tr>

            <!-- Mobile Accordion Detail Row -->
            <x-admin.table-row-detail>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.category') }}">
                    <x-admin.chip>{{ $post->category?->translations->first()?->name ?? 'General' }}</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.author') }}" :value="$post->author->name ?? 'Admin'" />
                <x-admin.table-row-detail-item label="{{ __('admin.listing.markets') }}">
                    <x-admin.market-chips :markets="$post->markets" empty="Global" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.languages') }}">
                    <x-admin.locale-chips :locales="$post->translations->pluck('locale')" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.reading_time_full') }}">
                    <x-admin.chip>{{ $post->reading_time_minutes }} min</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.status') }}">
                    <x-admin.status-badge :active="$post->is_published" activeText="Publicado" inactiveText="Borrador" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.created_at') }}" :value="$post->created_at->format('Y-m-d')" />
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$posts" />
        </x-slot:pagination>
    </x-admin.table>
</div>
@endsection
