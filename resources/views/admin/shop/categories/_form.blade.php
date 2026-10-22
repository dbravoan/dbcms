@props([
    'category' => null,
    'allCategories' => [],
    'languages' => [],
    'markets' => [],
])

@php
    $isEdit = !empty($category && $category->id);
    $actionUrl = $isEdit ? route('admin.shop.categories.update', $category) : route('admin.shop.categories.store');

    $langs = $languages instanceof \Illuminate\Support\Collection ? $languages : collect($languages);
    if ($langs->isEmpty()) {
        $langs = collect([
            (object) ['code' => 'es', 'name' => 'Español'],
            (object) ['code' => 'en', 'name' => 'English'],
        ]);
    }

    $translationsMap = [];
    foreach ($langs as $lang) {
        $translationsMap[$lang->code] = ['name' => '', 'slug' => '', 'description' => '', 'slugTouched' => false];
    }
    if ($category) {
        foreach ($category->translations as $translation) {
            $translationsMap[$translation->locale] = [
                'name' => $translation->name,
                'slug' => $translation->slug,
                'description' => $translation->description ?? '',
            ];
        }
    }

    $assignedMarketIds = old('market_ids', $category ? $category->markets->pluck('id')->all() : []);
    $defaultLocale = $langs->first()->code ?? 'es';
@endphp

<form method="POST" action="{{ $actionUrl }}" class="space-y-4 text-xs font-mono"
      x-data="{
        activeLocale: @js($defaultLocale),
        translations: @js($translationsMap),
        icon: @js(old('icon', $category?->icon ?? 'fa-solid fa-tag')),
        order: @js(old('order', $category?->order ?? 0)),
        ensure(code) {
            if (! this.translations[code]) {
                this.translations[code] = { name: '', slug: '', description: '', slugTouched: false };
            }
            if (this.translations[code].slugTouched === undefined) {
                this.translations[code].slugTouched = false;
            }
        },
        switchLocale(code) { this.ensure(code); this.activeLocale = code; },
        generateSlug(code) {
            const name = (this.translations[code]?.name) || '';
            this.translations[code].slug = name.toLowerCase()
                .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/(^-|-$)+/g, '');
        }
      }"
      x-init="Object.keys(translations).forEach((code) => ensure(code)); ensure(activeLocale);"
      x-on:locale-tab-change="switchLocale($event.detail.locale)">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <x-admin.locale-tabs :languages="$langs" :active="$defaultLocale" group="shop-category-create" />

    @foreach($langs as $lang)
        <div x-show="activeLocale === '{{ $lang->code }}'" class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">Nombre ({{ strtoupper($lang->code) }}) *</label>
                <input type="text"
                       name="translations[{{ $lang->code }}][name]"
                       x-model="translations['{{ $lang->code }}'].name"
                       @input="if (! translations['{{ $lang->code }}'].slugTouched) generateSlug('{{ $lang->code }}')"
                       placeholder="{{ __('admin.entity_form.ph_shop_category_name') }}"
                       class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
            </div>
            <div>
                <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">Slug URL ({{ strtoupper($lang->code) }})</label>
                <div class="flex items-center gap-2">
                    <input type="text"
                           name="translations[{{ $lang->code }}][slug]"
                           x-model="translations['{{ $lang->code }}'].slug"
                           @input="translations['{{ $lang->code }}'].slugTouched = true"
                           placeholder="{{ __('admin.entity_form.ph_shop_category_slug') }}"
                           class="admin-control flex-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-mono">
                    <button type="button" @click="generateSlug('{{ $lang->code }}'); translations['{{ $lang->code }}'].slugTouched = false" class="px-2 py-1.5 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[10px]">Sync</button>
                </div>
            </div>
            <div class="md:col-span-2">
                <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">Descripción ({{ strtoupper($lang->code) }})</label>
                <textarea name="translations[{{ $lang->code }}][description]"
                          x-model="translations['{{ $lang->code }}'].description"
                          rows="2"
                          placeholder="{{ __('admin.entity_form.ph_shop_category_desc') }}"
                          class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]"></textarea>
            </div>
        </div>
    @endforeach

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.icon_class') }}</label>
            <input type="text" name="icon" x-model="icon" placeholder="fa-solid fa-tag" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
        </div>
        <div>
            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.display_order') }}</label>
            <input type="number" name="order" x-model="order" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]">
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.parent_category_lower') }}</label>
            <x-admin.search-select
                source="shop_categories"
                name="parent_id"
                :value="old('parent_id', $category?->parent_id)"
                placeholder="{{ __('admin.entity_form.ph_no_parent_lower') }}" />
        </div>
        <div>
            <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.assigned_markets_lower') }}</label>
            <x-admin.market-picker name="market_ids" :selected="$assignedMarketIds" :markets="$markets" group="shop-category-create" />
        </div>
    </div>

    <div class="flex items-center justify-between pt-2">
        <label class="flex items-center gap-2 cursor-pointer">
            <input type="hidden" name="is_active" value="0">
                            <input type="checkbox" name="is_active" value="1" {{ old('is_active', $category?->is_active ?? false) ? 'checked' : '' }} class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
            <span class="text-[color:var(--admin-text)]">{{ __('admin.entity_form.category_active_lower') }}</span>
        </label>
    </div>

    <x-admin.form-actions :submit="false" class="flex justify-end gap-3 pt-4 border-t border-[color:var(--admin-border)]">
        <x-admin.button variant="primary" type="submit" class="">
            {{ $isEdit ? 'Guardar cambios' : 'Crear categoría' }} &rarr;
        </x-admin.button>
    </x-admin.form-actions>
</form>
