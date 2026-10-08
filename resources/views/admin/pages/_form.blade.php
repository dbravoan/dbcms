@props([
    'page' => null,
    'markets' => [],
    'languages' => [],
])

@php
    $isEdit = !empty($page && $page->id);
    $actionUrl = $isEdit ? route('admin.pages.update', $page) : route('admin.pages.store');

    $allLangs = !empty($languages) && count($languages) > 0 ? $languages : ($activeLanguages ?? collect());
    $translationsData = [];
    foreach ($allLangs as $lang) {
        $trans = $page ? $page->translations->where('locale', $lang->code)->first() : null;
        $translationsData[$lang->code] = [
            'title' => old("translations.{$lang->code}.title", old("title_{$lang->code}", $trans?->title ?? '')),
            'slug' => old("translations.{$lang->code}.slug", old("slug_{$lang->code}", $trans?->slug ?? '')),
            'meta_title' => old("translations.{$lang->code}.meta_title", old("meta_title_{$lang->code}", $trans?->meta_title ?? '')),
            'meta_description' => old("translations.{$lang->code}.meta_description", old("meta_description_{$lang->code}", $trans?->meta_description ?? '')),
            'schema_type' => old("translations.{$lang->code}.schema_type", $trans?->schema_type ?? 'WebPage'),
        ];
    }

    $assignedMarketIds = old('market_ids', $page ? $page->markets->pluck('id')->all() : []);
    if (empty($assignedMarketIds) && !empty($markets)) {
        $assignedMarketIds = collect($markets)->pluck('id')->all();
    }


    // Extract sections per locale
    $sectionsByLocale = [];
    foreach ($allLangs as $lang) {
        $sectionsByLocale[$lang->code] = [];
    }

    if ($page && $page->sections && $page->sections->isNotEmpty()) {
        foreach ($page->sections as $sec) {
            $styling = is_array($sec->styling_json) ? $sec->styling_json : [];
            foreach ($allLangs as $lang) {
                $trans = $sec->translations->where('locale', $lang->code)->first();
                $item = $styling;
                $item['id'] = 'sec_' . $sec->id;
                $item['type'] = $sec->type;
                if ($trans && isset($trans->content_json) && is_array($trans->content_json)) {
                    $item = array_merge($item, $trans->content_json);
                }
                $sectionsByLocale[$lang->code][] = $item;
            }
        }
    } else {
        $sectionsByLocale['es'][] = [
            'id' => 'sec_initial_1',
            'type' => 'hero',
            'title' => !empty($translationsData['es']['title']) ? $translationsData['es']['title'] : 'Infraestructura web de alto rendimiento',
            'content' => 'Plataforma empresarial con distribución global, baja latencia y arquitectura modular.',
            'animation' => 'fadeInUp',
            'animation_delay' => '0.2s',
            'animation_duration' => '1s',
            'container_style' => 'cyber-card',
            'content_width' => 'centered',
            'bg_type' => 'none',
            'border_top' => ['enabled' => false, 'color' => '#00FF99', 'width' => '2px', 'style' => 'solid'],
            'border_bottom' => ['enabled' => false, 'color' => '#00FF99', 'width' => '2px', 'style' => 'solid'],
            'divider_top' => ['type' => 'none', 'color' => '#0E0E0E', 'height' => 60, 'flip' => false],
            'divider_bottom' => ['type' => 'none', 'color' => '#0E0E0E', 'height' => 60, 'flip' => false],
            'scroll_magic' => ['behavior' => 'fadeInUp', 'delay' => '0.2s', 'duration' => '1s', 'scrub' => 'none'],
            'is_collapsed' => false,
            'items' => [],
            'cta_text' => 'Explorar catálogo',
            'cta_url' => '/shop',
        ];
    }
    $sectionsData = $sectionsByLocale;
@endphp

