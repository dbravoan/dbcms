@props([
    'post' => null,
    'categories' => [],
    'markets' => [],
    'languages' => [],
])

@php
    $isEdit = !empty($post && $post->id);
    $actionUrl = $isEdit ? route('admin.blog.posts.update', $post) : route('admin.blog.posts.store');

    $allLangs = !empty($languages) && count($languages) > 0
        ? $languages
        : ($activeLanguages ?? collect());

    if ($allLangs->isEmpty()) {
        $allLangs = collect([
            (object)['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸'],
            (object)['code' => 'en', 'name' => 'English', 'flag' => '🇺🇸'],
        ]);
    }

    $defaultLocale = $defaultLocale ?? $allLangs->firstWhere('is_default', true)?->code ?? $allLangs->first()?->code ?? config('app.fallback_locale');
    $translationsMap = [];
    foreach ($allLangs as $lang) {
        $trans = $post ? $post->translations->where('locale', $lang->code)->first() : null;
        $translationsMap[$lang->code] = [
            'title' => old("translations.{$lang->code}.title", $trans?->title ?? ''),
            'slug' => old("translations.{$lang->code}.slug", $trans?->slug ?? ''),
            'summary' => old("translations.{$lang->code}.summary", $trans?->summary ?? ''),
            'content' => old("translations.{$lang->code}.content", $trans?->content ?? ''),
            'meta_title' => old("translations.{$lang->code}.meta_title", $trans?->meta_title ?? ''),
            'meta_description' => old("translations.{$lang->code}.meta_description", $trans?->meta_description ?? ''),
            'schema_type' => old("translations.{$lang->code}.schema_type", $trans?->schema_type ?? 'TechArticle'),
        ];
    }

    $assignedMarketIds = old('market_ids', $post ? $post->markets->pluck('id')->all() : []);
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
    translations: @js($translationsMap),
    prefilledFromDefault: {},
    allLanguages: @js($allLangs->pluck('code')),
    featuredImage: @js(old('featured_image', $post?->featured_image ?? '')),

    init() {
        for (const code of this.allLanguages) {
            if (!this.translations[code]) {
                this.translations[code] = {
                    title: '',
                    slug: '',
                    summary: '',
                    content: '',
                    meta_title: '',
                    meta_description: '',
                    schema_type: 'TechArticle'
                };
            }
        }
    },


    copySourceLocale: @js($defaultLocale),
    copySuccessMessage: '',
    slugTouched: {},

    hasData(code) {
        return !!(this.translations[code]?.title && this.translations[code].title.trim() !== '');
    },

    switchLocale(code) {
        if (!this.translations[code]) {
            this.translations[code] = {
                title: '',
                slug: '',
                summary: '',
                content: '',
                meta_title: '',
                meta_description: '',
                schema_type: 'TechArticle'
            };
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
        this.translations[this.activeLocale].summary = src.summary || '';
        this.translations[this.activeLocale].content = src.content || '';
        this.translations[this.activeLocale].meta_title = src.meta_title || src.title;
        this.translations[this.activeLocale].meta_description = src.meta_description || '';

        window.dispatchEvent(new CustomEvent('cms-copy-locale', {
            detail: { from: sourceCode, to: this.activeLocale }
        }));

        this.copySuccessMessage = 'Contenidos y bloques copiados exitosamente desde ' + sourceCode.toUpperCase() + '.';
        setTimeout(() => { this.copySuccessMessage = ''; }, 4000);
    },

    generateSlug(textOrCode) {
        const text = typeof textOrCode === 'string' && this.translations[textOrCode] ? (this.translations[textOrCode].title || '') : textOrCode;
        const s = (text || '').toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/(^-|-$)+/g, '');
        if (typeof textOrCode === 'string' && this.translations[textOrCode]) {
            this.translations[textOrCode].slug = s;
        }
        return s;
    },

    openFeaturedPicker() {
        window.addEventListener('file-selected', (e) => {
            if (e.detail?.target === 'featured-image-field') {
                this.featuredImage = e.detail.file.url;
            }
        }, { once: true });

        window.dispatchEvent(new CustomEvent('open-filemanager', {
            detail: { target: 'featured-image-field' }
        }));
    }
}">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column: Content, Languages & Block Manager (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Title & Summary Card -->
            <x-admin.form-card class="space-y-4">
                <!-- Dynamic Language Switcher Bar -->
                <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[color:var(--admin-border)] pb-3">
                    <x-admin.locale-tabs :languages="$allLangs" :flags="$flags ?? []" :active="$allLangs->first()->code ?? 'es'" group="post-form" />

                    <!-- Quick Copy from Language Dropdown -->
                    <div class="flex items-center gap-2">
                        <select x-model="copySourceLocale" class="admin-control bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] text-[color:var(--admin-text)] text-xs rounded px-2.5 py-1">
                            @foreach($allLangs as $lang)
                                <option value="{{ $lang->code }}" x-show="'{{ $lang->code }}' !== activeLocale">{{ __('admin.entity_form.copy_from') }} {{ $lang->name }} ({{ strtoupper($lang->code) }})</option>
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
                        <span>{{ __('admin.entity_form.info_prefix') }}<strong x-text="activeLocale.toUpperCase()"></strong>{{ __('admin.entity_form.info_suffix_blog') }}</span>
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

                <!-- Dynamic Language Inputs for Active Locale -->
                <div class="space-y-4">
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            {{ __('admin.entity_form.post_title') }} (<span x-text="activeLocale.toUpperCase()"></span>) *
                        </label>
                        <input type="text"
                               :name="'translations[' + activeLocale + '][title]'"
                               x-model="translations[activeLocale].title"
                               @input="if(!slugTouched[activeLocale]) translations[activeLocale].slug = generateSlug(translations[activeLocale].title)"
                               required
                               placeholder="{{ __('admin.entity_form.ph_post_title') }}"
                               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] font-bold text-sm">
                    </div>

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            {{ __('admin.entity_form.route_slug') }} (<span x-text="activeLocale.toUpperCase()"></span>) *
                        </label>
                        <div class="flex items-center gap-2">
                            <span class="text-[color:var(--admin-muted)] text-xs">/blog/</span>
                            <input type="text"
                                   :name="'translations[' + activeLocale + '][slug]'"
                                   x-model="translations[activeLocale].slug"
                                   @input="slugTouched[activeLocale] = true"
                                   placeholder="{{ __('admin.entity_form.ph_post_slug') }}"
                                   class="admin-control flex-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-mono text-xs">
                            <button type="button"
                                    @click="translations[activeLocale].slug = generateSlug(translations[activeLocale].title); slugTouched[activeLocale] = false"
                                    class="px-2.5 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[10px]">
                                Generar desde título
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            {{ __('admin.entity_form.summary') }} (<span x-text="activeLocale.toUpperCase()"></span>)
                        </label>
                        <textarea :name="'translations[' + activeLocale + '][summary]'"
                                  x-model="translations[activeLocale].summary"
                                  rows="2"
                                  placeholder="{{ __('admin.entity_form.ph_summary') }}"
                                  class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]"></textarea>
                    </div>

                    <!-- Todos los idiomas viajan con el contrato de traducciones. -->
                    <template x-for="code in allLanguages" :key="'translation-' + code">
                        <div x-show="activeLocale === code">
                            <input type="hidden" :name="'translations[' + code + '][title]'" :value="translations[code]?.title || ''" :disabled="!hasData(code)">
                            <input type="hidden" :name="'translations[' + code + '][slug]'" :value="translations[code]?.slug || ''" :disabled="!hasData(code)">
                            <input type="hidden" :name="'translations[' + code + '][summary]'" :value="translations[code]?.summary || ''" :disabled="!hasData(code)">
                            <input type="hidden" :name="'translations[' + code + '][content]'" :value="translations[code]?.content || ''" :disabled="!hasData(code)">
                        </div>
                    </template>
                </div>

            </x-admin.form-card>

            <!-- Dynamic Block Manager Component (Identical to Pages) -->
            <x-admin.form-card class="">
                <x-admin.block-manager :sections="$sectionsData" name="sections_json" :languages="$allLangs" :target="request('section')" />
            </x-admin.form-card>

        </div>

        <!-- Right Column: Taxonomy, Media & Publishing Controls (1 Col) -->
        <div class="space-y-6">

            <!-- Publishing Action Card -->
            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                    &gt; Estado de publicación
                </h4>

                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{{ __('admin.entity_form.publish_post') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_published" value="1" class="sr-only peer" {{ old('is_published', $post?->is_published ?? true) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)]">{{ __('admin.entity_form.featured_post') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="is_featured" value="1" class="sr-only peer" {{ old('is_featured', $post?->is_featured ?? false) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>

                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)]">{{ __('admin.entity_form.allow_comments') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="allow_comments" value="1" class="sr-only peer" {{ old('allow_comments', $post?->allow_comments ?? true) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>

                <div>
                    <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.entity_form.category') }}</label>
                    <x-admin.search-select
                        source="blog_categories"
                        name="category_id"
                        :value="old('category_id', $post?->category_id)"
                        placeholder="{{ __('admin.entity_form.ph_category') }}"
                    />
                </div>

                <!-- Featured Image Picker -->
                <div>
                    <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1.5">{{ __('admin.entity_form.cover_image') }}</label>
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
                            <span x-text="featuredImage ? @js(__('admin.entity_form.change_image')) : @js(__('admin.entity_form.select_from_manager'))"></span>
                        </button>
                        <button type="button" x-data="{ aiAvailable: typeof window.CmsAssistant !== 'undefined' }"
                                @cms-assistant-ready.window="aiAvailable = true" x-show="aiAvailable"
                                @click="window.CmsAssistant.open({ mode: 'image', featured: true })"
                                class="w-full py-2 rounded-lg border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] hover:text-[color:var(--admin-text)]">
                            <i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('admin.ai.media_generate') }}
                        </button>
                    </div>
                </div>

                <!-- Markets Multi-Selector -->
                <x-admin.market-picker name="market_ids" :selected="$assignedMarketIds" :markets="$markets" group="post-form" />

                <x-admin.form-actions :submit="false" class="pt-3 border-t border-[color:var(--admin-border)]">
                    <x-admin.button variant="primary" type="submit" class="w-full py-2.5">
                        {{ $isEdit ? __('admin.actions.save') : __('admin.entity_form.publish_post') }} &rarr;
                    </x-admin.button>
                </x-admin.form-actions>
            </x-admin.form-card>

            <!-- SEO & GEO Copilot Box (per language, live with the tab) -->
            <x-admin.seo-meta-box :allLanguages="$allLangs->pluck('code')->all()" schemaEntity="post" :schemaType="$translationsMap[$defaultLocale ?? 'es']['schema_type'] ?? 'TechArticle'" />


            <!-- Modular Sidebar Extension Stack -->
            @stack('admin_form_sidebar')
        </div>

    </div>
</form>
