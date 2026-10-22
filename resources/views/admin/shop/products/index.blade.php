@extends('admin.layout')

@section('title', __('admin.page_titles.shop_products'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs">

    <!-- Unified Datatable Header with Search, Filters & Actions -->
    <x-admin.datatable-header
        title="{{ __('admin.listing.products_title') }}"
        description="{{ __('admin.listing.products_desc') }}"
        searchPlaceholder="Buscar por SKU o nombre de producto..."
        :createRoute="route('admin.shop.products.create')"
        createLabel="+ Nuevo producto"
    >
        <x-slot:actions>
            <a href="{{ route('admin.shop.categories.index') }}" class="px-3.5 py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded-lg border border-[color:var(--admin-border)] text-xs flex items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-tag"></i>
                <span>{{ __('admin.listing.categories') }}</span>
            </a>
            <a href="{{ route('admin.modules.settings', ['identifier' => 'shop']) }}" class="px-3.5 py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded-lg border border-[color:var(--admin-border)] text-xs flex items-center gap-1.5 transition-colors">
                <i class="fa-solid fa-gear"></i>
                <span>{{ __('admin.listing.settings') }}</span>
            </a>
        </x-slot:actions>

        <!-- Custom Filter: Category -->
        <x-admin.search-select source="shop_categories" name="category_id" :value="request('category_id')" placeholder="{{ __('admin.listing.posts_all_categories') }}" />

        <!-- Custom Filter: Status -->
        <select name="is_active" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs">
            <option value="all">{{ __('admin.listing.all_statuses') }}</option>
            <option value="1" {{ request('is_active') === '1' || request('status') === 'active' ? 'selected' : '' }}>{{ __('admin.listing.active_m') }}</option>
            <option value="0" {{ request('is_active') === '0' || request('status') === 'inactive' ? 'selected' : '' }}>{{ __('admin.listing.inactive_m') }}</option>
        </select>
    </x-admin.datatable-header>

    <!-- Products Datatable Card -->
    <x-admin.table
        :empty="$products->isEmpty()"
        emptyTitle="{{ __('admin.listing.products_empty_title') }}"
        emptyDescription="{{ __('admin.listing.products_empty_desc') }}"
        :emptyActionRoute="route('admin.shop.products.create')"
        emptyActionLabel="+ Nuevo producto"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.listing.product') }}</th>
            <x-admin.sortable-th column="sku" label="{{ __('admin.listing.sku') }}" class="hidden md:table-cell" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden lg:table-cell">{{ __('admin.listing.category') }}</th>
            <x-admin.sortable-th column="price" label="{{ __('admin.listing.price') }}" class="hidden sm:table-cell" />
            <x-admin.sortable-th column="stock_quantity" label="{{ __('admin.listing.stock') }}" class="hidden xl:table-cell" />
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden xl:table-cell">{{ __('admin.listing.markets') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden xl:table-cell">{{ __('admin.listing.languages') }}</th>
            <x-admin.sortable-th column="is_active" label="{{ __('admin.listing.status') }}" class="hidden sm:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($products as $prod)
            @php
                $trans = $prod->translations->where('locale', 'es')->first() ?? $prod->translations->first();
                $slug = $trans?->slug ?? '';
            @endphp
            <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                <!-- Mobile Chevron Toggle -->
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button"
                            @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')"
                            class="w-6 h-6 rounded-md flex items-center justify-center text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] transition-colors"
                            :title="expanded ? 'Ocultar detalles' : 'Ver más información'">
                        <i class="fa-solid text-[10px] transition-transform duration-200"
                           :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                    </button>
                </td>

                <td class="py-3.5 px-4">
                    <div class="flex items-center gap-3">
                        @if($prod->featured_image)
                            <img src="{{ $prod->featured_image }}" class="w-9 h-9 object-cover rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] shrink-0">
                        @else
                            <div class="w-9 h-9 rounded-lg border border-[color:var(--admin-border)] bg-[color:var(--admin-surface)] flex items-center justify-center text-[color:var(--admin-muted)] text-sm shrink-0">
                                <i class="fa-solid fa-box-open"></i>
                            </div>
                        @endif
                        <div class="min-w-0">
                            <a href="{{ route('admin.shop.products.edit', $prod) }}" class="font-bold text-[color:var(--admin-text)] hover:text-[color:var(--admin-accent)] transition-colors truncate block">
                                {{ $trans?->name ?? 'Sin nombre' }}
                            </a>
                            @if($prod->is_featured)
                                <x-admin.chip variant="brand">{{ __('admin.listing.featured') }}</x-admin.chip>
                            @endif
                        </div>
                    </div>
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-text)] font-bold font-mono hidden md:table-cell">
                    {{ $prod->sku }}
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-muted)] hidden lg:table-cell">
                    {{ $prod->category?->translations->first()?->name ?? 'Sin categoría' }}
                </td>
                <td class="py-3.5 px-4 font-bold text-[color:var(--admin-accent)] hidden sm:table-cell">
                    {{ number_format($prod->price, 2) }} €
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    @if(!$prod->track_stock)
                        <span class="text-[color:var(--admin-muted)]">{{ __('admin.listing.no_tracking') }}</span>
                    @elseif($prod->stock_quantity > 0)
                        <x-admin.chip variant="success">{{ $prod->stock_quantity }} unid.</x-admin.chip>
                    @else
                        <x-admin.chip variant="danger">{{ __('admin.listing.out_of_stock') }}</x-admin.chip>
                    @endif
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.market-chips :markets="$prod->markets" />
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.locale-chips :locales="$prod->translations->pluck('locale')" />
                </td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <x-admin.status-badge :active="$prod->is_active" activeText="Activo" inactiveText="Inactivo" />
                </td>
                <td class="py-3.5 px-4 text-right">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            href="{{ route('admin.shop.products.edit', $prod) }}"
                            icon="fa-solid fa-pen-to-square"
                            tooltip="Editar producto"
                            variant="primary"
                        />
                        @if($slug)
                            <x-admin.action-button
                                href="{{ app(\App\Contracts\Services\LocalizationServiceInterface::class)->frontUrl('shop', $slug) }}"
                                target="_blank"
                                icon="fa-solid fa-arrow-up-right-from-square"
                                tooltip="Ver en tienda"
                                variant="view"
                            />
                        @endif
                        <x-admin.action-button
                            type="button"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.shop.products.destroy', $prod) }}',
                                title: 'Eliminar Producto',
                                message: '¿Estás seguro de eliminar este producto del catálogo?'
                            })"
                            icon="fa-solid fa-trash-can"
                            tooltip="Eliminar producto"
                            variant="danger"
                        />
                    </div>
                </td>
            </tr>

            <!-- Mobile Accordion Detail Row -->
            <x-admin.table-row-detail>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.sku') }}">
                    <span class="font-mono text-[color:var(--admin-text)] font-bold">{{ $prod->sku }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.category') }}" :value="$prod->category?->translations->first()?->name ?? 'Sin categoría'" />
                <x-admin.table-row-detail-item label="{{ __('admin.listing.price') }}">
                    <span class="text-[color:var(--admin-accent)] font-bold">{{ number_format($prod->price, 2) }} €</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.stock') }}">
                    @if(!$prod->track_stock)
                        <span class="text-[color:var(--admin-muted)]">{{ __('admin.listing.no_tracking') }}</span>
                    @elseif($prod->stock_quantity > 0)
                        <x-admin.chip variant="success">{{ $prod->stock_quantity }} unid.</x-admin.chip>
                    @else
                        <x-admin.chip variant="danger">{{ __('admin.listing.out_of_stock') }}</x-admin.chip>
                    @endif
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.markets') }}">
                    <x-admin.market-chips :markets="$prod->markets" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.languages') }}">
                    <x-admin.locale-chips :locales="$prod->translations->pluck('locale')" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.status') }}">
                    <x-admin.status-badge :active="$prod->is_active" activeText="Activo" inactiveText="Inactivo" />
                </x-admin.table-row-detail-item>
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$products" />
        </x-slot:pagination>
    </x-admin.table>

</div>
@endsection
