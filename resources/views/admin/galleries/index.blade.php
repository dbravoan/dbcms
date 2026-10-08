@extends('admin.layout')

@section('title', __('admin.page_titles.galleries'))

@section('content')
<div class="space-y-6 font-mono text-xs">

    <!-- Unified Datatable Header with Search & Filters -->
    <x-admin.datatable-header
        title="{{ __('admin.listing.galleries_title') }}"
        description="{{ __('admin.listing.galleries_desc') }}"
        search-placeholder="{{ __('admin.listing.galleries_search') }}"
        :create-route="route('admin.galleries.create')"
        create-label="+ Nuevo Álbum"
    >
        <!-- Custom Filter: Status -->
        <select name="is_active" class="bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            <option value="all">{{ __('admin.listing.all_statuses_cap') }}</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('admin.listing.active_f') }}</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('admin.listing.inactive_f') }}</option>
        </select>

        <!-- Custom Filter: Market -->
        <x-admin.market-select name="market_id" value-key="id" :value="request('market_id')" placeholder="{{ __('admin.listing.all_markets_cap') }}" />
    </x-admin.datatable-header>

    <!-- Galleries Table -->
    <x-admin.table
        :empty="$galleries->isEmpty()"
        emptyTitle="{{ __('admin.listing.galleries_empty_title') }}"
        emptyDescription="{{ __('admin.listing.galleries_empty_desc') }}"
        :emptyActionRoute="route('admin.galleries.create')"
        emptyActionLabel="+ Nuevo Álbum"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 w-16">{{ __('admin.listing.cover') }}</th>
            <x-admin.sortable-th column="title" label="{{ __('admin.listing.album_title') }}" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden sm:table-cell">{{ __('admin.listing.route_slug_short') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden md:table-cell">{{ __('admin.listing.photos') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden lg:table-cell">{{ __('admin.listing.markets') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden xl:table-cell">{{ __('admin.listing.languages') }}</th>
            <x-admin.sortable-th column="is_active" label="{{ __('admin.listing.status') }}" class="hidden md:table-cell" />
            <x-admin.sortable-th column="created_at" label="{{ __('admin.listing.date') }}" class="hidden xl:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-400">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($galleries as $gallery)
            @php
                $cover = $gallery->items->first();
                $trans = $gallery->translations->first();
            @endphp
            <tr class="hover:bg-gray-800/30 transition-colors">
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-gray-400 hover:text-white p-1 focus:outline-none" aria-label="{{ __('admin.listing.view_details') }}">
                        <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                    </button>
                </td>
                <td class="py-3.5 px-4 w-16">
                    <div class="w-12 h-12 bg-black rounded-lg overflow-hidden border border-gray-800 flex items-center justify-center">
                        @if($cover)
                            <img src="{{ $cover->image_url }}" alt="{{ $gallery->title }}" class="w-full h-full object-cover">
                        @else
                            <i class="fa-solid fa-image text-gray-600"></i>
                        @endif
                    </div>
                </td>
                <td class="py-3.5 px-4 font-bold text-white max-w-xs truncate">
                    {{ $gallery->title }}
                </td>
                <td class="py-3.5 px-4 text-[#00FF99] hidden sm:table-cell">
                    /galeria/{{ $gallery->slug }}
                </td>
                <td class="py-3.5 px-4 text-gray-300 hidden md:table-cell">
                    <x-admin.chip>
                        {{ $gallery->items_count }} fotos
                    </x-admin.chip>
                </td>
                <td class="py-3.5 px-4 hidden lg:table-cell">
                    <x-admin.market-chips :markets="$gallery->markets" />
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.locale-chips :locales="$gallery->translations->pluck('locale')" />
                </td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    <x-admin.status-badge :active="$gallery->is_active" activeText="Activa" inactiveText="Inactiva" />
                </td>
                <td class="py-3.5 px-4 text-gray-400 hidden xl:table-cell">{{ $gallery->created_at->format('Y-m-d') }}</td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            as="a"
                            href="{{ route('admin.galleries.edit', $gallery) }}"
                            icon="fa-solid fa-images"
                            tooltip="Gestionar fotos de la galería"
                            variant="primary"
                        />
                        <x-admin.action-button
                            as="a"
                            href="{{ app(\App\Contracts\Services\LocalizationServiceInterface::class)->frontUrl('gallery', $gallery->slug) }}"
                            target="_blank"
                            icon="fa-solid fa-arrow-up-right-from-square"
                            tooltip="Ver galería pública"
                            variant="view"
                        />
                        <x-admin.action-button
                            icon="fa-solid fa-trash-can"
                            tooltip="Eliminar galería"
                            variant="danger"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.galleries.destroy', $gallery) }}',
                                title: 'Eliminar Galería',
                                message: '¿Estás seguro de eliminar esta galería multimedia?'
                            })"
                        />
                    </div>
                </td>
            </tr>

            <x-admin.table-row-detail colSpan="9">
                <x-admin.table-row-detail-item label="{{ __('admin.listing.route_slug_short') }}">
                    <span class="text-[#00FF99]">/galeria/{{ $gallery->slug }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.total_photos') }}">
                    <x-admin.chip>{{ $gallery->items_count }} fotos</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.enabled_markets') }}">
                    <x-admin.market-chips :markets="$gallery->markets" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.languages') }}">
                    <x-admin.locale-chips :locales="$gallery->translations->pluck('locale')" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.status') }}">
                    <x-admin.status-badge :active="$gallery->is_active" activeText="Activa" inactiveText="Inactiva" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.created_at') }}">
                    <span class="text-gray-400">{{ $gallery->created_at->format('Y-m-d H:i') }}</span>
                </x-admin.table-row-detail-item>
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$galleries" />
        </x-slot:pagination>
    </x-admin.table>
</div>
@endsection