<form method="POST" action="{{ $actionUrl }}" class="space-y-8 font-mono text-xs"
      data-cms-translation-editor
      x-on:cms-translation-apply.window="if ($event.detail.form === $el && allLanguages.includes($event.detail.targetLocale)) { translations[$event.detail.targetLocale] = {...translations[$event.detail.targetLocale], ...$event.detail.values}; switchLocale($event.detail.targetLocale); }"
      x-on:locale-tab-change="switchLocale($event.detail.locale)"
      x-data="{
    activeLocale: 'es',
    defaultLocale: 'es',
    translations: @js($translationsData),
    allLanguages: @js($allLangs->pluck('code')),
    copySourceLocale: 'es',
    copySuccessMessage: '',
    slugTouched: {},

    init() {
        for (const code of this.allLanguages) {
            if (!this.translations[code]) {
                this.translations[code] = {
                    title: '',
                    slug: '',
                    meta_title: '',
                    meta_description: '',
                    schema_type: 'WebPage'
                };
            }
        }
    },


    hasData(code) {
        return !!(this.translations[code]?.title && this.translations[code].title.trim() !== '');
    },

    generateSlug(text) {
        if (!text) return '';
        return text.toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)+/g, '');
    },

    switchLocale(code) {
        if (!this.translations[code]) {
            this.translations[code] = { title: '', slug: '', meta_title: '', meta_description: '', schema_type: 'WebPage' };
        }
        this.activeLocale = code;
        window.dispatchEvent(new CustomEvent('cms-locale-switched', { detail: { locale: code } }));
    },

    copyFrom(sourceCode) {
        if (!this.translations[sourceCode] || !this.translations[sourceCode].title) {
            alert('El idioma de origen no contiene datos para copiar.');
            return;
        }

        const src = this.translations[sourceCode];
        this.translations[this.activeLocale].title = src.title;
        this.translations[this.activeLocale].slug = this.generateSlug(src.title);
        this.translations[this.activeLocale].meta_title = src.meta_title || src.title;
        this.translations[this.activeLocale].meta_description = src.meta_description || '';
        this.translations[this.activeLocale].schema_type = src.schema_type || 'WebPage';

        window.dispatchEvent(new CustomEvent('cms-copy-locale', {
            detail: { from: sourceCode, to: this.activeLocale }
        }));

        this.copySuccessMessage = 'Contenidos y bloques copiados correctamente desde ' + sourceCode.toUpperCase() + '.';
        setTimeout(() => { this.copySuccessMessage = ''; }, 4000);
    }
}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column: Content, Languages & Block Manager (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Title & Slug Card -->
            <div class="cyber-card p-6 space-y-4">
                <!-- Language Selector Tabs & Copy Action -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-800 pb-3">
                    <x-admin.locale-tabs :languages="$allLangs" active="{{ $allLangs->first()->code ?? 'es' }}" group="page-form" />

                    <!-- Quick Copy from Language Dropdown -->
                    <div class="flex items-center gap-2">
                        <select x-model="copySourceLocale" class="bg-[#181818] border border-gray-700 text-gray-300 text-xs rounded px-2.5 py-1">
                            @foreach($allLangs as $lang)
                                <option value="{{ $lang->code }}" x-show="'{{ $lang->code }}' !== activeLocale">{{ __('admin.entity_form.copy_from') }} {{ $lang->name }} ({{ strtoupper($lang->code) }})</option>
                            @endforeach
                        </select>
                        <button type="button" @click="copyFrom(copySourceLocale)" class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-white rounded text-xs font-bold border border-gray-700">
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
                    <button type="button" @click="copyFrom(copySourceLocale)" class="btn-neon text-xs py-1 px-3">
                        Copiar contenidos y bloques &rarr;
                    </button>
                </div>

                <!-- Success Toast -->
                <div x-show="copySuccessMessage" x-cloak class="p-2.5 rounded-lg bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] text-xs flex items-center justify-between">
                    <span class="inline-flex items-center gap-1.5">
                        <i class="fa-solid fa-wand-magic-sparkles"></i>
                        <span x-text="copySuccessMessage"></span>
                    </span>
                    <button type="button" @click="copySuccessMessage = ''" class="text-gray-400 hover:text-white">&times;</button>
                </div>

                <!-- Dynamic Language Inputs -->
                @foreach($allLangs as $lang)
                    <div x-show="activeLocale === '{{ $lang->code }}'" class="space-y-4">
                        <div>
                            <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">
                                {{ __('admin.entity_form.page_title') }} ({{ strtoupper($lang->code) }}) @if($lang->code === 'es')*@endif
                            </label>
                            <input type="text"
                                   name="translations[{{ $lang->code }}][title]"
                                   x-model="translations['{{ $lang->code }}'].title"
                                   @input="if(!slugTouched['{{ $lang->code }}']) translations['{{ $lang->code }}'].slug = generateSlug(translations['{{ $lang->code }}'].title)"
                                   @if($lang->code === 'es') required @endif
                                   placeholder="{{ __('admin.entity_form.ph_page_title') }}"
                                   class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-4 py-2.5 text-white font-bold text-sm">
                            <!-- Hidden legacy inputs for test parity -->
                            <input type="hidden" name="title_{{ $lang->code }}" :value="translations['{{ $lang->code }}']?.title || ''">
                        </div>
                        <div>
                            <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">
                                {{ __('admin.entity_form.route_slug') }} ({{ strtoupper($lang->code) }})
                            </label>
                            <div class="flex items-center gap-2">
                                <span class="text-gray-500 text-xs">/</span>
                                <input type="text"
                                       name="translations[{{ $lang->code }}][slug]"
                                       x-model="translations['{{ $lang->code }}'].slug"
                                       @input="slugTouched['{{ $lang->code }}'] = true"
                                       placeholder="{{ __('admin.entity_form.ph_slug') }}"
                                       class="flex-1 bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-[#00FF99] font-mono">
                                <input type="hidden" name="slug_{{ $lang->code }}" :value="translations['{{ $lang->code }}']?.slug || ''">
                                <button type="button"
                                        @click="translations['{{ $lang->code }}'].slug = generateSlug(translations['{{ $lang->code }}'].title); slugTouched['{{ $lang->code }}'] = false"
                                        class="px-2.5 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px]">
                                    Generar desde título
                                </button>
                            </div>
                        </div>

                    </div>
                @endforeach
            </div>

            <!-- Dynamic Block Manager Component -->
            <div class="cyber-card p-6">
                <x-admin.block-manager :sections="$sectionsData" name="sections_json" :languages="$allLangs" :target="request('section')" />
            </div>

        </div>

        <!-- Right Column: Publishing Controls, Markets & SEO Copilot (1 Col) -->
        <div class="space-y-6">

            <!-- Publishing & Visibility Card -->
            <div class="cyber-card p-5 space-y-4">
                <h4 class="font-bold text-white uppercase text-xs pb-2 border-b border-gray-800">
                    &gt; {{ __('admin.entity_form.publication_visibility') }}
                </h4>

                <div class="flex items-center justify-between">
                    <span class="text-gray-300 font-bold">{{ __('admin.entity_form.publish_state') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_published" value="1" class="sr-only peer" {{ old('is_published', $page?->is_published ?? true) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-gray-700 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[#00FF99]"></div>
                    </label>
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.render_template') }}</label>
                    <select name="template" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                        <option value="default" {{ old('template', $page?->template) === 'default' ? 'selected' : '' }}>{{ __('admin.entity_form.template_default') }}</option>
                        <option value="landing" {{ old('template', $page?->template) === 'landing' ? 'selected' : '' }}>{{ __('admin.entity_form.template_landing') }}</option>
                        <option value="full-width" {{ old('template', $page?->template) === 'full-width' ? 'selected' : '' }}>{{ __('admin.entity_form.template_full') }}</option>
                    </select>
                </div>

                <!-- Market Association Badges -->
                <x-admin.market-picker name="market_ids" :selected="$assignedMarketIds" :markets="$markets" group="page-form" />

                <div class="pt-3 border-t border-gray-800">
                    <button type="submit" class="btn-neon w-full py-2.5">
                        {{ $isEdit ? __('admin.actions.save') : __('admin.entity_form.save_page') }} &rarr;
                    </button>
                </div>
            </div>

            <!-- Real-time SEO & GEO Copilot Box (per language, live with the tab) -->
            <x-admin.seo-meta-box :allLanguages="$allLangs->pluck('code')->all()" />


            <!-- Modular Sidebar Extension Stack -->
            @stack('admin_form_sidebar')
        </div>

    </div>
</form>
