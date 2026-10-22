@props([
    'product' => null,
    'categories' => [],
    'markets' => [],
    'languages' => [],
    'isShop' => true,
])

@php
    $isEdit = !empty($product && $product->id);
    $actionUrl = $isEdit ? route('admin.shop.products.update', $product) : route('admin.shop.products.store');

    $allLangs = !empty($languages) && count($languages) > 0
        ? $languages
        : ($activeLanguages ?? collect());

    if ($allLangs->isEmpty()) {
        $allLangs = collect([$defaultLanguage ?? (object) ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸']]);
    }

    // El idioma del contenido por defecto sale de la BD (reactivo), nunca fijo.
    $defaultLocale = $defaultLocale ?? ($activeLanguages ?? $allLangs)->first()?->code ?? 'es';
    $translationsMap = [];
    foreach ($allLangs as $lang) {
        $trans = $product ? $product->translations->where('locale', $lang->code)->first() : null;
        $translationsMap[$lang->code] = [
            'name' => old("translations.{$lang->code}.name", $trans?->name ?? ''),
            'slug' => old("translations.{$lang->code}.slug", $trans?->slug ?? ''),
            'summary' => old("translations.{$lang->code}.summary", $trans?->summary ?? ''),
            'description' => old("translations.{$lang->code}.description", $trans?->description ?? ''),
            'meta_title' => old("translations.{$lang->code}.meta_title", $trans?->meta_title ?? ''),
            'meta_description' => old("translations.{$lang->code}.meta_description", $trans?->meta_description ?? ''),
            'schema_type' => old("translations.{$lang->code}.schema_type", $trans?->schema_type ?? 'Product'),
        ];
    }

    $assignedMarketIds = old('market_ids', $product ? $product->markets->pluck('id')->all() : []);
    if (empty($assignedMarketIds) && !empty($markets)) {
        $assignedMarketIds = collect($markets)->pluck('id')->all();
    }


    $sectionsData = old('sections_json', $sectionsData ?? []);
@endphp

<form method="POST" action="{{ $actionUrl }}" class="space-y-8 font-mono text-xs" x-on:locale-tab-change="switchLocale($event.detail.locale)"
      data-cms-translation-editor
      x-on:cms-translation-apply.window="if ($event.detail.form === $el && allLanguages.includes($event.detail.targetLocale)) { translations[$event.detail.targetLocale] = {...translations[$event.detail.targetLocale], ...$event.detail.values}; switchLocale($event.detail.targetLocale); }"
      @cms-set-featured-image.window="featuredImage = $event.detail.url"
      x-data="{
    activeLocale: @js($defaultLocale),
    defaultLocale: @js($defaultLocale),
    translations: @js($translationsMap),
    prefilledFromDefault: {},
    allLanguages: @js($allLangs->pluck('code')),

    sku: @js(old('sku', $product?->sku ?? 'SKU-' . strtoupper(\Illuminate\Support\Str::random(6)))),
    price: @js(old('price', $product?->price ?? 0.00)),
    comparePrice: @js(old('compare_price', $product?->compare_price ?? '')),
    stockQty: @js(old('stock_quantity', $product?->stock_quantity ?? 10)),
    trackStock: @js(old('track_stock', $product?->track_stock ?? true)),

    featuredImage: @js(old('featured_image', $product?->featured_image ?? '')),

    init() {
        for (const code of this.allLanguages) {
            if (!this.translations[code]) {
                this.translations[code] = {
                    name: '',
                    slug: '',
                    summary: '',
                    description: '',
                    meta_title: '',
                    meta_description: '',
                    schema_type: 'Product'
                };
            }
        }
    },


    copySourceLocale: 'es',
    copySuccessMessage: '',

    hasData(code) {
        return !!(this.translations[code]?.name && this.translations[code].name.trim() !== '');
    },

    switchLocale(code) {
        if (!this.translations[code]) {
            this.translations[code] = {
                name: '',
                slug: '',
                summary: '',
                description: '',
                meta_title: '',
                meta_description: '',
                schema_type: 'Product'
            };
        }
        this.activeLocale = code;
        window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } }));
    },

    copyFrom(sourceCode) {
        if (!this.translations[sourceCode] || !this.translations[sourceCode].name) {
            alert('El idioma de origen no contiene datos para copiar.');
            return;
        }

        const src = this.translations[sourceCode];
        this.translations[this.activeLocale].name = src.name;
        this.translations[this.activeLocale].slug = this.generateSlug(src.name) || (src.slug ? src.slug + '-' + this.activeLocale : '');
        this.translations[this.activeLocale].summary = src.summary || '';
        this.translations[this.activeLocale].description = src.description || '';
        this.translations[this.activeLocale].meta_title = src.meta_title || src.name;
        this.translations[this.activeLocale].meta_description = src.meta_description || '';
        this.translations[this.activeLocale].schema_type = src.schema_type || 'Product';

        window.dispatchEvent(new CustomEvent('cms-copy-locale', {
            detail: { from: sourceCode, to: this.activeLocale }
        }));
    },

    copySuccessMessage: '',
    slugTouched: {},

    generateSlug(localeOrText) {
        const text = (typeof localeOrText === 'string' && this.translations[localeOrText]) 
            ? (this.translations[localeOrText].name || '') 
            : (localeOrText || this.translations[this.activeLocale]?.name || '');
        const s = (text || '').toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)+/g, '');
        if (typeof localeOrText === 'string' && this.translations[localeOrText]) {
            this.translations[localeOrText].slug = s;
        } else if (this.translations[this.activeLocale]) {
            this.translations[this.activeLocale].slug = s;
        }
        return s;
    },

    openFeaturedPicker() {
        window.addEventListener('file-selected', (e) => {
            if (e.detail?.target === 'prod-featured-image') {
                this.featuredImage = e.detail.file.url;
            }
        }, { once: true });

        window.dispatchEvent(new CustomEvent('open-filemanager', {
            detail: { target: 'prod-featured-image' }
        }));
    }
}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column: Product Information, Tabs & Modular Sections (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Title, SKU & Multi-Language Card -->
            <x-admin.form-card class="space-y-4">
                <!-- Dynamic Language Switcher Bar & Copy Action -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[color:var(--admin-border)] pb-3">
                    <x-admin.locale-tabs :languages="$allLangs" :flags="$flags ?? []" :active="$allLangs->first()->code ?? 'es'" group="product-form" />

                    <!-- Quick Copy from Language Dropdown -->
                    <div class="flex items-center gap-2">
                        <select x-model="copySourceLocale" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] text-xs rounded px-2.5 py-1">
                            @foreach($allLangs as $lang)
                                <option value="{{ $lang->code }}" x-show="'{{ $lang->code }}' !== activeLocale">Copiar de {{ $lang->name }} ({{ strtoupper($lang->code) }})</option>
                            @endforeach
                        </select>
                        <button type="button" @click="copyFrom(copySourceLocale)" class="px-2.5 py-1 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-xs font-bold border border-[color:var(--admin-border)]">
                            Copiar a <span x-text="activeLocale.toUpperCase()"></span>
                        </button>
                    </div>
                </div>

                <!-- Empty Language Helper Banner -->
                <div x-show="!hasData(activeLocale)" class="p-3.5 rounded-xl bg-blue-950/40 border border-blue-500/30 text-blue-200 text-xs flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-globe text-base text-blue-400"></i>
                        <span>{{ __('admin.entity_form.info_prefix') }}<strong x-text="activeLocale.toUpperCase()"></strong>{{ __('admin.entity_form.info_suffix') }}</span>
                    </div>
                    <x-admin.button variant="primary" type="button" @click="copyFrom(copySourceLocale)" class="text-xs py-1 px-3">
                        Copiar contenidos y bloques &rarr;
                    </x-admin.button>
                </div>

                <!-- Success Toast -->
                <div x-show="copySuccessMessage" x-cloak class="p-2.5 rounded-lg bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)] text-xs flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span x-text="copySuccessMessage"></span>
                    </span>
                    <button type="button" @click="copySuccessMessage = ''" class="text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]">&times;</button>
                </div>

                <!-- Basic SKU & Identifiers -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.sku') }} *</label>
                        <input type="text" name="sku" x-model="sku" required placeholder="{{ __('admin.entity_form.ph_sku') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-mono font-bold">
                    </div>
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.catalog_category') }}</label>
                        <x-admin.search-select
                            source="shop_categories"
                            name="category_id"
                            :value="old('category_id', $product?->category_id)"
                            placeholder="{{ __('admin.entity_form.ph_category') }}"
                        />
                    </div>
                </div>

                <!-- Dynamic Language Inputs for Active Locale -->
                <div class="space-y-4">
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            Nombre del producto (<span x-text="activeLocale.toUpperCase()"></span>) *
                        </label>
                        <input type="text"
                               :name="'translations[' + activeLocale + '][name]'"
                               x-model="translations[activeLocale].name"
                               @input="if(!slugTouched[activeLocale]) generateSlug(activeLocale)"
                               required
                               placeholder="{{ __('admin.entity_form.ph_product_name') }}"
                               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] font-bold text-sm">
                    </div>

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            Identificador de ruta (slug) (<span x-text="activeLocale.toUpperCase()"></span>) *
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="text-[color:var(--admin-muted)] text-xs">/shop/</span>
                            <input type="text"
                                   :name="'translations[' + activeLocale + '][slug]'"
                                   x-model="translations[activeLocale].slug"
                                   @input="slugTouched[activeLocale] = true"
                                   required
                                   placeholder="{{ __('admin.entity_form.ph_product_slug') }}"
                                   class="admin-control flex-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-mono">
                            <button type="button" @click="generateSlug(activeLocale); slugTouched[activeLocale] = false" class="px-2.5 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[10px]">{{ __('admin.entity_form.generate_from_name') }}</button>
                        </div>
                    </div>

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            Resumen o descripción breve (<span x-text="activeLocale.toUpperCase()"></span>)
                        </label>
                        <textarea :name="'translations[' + activeLocale + '][summary]'"
                                  x-model="translations[activeLocale].summary"
                                  rows="2"
                                  placeholder="{{ __('admin.entity_form.ph_product_summary') }}"
                                  class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]"></textarea>
                    </div>

                    <!-- Preserve every inactive locale through the shared nested request contract. -->
                    <template x-for="code in allLanguages" :key="'translation-' + code">
                        <div>
                            <input type="hidden" :name="'translations[' + code + '][name]'" :value="translations[code]?.name || ''">
                            <input type="hidden" :name="'translations[' + code + '][slug]'" :value="translations[code]?.slug || ''">
                            <input type="hidden" :name="'translations[' + code + '][summary]'" :value="translations[code]?.summary || ''">
                            <input type="hidden" :name="'translations[' + code + '][description]'" :value="translations[code]?.description || ''">
                        </div>
                    </template>
                </div>

            </x-admin.form-card>

            <!-- Content Block Manager on Product Details -->
            <x-admin.form-card class="">
                <x-admin.block-manager :sections="$sectionsData" name="sections_json" :languages="$allLangs" :target="request('section')" />
            </x-admin.form-card>

        </div>

        <!-- Right Column: Pricing, Inventory, Multi-Market & Media (1 Col) -->
        <div class="space-y-6">

            <!-- Publishing & Status -->
            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                    &gt; Estado y visibilidad
                </h4>

                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{{ __('admin.entity_form.product_active') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $product?->is_active ?? false) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)]">{{ __('admin.entity_form.product_featured') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" class="sr-only peer" {{ old('is_featured', $product?->is_featured ?? false) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>
            </x-admin.form-card>

            <!-- Pricing & Inventory -->
            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                    &gt; Precios y existencias
                </h4>

                <div>
                    <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.base_price') }} *</label>
                    <input type="number" step="0.01" name="price" x-model="price" required class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-bold font-mono text-sm">
                </div>

                <div>
                    <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.compare_price') }}</label>
                    <input type="number" step="0.01" name="compare_price" x-model="comparePrice" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-muted)] font-mono">
                </div>

                <div class="grid grid-cols-2 gap-3 pt-2 border-t border-[color:var(--admin-border)]">
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.stock') }}</label>
                        <input type="number" name="stock_quantity" x-model="stockQty" min="0" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] font-mono">
                    </div>
                    <div class="flex items-center pt-5">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="hidden" name="track_stock" value="0">
                        <input type="checkbox" name="track_stock" value="1" x-model="trackStock" class="rounded bg-[color:var(--admin-surface)] border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-[color:var(--admin-accent)]">
                            <span class="text-[11px] text-[color:var(--admin-text)]">{{ __('admin.entity_form.manage_stock') }}</span>
                        </label>
                    </div>
                </div>
            </x-admin.form-card>

            <!-- Featured Image -->
            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                    &gt; Imagen principal
                </h4>
                <div class="space-y-2">
                    <input type="hidden" name="featured_image" :value="featuredImage">
                    <template x-if="featuredImage">
                        <div class="relative rounded-lg overflow-hidden border border-[color:var(--admin-border)] h-36 bg-[color:var(--admin-surface)]">
                            <img :src="featuredImage" class="w-full h-full object-cover">
                            <button type="button" @click="featuredImage = ''" class="absolute top-2 right-2 bg-[color:var(--admin-surface)]/80 hover:bg-red-600 text-[color:var(--admin-text)] rounded p-1 text-xs">&times;</button>
                        </div>
                    </template>
                    <button type="button" @click="openFeaturedPicker()" class="w-full py-2 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)] text-[color:var(--admin-text)] rounded-lg flex items-center justify-center gap-2">
                        <i class="fa-solid fa-image"></i>
                        <span x-text="featuredImage ? 'Cambiar imagen' : 'Seleccionar del gestor de archivos'"></span>
                    </button>
                    <button type="button" x-data="{ aiAvailable: typeof window.CmsAssistant !== 'undefined' }"
                            @cms-assistant-ready.window="aiAvailable = true" x-show="aiAvailable"
                            @click="window.CmsAssistant.open({ mode: 'image', featured: true })"
                            class="w-full py-2 rounded-lg border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] hover:text-[color:var(--admin-text)]">
                        <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('admin.ai.media_generate') }}
                    </button>
                </div>
            </x-admin.form-card>

            <!-- Multi-Market Pricing Overrides -->
            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)] flex items-center justify-between">
                    <span>&gt; Mercados y precios locales</span>
                    <span class="text-[10px] text-[color:var(--admin-muted)] font-normal">{{ __('admin.entity_form.multidivisa') }}</span>
                </h4>

                <x-admin.market-picker name="market_ids" :selected="$assignedMarketIds" :markets="$markets" group="product-form" />

                <!-- SEO & GEO Copilot Box (per language, live with the tab) -->
                <x-admin.seo-meta-box :allLanguages="$allLangs->pluck('code')->all()" schemaType="Product" />

                <div class="space-y-2 pt-2 border-t border-[color:var(--admin-border)]/60">
                    @foreach($markets as $m)
                        @php
                            $pivot = $product?->markets->where('id', $m->id)->first();
                            $customPrice = $pivot?->pivot->custom_price ?? '';
                        @endphp
                        <div class="flex items-center gap-2">
                            <span class="text-[color:var(--admin-muted)] text-[10px] w-24 shrink-0 font-mono">{{ strtoupper($m->code) }} · {{ $m->currency }}</span>
                            <input type="number" step="0.01" name="market_prices[{{ $m->id }}]" value="{{ old('market_prices.'.$m->id, $customPrice) }}" placeholder="{{ __('admin.entity_form.ph_inherit_price') }}" class="admin-control flex-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded px-2 py-1 text-[color:var(--admin-accent)] text-xs font-mono">
                        </div>
                    @endforeach
                </div>

                <x-admin.form-actions :submit="false" class="pt-3 border-t border-[color:var(--admin-border)]">
                    <x-admin.button variant="primary" type="submit" class="w-full py-2.5">
                        {{ $isEdit ? 'Guardar cambios' : 'Crear producto' }} &rarr;
                    </x-admin.button>
                </x-admin.form-actions>
            </x-admin.form-card>

            <!-- SEO & GEO Copilot Box -->

        </div>

    </div>
    @stack('admin_form_sidebar')
</form>
