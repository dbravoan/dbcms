@extends('admin.layout')

@section('title', __('admin.page_titles.blog_categories'))

@section('content')
<div class="space-y-6 font-mono text-xs" x-data="{
    showCreateModal: false,
    showEditModal: false,
    editingCategory: { id: null, parent_id: '', is_active: false, name: '', translations: @js($languages->mapWithKeys(fn ($l) => [$l->code => ['name' => '', 'slug' => '', 'description' => '', 'slugTouched' => false]])->all()) },
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
            parent_id: cat.parent_id || '',
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

    <!-- Unified Datatable Header with Search & Filter -->
    <x-admin.datatable-header
        title="{{ __('admin.listing.blog_categories_title') }}"
        description="{{ __('admin.listing.blog_categories_desc') }}"
        searchPlaceholder="Buscar categoría..."
        createModalEvent="open-create-category-modal"
        createLabel="+ Nueva Categoría"
    >
        <!-- Custom Filter: Status -->
        <select name="is_active" class="bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            <option value="all">{{ __('admin.listing.all_statuses_cap') }}</option>
            <option value="1" {{ request('is_active') === '1' ? 'selected' : '' }}>{{ __('admin.listing.active_f') }}</option>
            <option value="0" {{ request('is_active') === '0' ? 'selected' : '' }}>{{ __('admin.listing.inactive_f') }}</option>
        </select>
    </x-admin.datatable-header>

    <!-- Categories Table -->
    <x-admin.table
        :empty="$categories->isEmpty()"
        emptyTitle="{{ __('admin.listing.blog_categories_empty_title') }}"
        emptyDescription="{{ __('admin.listing.blog_categories_empty_desc') }}"
        emptyActionEvent="open-create-category-modal"
        emptyActionLabel="+ Nueva Categoría"
    >
        <x-slot:header>
            <th class="py-3 px-2 md:hidden w-8"></th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400">{{ __('admin.listing.name') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden md:table-cell">{{ __('admin.listing.route_slug') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden lg:table-cell">{{ __('admin.listing.parent_category') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden lg:table-cell">{{ __('admin.listing.markets') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden xl:table-cell">{{ __('admin.listing.languages') }}</th>
            <th class="py-3 px-4 font-bold uppercase tracking-wider text-gray-400 hidden sm:table-cell">{{ __('admin.listing.articles') }}</th>
            <x-admin.sortable-th column="is_active" label="{{ __('admin.listing.status') }}" class="hidden sm:table-cell" />
            <x-admin.sortable-th column="created_at" label="{{ __('admin.listing.date') }}" class="hidden xl:table-cell" />
            <th class="py-3 px-4 text-right font-bold uppercase tracking-wider text-gray-400">{{ __('admin.listing.actions') }}</th>
        </x-slot:header>

        @foreach($categories as $category)
            @php
                $trans = $category->translations->where('locale', 'es')->first() ?? $category->translations->first();
                $slugPath = $category->getSlugPath('es');
                $transEn = $category->translations->where('locale', 'en')->first();
            @endphp
            <tr class="hover:bg-gray-800/30 transition-colors">
                <!-- Mobile Chevron Toggle -->
                <td class="py-3.5 px-2 md:hidden text-center w-8">
                    <button type="button"
                            @click="expanded = expanded === $el.closest('tr') ? null : $el.closest('tr')"
                            class="w-6 h-6 rounded-md flex items-center justify-center text-gray-400 hover:text-white transition-colors"
                            :title="expanded ? @js(__('admin.listing.hide_details')) : @js(__('admin.listing.show_details'))">
                        <i class="fa-solid text-[10px] transition-transform duration-200"
                           :class="expanded === $el.closest('tr') ? 'fa-chevron-up text-[#00FF99]' : 'fa-chevron-down'"></i>
                    </button>
                </td>

                <td class="py-3.5 px-4 font-bold text-white flex items-center gap-2">
                    @if($category->parent_id)
                        <span class="text-gray-600">↳</span>
                    @endif
                    <span>{{ $trans?->name ?? 'Sin Nombre' }}</span>
                </td>
                <td class="py-3.5 px-4 text-[#00FF99] font-mono hidden md:table-cell">
                    /blog/categoria/{{ $slugPath }}
                </td>
                <td class="py-3.5 px-4 text-gray-400 hidden lg:table-cell">
                    {{ $category->parent?->translations->first()?->name ?? '— (Raíz)' }}
                </td>
                <td class="py-3.5 px-4 hidden lg:table-cell">
                    <x-admin.market-chips :markets="$category->markets" empty="Global" />
                </td>
                <td class="py-3.5 px-4 hidden xl:table-cell">
                    <x-admin.locale-chips :locales="$category->translations->pluck('locale')" />
                </td>
                <td class="py-3.5 px-4 text-gray-300 hidden sm:table-cell">
                    <x-admin.chip>
                        {{ $category->posts_count }} artículos
                    </x-admin.chip>
                </td>
                <td class="py-3.5 px-4 hidden sm:table-cell">
                    <x-admin.status-badge :active="$category->is_active" activeText="Activa" inactiveText="Inactiva" />
                </td>
                <td class="py-3.5 px-4 text-gray-400 hidden xl:table-cell">{{ $category->created_at->format('Y-m-d') }}</td>
                <td class="py-3.5 px-4 text-right">
                    <div class="inline-flex items-center justify-end gap-1.5">
                        @if($category->getAttributes()['pending_revision_id'] ?? null)
                            <x-admin.action-button href="{{ $category->getAttribute('review_url') }}" icon="fa-solid fa-clipboard-check" tooltip="Revisar cambios pendientes" variant="primary" />
                        @endif
                        <x-admin.action-button
                            type="button"
                            @click="openEdit({{ json_encode($category) }}, '{{ route('admin.blog.categories.update', $category) }}')"
                            icon="fa-solid fa-pen-to-square"
                            tooltip="Editar categoría"
                            variant="primary"
                        />
                        <x-admin.action-button
                            href="{{ app(\App\Contracts\Services\LocalizationServiceInterface::class)->frontUrl('blog', $slugPath) }}"
                            target="_blank"
                            icon="fa-solid fa-arrow-up-right-from-square"
                            tooltip="Ver en blog"
                            variant="view"
                        />
                        <x-admin.action-button
                            type="button"
                            @click="$dispatch('confirm-delete', {
                                action: '{{ route('admin.blog.categories.destroy', $category) }}',
                                title: 'Eliminar Categoría',
                                message: '¿Estás seguro de eliminar esta categoría del blog?'
                            })"
                            icon="fa-solid fa-trash-can"
                            tooltip="Eliminar categoría"
                            variant="danger"
                        />
                    </div>
                </td>
            </tr>

            <!-- Mobile Accordion Detail Row -->
            <x-admin.table-row-detail>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.route_slug') }}">
                    <span class="text-[#00FF99] font-mono">/blog/categoria/{{ $slugPath }}</span>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.parent_category') }}" :value="$category->parent?->translations->first()?->name ?? '— (Raíz)'" />
                <x-admin.table-row-detail-item label="{{ __('admin.listing.markets') }}">
                    <x-admin.market-chips :markets="$category->markets" empty="Global" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.languages') }}">
                    <x-admin.locale-chips :locales="$category->translations->pluck('locale')" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.articles') }}">
                    <x-admin.chip>{{ $category->posts_count }} artículos</x-admin.chip>
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.status') }}">
                    <x-admin.status-badge :active="$category->is_active" activeText="Activa" inactiveText="Inactiva" />
                </x-admin.table-row-detail-item>
                <x-admin.table-row-detail-item label="{{ __('admin.listing.created_at') }}" :value="$category->created_at->format('Y-m-d')" />
            </x-admin.table-row-detail>
        @endforeach

        <x-slot:pagination>
            <x-admin.datatable-pagination :paginator="$categories" />
        </x-slot:pagination>
    </x-admin.table>

    <!-- Create Category Modal -->
    <!-- Create Category Modal -->
    <div x-data="{ open: false }" @open-create-category-modal.window="open = true">
        <x-admin.modal
            show="open"
            title="{{ __('admin.listing.create_category_title') }}"
            subtitle="{{ __('admin.listing.create_category_subtitle') }}"
            icon="fa-solid fa-folder-plus"
            maxWidth="xl"
        >
            @include('admin.blog.categories._form', [
                'category' => null,
                'allCategories' => $allCategories,
                'languages' => $languages ?? collect(),
                'markets' => $markets ?? collect(),
            ])
        </x-admin.modal>
    </div>

    <!-- Edit Category Modal -->
    <x-admin.modal
        show="showEditModal"
        onClose="showEditModal = false"
        maxWidth="xl"
        icon="fa-solid fa-folder-tree"
    >
        <x-slot:header>
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-[#00FF99]/10 border border-[#00FF99]/30 flex items-center justify-center text-[#00FF99] shrink-0">
                    <i class="fa-solid fa-folder-tree"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>&gt; {{ __('admin.listing.categories_edit_title') }}:</span>
                        <span class="text-[#00FF99]" x-text="editingCategory?.name"></span>
                    </h3>
                    <p class="text-[11px] text-gray-400">{{ __('admin.listing.categories_edit_desc') }}</p>
                </div>
            </div>
        </x-slot:header>

        <form method="POST" :action="editUrl" class="space-y-4 text-xs font-mono"
              x-on:locale-tab-change="editLocale = $event.detail.locale">
            @csrf
            @method('PUT')

            <template x-if="editingCategory">
                <div class="space-y-4">
                    <x-admin.locale-tabs :languages="$languages" :active="$languages->first()->code ?? 'es'" group="blog-category-edit" />

                    @foreach($languages as $lang)
                        <div x-show="editLocale === '{{ $lang->code }}'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">Nombre ({{ strtoupper($lang->code) }}) *</label>
                                <input type="text"
                                       name="translations[{{ $lang->code }}][name]"
                                       x-model="editingCategory.translations['{{ $lang->code }}'].name"
                                       @input="editEnsure('{{ $lang->code }}'); if (! editingCategory.translations['{{ $lang->code }}'].slugTouched) editSlug('{{ $lang->code }}')"
                                       class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">Slug URL ({{ strtoupper($lang->code) }})</label>
                                <input type="text"
                                       name="translations[{{ $lang->code }}][slug]"
                                       x-model="editingCategory.translations['{{ $lang->code }}'].slug"
                                       @input="editingCategory.translations['{{ $lang->code }}'].slugTouched = true"
                                       class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-[#00FF99]">
                            </div>
                        </div>
                    @endforeach

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.listing.parent_category_hier') }}</label>
                            <x-admin.search-select
                                source="blog_categories"
                                name="parent_id"
                                placeholder="{{ __('admin.entity_form.ph_no_parent') }}"
                                x-effect="hydrate(editingCategory ? editingCategory.parent_id : null)" />
                        </div>
                        <div>
                            <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.listing.assigned_markets') }}</label>
                            <x-admin.market-picker name="market_ids" :markets="$markets" group="blog-category-edit" x-effect="hydrate(editingMarketIds)" />
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" :checked="editingCategory.is_active" class="rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99]">
                            <span class="text-gray-300">{{ __('admin.entity_form.category_active') }}</span>
                        </label>
                    </div>
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-800">
                        <button type="button" @click="showEditModal = false" class="px-4 py-2 bg-gray-800 text-gray-400 hover:text-white rounded-lg">{{ __('admin.actions.cancel') }}</button>
                        <button type="submit" class="btn-neon">{{ __('admin.listing.save_changes') }} &rarr;</button>
                    </div>
                </div>
            </template>
        </form>
    </x-admin.modal>

</div>
@endsection
