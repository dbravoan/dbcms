@props([
    'gallery' => null,
    'markets' => [],
    'languages' => [],
])

@php
    $isEdit = !empty($gallery && $gallery->id);
    $actionUrl = $isEdit ? route('admin.galleries.update', $gallery) : route('admin.galleries.store');

    $allLangs = !empty($languages) && count($languages) > 0
        ? $languages
        : ($activeLanguages ?? collect());

    if ($allLangs->isEmpty()) {
        $allLangs = collect([(object) ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸']]);
    }

    // Idioma de contenido por defecto desde la BD (reactivo), nunca fijo.
    $defaultLocale = $defaultLocale ?? $allLangs->first()?->code ?? 'es';
    $translationsMap = [];
    foreach ($allLangs as $__lang) {
        $translationsMap[$__lang->code] = ['title' => '', 'slug' => '', 'description' => '', 'meta_title' => '', 'meta_description' => '', 'schema_type' => 'ImageGallery'];
    }
    if ($gallery) {
        foreach ($gallery->translations as $tr) {
            $translationsMap[$tr->locale] = [
                'title' => $tr->title,
                'slug' => $tr->slug,
                'description' => $tr->description ?? '',
                'meta_title' => $tr->meta_title ?? '',
                'meta_description' => $tr->meta_description ?? '',
                'schema_type' => $tr->schema_type ?? 'ImageGallery',
            ];
        }
    }
    foreach ($translationsMap as $code => $translation) {
        $submitted = old('translations.'.$code);
        if (is_array($submitted)) {
            $translationsMap[$code] = array_replace($translation, $submitted);
        }
    }

    $assignedMarketIds = old('market_ids', $gallery ? $gallery->markets->pluck('id')->all() : []);
    if (empty($assignedMarketIds) && !empty($markets)) {
        $assignedMarketIds = collect($markets)->pluck('id')->all();
    }


    $initialItems = [];
    if ($gallery && $gallery->items) {
        foreach ($gallery->items as $item) {
            $initialItems[] = [
                'id' => 'item_' . $item->id,
                'image_url' => $item->image_url,
                'title' => $item->title ?? '',
                'alt_text' => $item->alt_text ?? '',
                'caption' => $item->caption ?? '',
            ];
        }
    }
    $submittedItems = json_decode((string) old('items_json', ''), true);
    if (is_array($submittedItems) && array_is_list($submittedItems)) {
        $initialItems = $submittedItems;
    }
@endphp

@if(!empty($gallery?->getAttributes()['pending_revision_id']))
    <p class="mb-4 rounded border border-amber-500/40 p-3 text-sm text-amber-200">Editas un borrador pendiente. La galería pública conserva su contenido hasta aprobar esta revisión.</p>
@endif

<form method="POST" action="{{ $actionUrl }}" class="space-y-8 font-mono text-xs" x-on:locale-tab-change="switchLocale($event.detail.locale)"
      x-data="galleryEditor(@js($translationsMap), @js($initialItems), @js($allLangs->pluck('code')), @js($defaultLocale))">
    @csrf
    @if($isEdit)
        @method('PUT')
    @endif

    <input type="hidden" name="items_json" :value="JSON.stringify(items)">

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

        <!-- Left Column: Album Details & Image Gallery Manager (2 Cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Title, Slug & Language Tabs Card -->
            <x-admin.form-card class="space-y-4">
                <!-- Dynamic Language Switcher Bar -->
                <x-admin.locale-tabs :languages="$allLangs" :flags="$flags ?? []" :active="$defaultLocale" group="gallery-form" />

                <!-- Dynamic Language Inputs for Active Locale -->
                <div class="space-y-4">
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            Título del Álbum (<span x-text="activeLocale.toUpperCase()"></span>) *
                        </label>
                        <input type="text"
                               :name="'translations[' + activeLocale + '][title]'"
                               x-model="translations[activeLocale].title"
                               @input="if(!slugTouched[activeLocale]) generateSlug(activeLocale)"
                               required
                               placeholder="{{ __('admin.entity_form.ph_gallery_title', ['app' => config('cms.name')]) }}"
                               class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-4 py-2.5 text-[color:var(--admin-text)] font-bold text-sm">
                    </div>
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            Identificador del Álbum / Código de Bloque (<span x-text="activeLocale.toUpperCase()"></span>)
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="text"
                                   :name="'translations[' + activeLocale + '][slug]'"
                                   x-model="translations[activeLocale].slug"
                                   @input="slugTouched[activeLocale] = true"
                                   placeholder="{{ __('admin.entity_form.ph_gallery_slug') }}"
                                   class="admin-control flex-1 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-accent)] font-mono">
                            <button type="button" @click="generateSlug(activeLocale); slugTouched[activeLocale] = false" class="px-2.5 py-2 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[10px]">Sync</button>
                        </div>
                        <p class="text-[10px] text-[color:var(--admin-muted)] mt-1">{{ __('admin.entity_form.gallery_slug_hint') }}</p>
                    </div>
                    <div>
                        <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">
                            Descripción del Álbum (<span x-text="activeLocale.toUpperCase()"></span>)
                        </label>
                        <textarea :name="'translations[' + activeLocale + '][description]'"
                                  x-model="translations[activeLocale].description"
                                  rows="2"
                                  placeholder="{{ __('admin.entity_form.ph_gallery_desc') }}"
                                  class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-lg px-3 py-2 text-[color:var(--admin-text)]"></textarea>
                    </div>

                    <!-- Todos los idiomas viajan con el contrato de traducciones. -->
                    <template x-for="code in allLanguages" :key="'translation-' + code">
                        <div x-show="activeLocale === code">
                            <input type="hidden" :name="'translations[' + code + '][title]'" :value="translations[code]?.title || ''">
                            <input type="hidden" :name="'translations[' + code + '][slug]'" :value="translations[code]?.slug || ''">
                            <input type="hidden" :name="'translations[' + code + '][description]'" :value="translations[code]?.description || ''">
                        </div>
                    </template>
                </div>
            </x-admin.form-card>

            <!-- Photos & Media Items Grid Customizer -->
            <x-admin.form-card class="space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-[color:var(--admin-border)]">
                    <div>
                        <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                            <span class="text-[color:var(--admin-accent)]">&gt;_</span>
                            <span>{{ __('admin.entity_form.photos_items') }}</span>
                        </h3>
                        <p class="text-[11px] text-[color:var(--admin-muted)] mt-0.5">
                            Añade, reordena y edita las imágenes vinculadas a esta galería.
                        </p>
                    </div>

                    <x-admin.button variant="primary" type="button" @click="openFileManagerForNewItem()" class="text-xs py-1.5 px-3 flex items-center gap-1.5">
                        <i class="fa-solid fa-image"></i>
                        <span>{{ __('admin.entity_form.add_image') }}</span>
                    </x-admin.button>
                </div>

                <!-- Empty State -->
                <div x-show="items.length === 0" class="p-8 border border-dashed border-[color:var(--admin-border)] rounded-xl text-center text-[color:var(--admin-muted)] space-y-3 bg-[color:var(--admin-surface)]/50">
                    <span class="text-3xl block text-[color:var(--admin-muted)]"><i class="fa-solid fa-images"></i></span>
                    <p class="text-xs">&gt; {{ __('admin.galleries.empty_album') }}</p>
                </div>

                <!-- Items List -->
                <div class="space-y-3">
                    <template x-for="(item, idx) in items" :key="item.id || idx">
                        <div class="p-3 bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl flex items-center gap-4 group hover:border-[color:var(--admin-border)]">
                            <!-- Thumbnail -->
                            <div class="w-20 h-20 bg-[color:var(--admin-surface)] rounded-lg overflow-hidden shrink-0 border border-[color:var(--admin-border)]">
                                <img :src="item.image_url" :alt="item.title" class="w-full h-full object-cover">
                            </div>

                            <!-- Title & Caption -->
                            <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-2">
                                <div>
                                    <label class="admin-field-label text-[10px] text-[color:var(--admin-muted)] uppercase font-bold">{{ __('admin.entity_form.photo_title') }}</label>
                                    <input type="text" x-model="item.title" placeholder="{{ __('admin.entity_form.ph_title_descriptive') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded px-2.5 py-1.5 text-[color:var(--admin-text)]">
                                </div>
                                <div>
                                    <label class="admin-field-label text-[10px] text-[color:var(--admin-muted)] uppercase font-bold">{{ __('admin.entity_form.caption') }}</label>
                                    <input type="text" x-model="item.caption" placeholder="{{ __('admin.entity_form.ph_caption') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded px-2.5 py-1.5 text-[color:var(--admin-text)]">
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="flex items-center gap-1">
                                <button type="button" @click="moveUp(idx)" :disabled="idx === 0" class="p-1 text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] disabled:opacity-30">▲</button>
                                <button type="button" @click="moveDown(idx)" :disabled="idx === items.length - 1" class="p-1 text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)] disabled:opacity-30">▼</button>
                                <button type="button" @click="removeItem(idx)" class="p-1 text-red-400 hover:text-red-300 ml-1">&times;</button>
                            </div>
                        </div>
                    </template>
                </div>
            </x-admin.form-card>

        </div>

        <!-- Right Column: Publishing Controls & Markets (1 Col) -->
        <div class="space-y-6">

            <x-admin.form-card class="space-y-4">
                <h4 class="font-bold text-[color:var(--admin-text)] uppercase text-xs pb-2 border-b border-[color:var(--admin-border)]">
                    &gt; Ajustes del Álbum
                </h4>

                <div class="flex items-center justify-between">
                    <span class="text-[color:var(--admin-text)] font-bold">{{ __('admin.entity_form.album_active') }}</span>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1" class="sr-only peer" {{ old('is_active', $gallery?->is_active ?? false) ? 'checked' : '' }}>
                        <div class="w-9 h-5 bg-[color:var(--admin-surface-raised)] peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-[color:var(--admin-accent)]"></div>
                    </label>
                </div>

                <!-- Markets Multi-Selector -->
                <x-admin.market-picker name="market_ids" :selected="$assignedMarketIds" :markets="$markets" group="gallery-form" />

                <x-admin.form-actions :submit="false" class="pt-3 border-t border-[color:var(--admin-border)]">
                    <x-admin.button variant="primary" type="submit" class="w-full py-2.5">
                        {{ $isEdit ? 'Guardar Cambios' : 'Guardar Galería' }} &rarr;
                    </x-admin.button>
                </x-admin.form-actions>
            </x-admin.form-card>
            <!-- SEO & GEO Copilot Box (per language, live with the tab) -->
            <x-admin.seo-meta-box :allLanguages="$allLangs->pluck('code')->all()" schemaType="ImageGallery" />

        </div>

    </div>
    @stack('admin_form_sidebar')
</form>
