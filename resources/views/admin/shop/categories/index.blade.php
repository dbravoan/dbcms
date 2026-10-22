@extends('admin.layout')

@section('title', __('admin.page_titles.shop_categories'))

@section('content')
<div class="admin-page space-y-6 font-mono text-xs" x-data="{
    showCreateModal: false,
    showEditModal: false,
    editingCategory: { id: null, parent_id: '', is_active: false, name: '', icon: 'fa-solid fa-tag', order: 0, translations: @js($languages->mapWithKeys(fn ($l) => [$l->code => ['name' => '', 'slug' => '', 'description' => '', 'slugTouched' => false]])->all()) },
    editingMarketIds: [],
    editLocale: @js($languages->first()->code ?? 'es'),
    editUrl: '',
    openEdit(cat, url) {
        const translations = this.editingCategory.translations;
        (cat.translations || []).forEach((t) => {
            translations[t.locale] = { name: t.name, slug: t.slug, description: t.description || '', slugTouched: true };
        });
        this.editingCategory = {
            id: cat.id,
            translations: translations,
            icon: cat.icon || 'fa-solid fa-tag',
            parent_id: cat.parent_id || '',
            order: cat.order || 0,
            is_active: Boolean(cat.is_active),
            name: (cat.translations && cat.translations[0]) ? cat.translations[0].name : ''
        };
        this.editingMarketIds = (cat.markets || []).map((m) => String(m.id));
        this.editUrl = url;
        this.showEditModal = true;
    },
    editEnsure(code) {
        if (this.editingCategory && ! this.editingCategory.translations[code]) {
            this.editingCategory.translations[code] = { name: '', slug: '', description: '', slugTouched: false };
        }
    },
    editSlug(code) {
        const name = (this.editingCategory?.translations[code]?.name) || '';
        this.editingCategory.translations[code].slug = name.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)+/g, '');
    }
}">

    <!-- Unified Datatable Header with Search & Filters -->
    <x-admin.datatable-header
        title="{{ __('admin.listing.shop_categories_title') }}"
        description="{{ __('admin.listing.shop_categories_desc') }}"
        searchPlaceholder="Buscar categoría por nombre o slug..."
        createModalEvent="open-create-shop-category-modal"
        createLabel="+ Nueva categoría"
    >
        <x-slot:actions>
            <a href="{{ route('admin.shop.products.index') }}" class="px-3.5 py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded-lg border border-[color:var(--admin-border)] text-xs flex items-center gap-1.5 transition-colors">
                <span>&larr;</span>
                <span>{{ __('admin.listing.back_to_products') }}</span>
            </a>
        </x-slot:actions>

        <!-- Custom Filter: Status -->
        <select name="is_active" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs">
            <option value="all">{{ __('admin.listing.all_statuses') }}</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('admin.listing.active_f') }}</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('admin.listing.inactive_f') }}</option>
        </select>
    </x-admin.datatable-header>

    <!-- Categories Table -->
    <x-admin.table
        :empty="$categories->isEmpty()"
        emptyTitle="{{ __('admin.listing.shop_categories_empty_title') }}"
        emptyDescription="{{ __('admin.listing.shop_categories_empty_desc') }}"
        emptyActionEvent="open-create-shop-category-modal"
        emptyActionLabel="+ Nueva categoría"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.listing.category') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden sm:table-cell">{{ __('admin.listing.route_slug_o') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden lg:table-cell">{{ __('admin.listing.parent_category_lower') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden lg:table-cell">{{ __('admin.listing.markets') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden xl:table-cell">{{ __('admin.listing.languages') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-[color:var(--admin-muted)] hidden md:table-cell">{{ __('admin.listing.products') }}</th>
            <x-admin.sortable-th column="order" label="{{ __('admin.listing.order') }}" class="hidden xl:table-cell" />
            <x-admin.sortable-th column="is_active" label="{{ __('admin.listing.status') }}" class="hidden md:table-cell" />
            <x-admin.sortable-th column="created_at" label="{{ __('admin.listing.date') }}" class="hidden lg:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-[color:var(--admin-muted)]">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($categories as $cat)
            @php
                $trans = $cat->translations->where('locale', 'es')->first() ?? $cat->translations->first();
                $transEn = $cat->translations->where('locale', 'en')->first();
                $slug = $trans?->slug ?? '';
            @endphp
            <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button" @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] p-1 focus:outline-none" aria-label="{{ __('admin.listing.view_details') }}">
                        <i class="fa-solid text-[10px]" :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[color:var(--admin-accent)]' : 'fa-chevron-down'"></i>
                    </button>
                </td>
                <td class="py-3.5 px-4 font-bold text-[color:var(--admin-text)]">
                    <div class="flex items-center gap-2">
                        @if($cat->parent_id)
                            <span class="text-[color:var(--admin-muted)]">↳</span>
                        @endif
                        @if($cat->icon && str_contains($cat->icon, 'fa-'))
                            <i class="{{ $cat->icon }} text-[color:var(--admin-accent)]"></i>
                        @elseif($cat->icon && !preg_match('/[\x{10000}-\x{10FFFF}]/u', $cat->icon))
                            <span>{{ $cat->icon }}</span>
                        @else
                            <i class="fa-solid fa-tag text-[color:var(--admin-accent)]"></i>
                        @endif
                        <span>{{ $trans?->name ?? 'Sin nombre' }}</span>
                    </div>
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-accent)] hidden sm:table-cell">
                    /shop/category/{{ $slug }}
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-muted)] hidden lg:table-cell">
                    {{ $cat->parent?->translations->first()?->name ?? '— (Raíz)' }}
                </td>
                <td class="py-3.5 px-4 hidden lg:table-cell">
                    <x-admin.market-chips :markets="$cat->markets" empty="Global" />
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.locale-chips :locales="$cat->translations->pluck('locale')" />
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-text)] hidden md:table-cell">
                    <x-admin.chip>
                        {{ $cat->products_count }} productos
                    </x-admin.chip>
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-muted)] hidden xl:table-cell">
                    {{ $cat->order ?? 0 }}
                </td>
                <td class="py-3.5 px-4 hidden md:table-cell">
                    <x-admin.status-badge :active="$cat->is_active" activeText="Activa" inactiveText="Inactiva" />
                </td>
                <td class="py-3.5 px-4 text-[color:var(--admin-muted)] hidden lg:table-cell">{{ $cat->created_at?->format('Y-m-d') }}</td>
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        <x-admin.action-button
                            icon="fa-solid fa-pen-to-square"
                            tooltip="Editar categoría"
                            variant="primary"
                            @click="openEdit({{ json_encode($cat) }}, '{{ route('admin.shop.categories.update', $cat) }}')"
                        />
                        @if($slug)
                            <x-admin.action-button
                                as="a"
                                href="{{ app(\App\Contracts\Services\LocalizationServiceInterface::class)->frontUrl('shop', $slug) }}"
                                target="_blank"
                                icon="fa-solid fa-arrow-up-right-from-square"
                                tooltip="Ver en tienda"
                                variant="view"
                            />
                        @endif
                        <x-admin.action-button
                            icon="fa-solid fa-trash-can"
                            tooltip="Eliminar categoría"
                            variant="danger"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.shop.categories.destroy', $cat) }}',
                                title: 'Eliminar categoría de tienda',
                                message: '¿Estás seguro de que deseas eliminar esta categoría de la tienda?'
                            })"
                        />
                    </div>
                </td>
            </tr>

            <x-admin.table-row-detail colSpan="9">
                <x-admin.table-row-detail-item label="{{ __('admin.listing.route_slug_short') }}">
                    <span class="text-[color:var(--admin-accent)]">/shop/category/{{ $slug }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.parent_category_lower') }}">
                    <span class="text-[color:var(--admin-text)]">{{ $cat->parent?->translations->first()?->name ?? '— (Raíz)' }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.markets') }}">
                    <x-admin.market-chips :markets="$cat->markets" empty="Global" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.languages') }}">
                    <x-admin.locale-chips :locales="$cat->translations->pluck('locale')" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.linked_products') }}">
                    <x-admin.chip>{{ $cat->products_count }} productos</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.status') }}">
                    <x-admin.status-badge :active="$cat->is_active" activeText="Activa" inactiveText="Inactiva" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.order') }}">
                    <span class="text-[color:var(--admin-text)]">{{ $cat->order ?? 0 }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.created_at') }}">
                    <span class="text-[color:var(--admin-muted)]">{{ $cat->created_at?->format('Y-m-d H:i') }}</span>
                </x-admin.table-row-detail-item>
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$categories" />
        </x-slot:pagination>
    </x-admin.table>

    <!-- Create Shop Category Modal -->
    <div x-data="{ open: false }" @open-create-shop-category-modal.window="open = true">
        <x-admin.modal
            show="open"
            title="{{ __('admin.listing.create_shop_category_title') }}"
            subtitle="{{ __('admin.listing.create_shop_category_subtitle') }}"
            icon="fa-solid fa-tags"
            maxWidth="xl"
        >
            @include('admin.shop.categories._form', [
                'category' => null,
                'allCategories' => $allCategories,
                'languages' => $languages ?? collect(),
                'markets' => $markets ?? collect(),
            ])
        </x-admin.modal>
    </div>

    <!-- Edit Shop Category Modal -->
    <x-admin.modal
        show="showEditModal"
        onClose="showEditModal = false"
        maxWidth="xl"
        icon="fa-solid fa-pen-to-square"
    >
        <x-slot:header>
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/30 flex items-center justify-center text-[color:var(--admin-accent)] shrink-0">
                    <i class="fa-solid fa-pen-to-square"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                        <span>&gt; {{ __('admin.listing.shop_categories_edit_title') }}:</span>
                        <span class="text-[color:var(--admin-accent)]" x-text="editingCategory?.name"></span>
                    </h3>
                    <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.listing.shop_categories_edit_desc') }}</p>
                </div>
            </div>
        </x-slot:header>

        <template x-if="editingCategory">
            <form method="POST" :action="editUrl" class="space-y-4 text-xs font-mono"
              x-on:locale-tab-change="editLocale = $event.detail.locale">
            @csrf
            @method('PUT')

            <template x-if="editingCategory">
                <div class="space-y-4">
                    <x-admin.locale-tabs :languages="$languages" :active="$languages->first()->code ?? 'es'" group="shop-category-edit" />

                    @foreach($languages as $lang)
                        <div x-show="editLocale === '{{ $lang->code }}'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">Nombre ({{ strtoupper($lang->code) }}) *</label>
                                <input type="text"
                                       name="translations[{{ $lang->code }}][name]"
                                       x-model="editingCategory.translations['{{ $lang->code }}'].name"
                                       @input="editEnsure('{{ $lang->code }}'); if (! editingCategory.translations['{{ $lang->code }}'].slugTouched) editSlug('{{ $lang->code }}')"
                                       class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                            </div>
                            <div>
                                <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">Slug URL ({{ strtoupper($lang->code) }})</label>
                                <input type="text"
                                       name="translations[{{ $lang->code }}][slug]"
                                       x-model="editingCategory.translations['{{ $lang->code }}'].slug"
                                       @input="editingCategory.translations['{{ $lang->code }}'].slugTouched = true"
                                       class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-mono">
                            </div>
                        </div>
                    @endforeach

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.listing.icon_class') }}</label>
                            <input type="text" name="icon" x-model="editingCategory.icon" placeholder="fa-solid fa-tag" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                        </div>
                        <div>
                            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.listing.order') }}</label>
                            <input type="number" name="order" x-model="editingCategory.order" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.listing.parent_category_lower') }}</label>
                            <x-admin.search-select
                                source="shop_categories"
                                name="parent_id"
                                placeholder="{{ __('admin.entity_form.ph_no_parent_lower') }}"
                                x-effect="hydrate(editingCategory ? editingCategory.parent_id : null)" />
                        </div>
                        <div>
                            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.assigned_markets_lower') }}</label>
                            <x-admin.market-picker name="market_ids" :markets="$markets" group="shop-category-edit" x-effect="hydrate(editingMarketIds)" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" x-model="editingCategory.is_active" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                            <span class="text-[color:var(--admin-text)]">{{ __('admin.entity_form.category_active_lower') }}</span>
                        </label>
                    </div>
                    <x-admin.form-actions :submit="false" class="flex justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] rounded-lg">{{ __('admin.actions.cancel') }}</button>
                        <x-admin.button variant="primary" type="submit" class="">{{ __('admin.listing.save_changes') }} &rarr;</x-admin.button>
                    </x-admin.form-actions>
                </div>
            </template>
        </form>
        </template>
    </x-admin.modal>

</div>
@endsection
