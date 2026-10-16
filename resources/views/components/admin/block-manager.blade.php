@props([
    'sections' => [],
    'name' => 'sections_json',
    'languages' => [],
    'target' => null,
])

@php
    // Expresiones Alpine para el estado activo de los botones de layout: se
    // construyen como texto en PHP porque los atributos de un componente <x-…>
    // se copian literalmente (no compilan directivas).
    $layoutActiveExpr = static fn (string $layout): string => "activeLayout(block) === '".$layout."'";

    $rawSections = !empty($sections) ? (is_string($sections) ? json_decode($sections, true) : $sections) : [];
    if (!is_array($rawSections)) {
        $rawSections = [];
    }

    $allActiveLanguages = !empty($languages) && count($languages) > 0
        ? $languages
        : ($activeLanguages ?? collect());

    if ($allActiveLanguages->isEmpty()) {
        // Sin idiomas en la BD se usa el idioma por defecto real (reactivo),
        // nunca una lista fija que no coincide con el backoffice.
        $allActiveLanguages = collect([$defaultLanguage ?? (object) ['code' => 'es', 'name' => 'Español', 'flag' => '🇪🇸']]);
    }

    $languagesJs = collect($allActiveLanguages)->map(function ($lang) {
        $code = is_object($lang) ? $lang->code : ($lang['code'] ?? 'es');
        $name = is_object($lang) ? $lang->name : ($lang['name'] ?? strtoupper((string) $code));
        // Prefer the flag assigned in the backoffice (Language::flag accessor).
        $assigned = is_object($lang) ? ($lang->flag ?? null) : ($lang['flag'] ?? null);
        $flag = ! empty($assigned) ? (string) $assigned : match($code) {
            'es' => '🇪🇸',
            'en' => '🇺🇸',
            'ca' => '🏴󠁥󠁳󠁣󠁴󠁿',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            default => '🌐'
        };
        return [
            'code' => $code,
            'name' => $name,
            'flag' => $flag,
        ];
    })->values()->all();

    $editorDefaultLocale = $allActiveLanguages->firstWhere('is_default', true)?->code ?? $allActiveLanguages->first()?->code ?? config('app.fallback_locale');

    // Determine if $rawSections is keyed by locale
    $isLocaleKeyed = false;
    foreach (array_keys($rawSections) as $k) {
        if (is_string($k) && !is_numeric($k)) {
            $isLocaleKeyed = true;
            break;
        }
    }

    $sectionsByLocale = [];
    foreach ($allActiveLanguages as $lang) {
        $code = is_object($lang) ? $lang->code : ($lang['code'] ?? 'es');
        $sectionsByLocale[$code] = [];
    }

    if ($isLocaleKeyed) {
        foreach ($sectionsByLocale as $code => $val) {
            $sectionsByLocale[$code] = isset($rawSections[$code]) && is_array($rawSections[$code])
                ? $rawSections[$code]
                : [];
        }
    } else {
        $sectionsByLocale[$editorDefaultLocale] = $rawSections;
    }

    // Normalize each block in each locale
    foreach ($sectionsByLocale as $code => &$locBlocks) {
        foreach ($locBlocks as &$b) {
            if (!isset($activeBlockTypes[(string) ($b['type'] ?? 'text')])) { continue; }
            $b['animation'] = $b['animation'] ?? 'fadeInUp';
            $b['animation_delay'] = $b['animation_delay'] ?? '0.2s';
            $b['animation_duration'] = $b['animation_duration'] ?? '1s';
            $b['container_style'] = $b['container_style'] ?? 'cyber-card';
            $b['content_width'] = $b['content_width'] ?? 'centered';
            $b['bg_type'] = $b['bg_type'] ?? 'none';
            $b['bg_color'] = $b['bg_color'] ?? '';
            $b['bg_image_url'] = $b['bg_image_url'] ?? '';
            $b['bg_image_overlay'] = $b['bg_image_overlay'] ?? 'rgba(0,0,0,0.5)';
            $b['bg_video_url'] = $b['bg_video_url'] ?? '';
            $b['bg_video_poster'] = $b['bg_video_poster'] ?? '';
            $b['bg_video_overlay'] = $b['bg_video_overlay'] ?? 'rgba(0,0,0,0.5)';
            $b['border_top'] = $b['border_top'] ?? ['enabled' => false, 'color' => '#00FF99', 'width' => '2px', 'style' => 'solid'];
            $b['border_bottom'] = $b['border_bottom'] ?? ['enabled' => false, 'color' => '#00FF99', 'width' => '2px', 'style' => 'solid'];
            $b['divider_top'] = $b['divider_top'] ?? ['type' => 'none', 'color' => '#0E0E0E', 'height' => 60, 'flip' => false];
            $b['divider_bottom'] = $b['divider_bottom'] ?? ['type' => 'none', 'color' => '#0E0E0E', 'height' => 60, 'flip' => false];
            $b['columns_layout'] = $b['columns_layout'] ?? '1';
            // Contrato canónico: una sola clave para las columnas.
            $b['columns'] = (isset($b['columns']) && is_array($b['columns'])) ? $b['columns'] : [];
            $b['scroll_magic'] = $b['scroll_magic'] ?? ['behavior' => $b['animation'], 'delay' => $b['animation_delay'], 'duration' => $b['animation_duration'], 'scrub' => 'none'];
            $b['is_collapsed'] = false;
        }
    }
    unset($locBlocks, $b);
@endphp



<div x-data="blockManager(@js($sectionsByLocale), @js($editorDefaultLocale), @js($availableTypesJs), @js($languagesJs), @json($target ? (int) $target : null))"
     @keydown.escape.window="if (isFullscreen) toggleFullscreen()"
     @keydown.window="if ((event.ctrlKey || event.metaKey) && event.shiftKey && (event.key === 'F' || event.key === 'f')) { event.preventDefault(); toggleFullscreen(); }"
     :class="isFullscreen ? 'fixed inset-0 z-[100] bg-[#0A0A0A] w-screen h-screen flex flex-col overflow-hidden text-xs' : 'space-y-6 text-xs'"
     class="font-mono transition-all duration-200">

    <!-- Hidden Input containing JSON Payload -->
    <input type="hidden" name="{{ $name }}" :value="getCleanSerializedSections()">

    <!-- FULLSCREEN STICKY TOP BAR (Visible only in Fullscreen Mode) -->
    <div x-show="isFullscreen"
         class="shrink-0 bg-[#121212]/95 backdrop-blur-md border-b border-[#00FF99]/30 px-4 sm:px-6 py-3 flex flex-wrap items-center justify-between gap-3 z-30 shadow-2xl">
        <!-- Brand & Language Switcher in Fullscreen -->
        <div class="flex items-center gap-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-bold text-white tracking-tight">{{ config('cms.name', 'CMS') }}</span>
                <span class="px-2 py-0.5 rounded bg-[#00FF99]/10 border border-[#00FF99]/40 text-[#00FF99] text-[10px] font-bold uppercase tracking-wider flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00FF99] animate-pulse"></span>
                    <span>{{ __('admin.block_manager.fullscreen_canvas') }}</span>
                </span>
            </div>

            <!-- Language Switcher Pills -->
            <div class="flex items-center gap-1 bg-black/60 p-1 rounded-lg border border-gray-800">
                <template x-for="lang in languages" :key="lang.code">
                    <button type="button"
                            x-show="isLanguageActive(lang.code)"
                            @click="switchLocale(lang.code)"
                            :class="activeLocale === lang.code ? 'bg-[#00FF99] text-black font-bold shadow-[0_0_10px_rgba(0,255,153,0.3)]' : 'text-gray-400 hover:text-white bg-[#181818] border border-gray-800'"
                            class="px-2.5 py-1 rounded text-xs transition-all flex items-center gap-1.5">
                        <span x-text="lang.flag"></span>
                        <span x-text="lang.code.toUpperCase()"></span>
                        <span x-show="sectionsByLocale[lang.code] && sectionsByLocale[lang.code].length > 0"
                              :class="activeLocale === lang.code ? 'bg-black' : 'bg-[#00FF99]'"
                              class="w-1.5 h-1.5 rounded-full" title="{{ __('admin.block_manager.has_sections') }}"></span>
                    </button>
                </template>
            </div>
        </div>

        <!-- Center: Canvas Width Toggle & Block Stats -->
        <div class="hidden md:flex items-center gap-3">
            <div class="flex items-center gap-1 bg-black/60 p-1 rounded-lg border border-gray-800">
                <span class="text-[10px] text-gray-500 uppercase font-bold px-1.5">{{ __('admin.block_manager.canvas') }}</span>
                <button type="button" @click="canvasWidth = 'container'"
                        :class="canvasWidth === 'container' ? 'bg-[#1F1F1F] text-[#00FF99] border border-[#00FF99]/40 font-bold' : 'text-gray-400 hover:text-white'"
                        class="px-2 py-0.5 rounded text-[11px] transition-all">
                    Centrado (1280px)
                </button>
                <button type="button" @click="canvasWidth = 'full'"
                        :class="canvasWidth === 'full' ? 'bg-[#1F1F1F] text-[#00FF99] border border-[#00FF99]/40 font-bold' : 'text-gray-400 hover:text-white'"
                        class="px-2 py-0.5 rounded text-[11px] transition-all">
                    100% Panorámico
                </button>
            </div>
            <span class="text-gray-500 text-xs font-mono" x-text="blocks.length + ' secciones en ' + activeLocale.toUpperCase()"></span>
        </div>

        <!-- Right: Actions & Exit Fullscreen -->
        <div class="flex items-center gap-2">
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>

            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <!-- Add Section Button -->
            <button type="button"
                    @click="openAddModalAt(null)"
                    class="btn-neon text-xs py-1.5 px-3 flex items-center gap-1.5">
                <span>+</span>
                <span>{{ __('admin.block_manager.add_section') }}</span>
            </button>

            <!-- Direct Save Button from Fullscreen -->
            <button type="button"
                    @click="saveForm()"
                    class="px-3 py-1.5 bg-[#00FF99] hover:bg-[#00FF99]/90 text-black font-bold rounded text-xs flex items-center gap-1.5 shadow-[0_0_15px_rgba(0,255,153,0.3)] transition-all"
                    title="{{ __('admin.block_manager.save_tooltip') }}">
                <i class="fa-solid fa-floppy-disk"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.save') }}</span>
            </button>

            <!-- Exit Fullscreen Button -->
            <button type="button"
                    @click="toggleFullscreen()"
                    class="px-3 py-1.5 rounded bg-red-950/40 hover:bg-red-900/60 border border-red-500/40 text-red-300 hover:text-white font-bold text-xs flex items-center gap-1.5 transition-colors"
                    title="{{ __('admin.block_manager.exit_fullscreen_tooltip') }}">
                <i class="fa-solid fa-xmark"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.exit_fullscreen') }}</span>
            </button>
        </div>
    </div>

    <!-- STANDARD TOP ACTION BAR (Visible when NOT in Fullscreen) -->
    <div x-show="!isFullscreen" class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-gray-800">
        <div>
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <span class="text-[#00FF99]">&gt;_</span>
                <span>{{ __('admin.block_manager.title') }}</span>
                <span class="px-2 py-0.5 rounded bg-black/60 border border-[#00FF99]/40 text-[#00FF99] text-[10px] uppercase font-bold" x-text="@js(__('admin.block_manager.language')) + ' ' + activeLocale.toUpperCase()"></span>
            </h3>
            <p class="text-gray-400 text-[11px] mt-0.5">
                Organiza los bloques de contenido y widgets dinámicos para este idioma. Puedes personalizar fondos, separadores y animaciones.
            </p>
        </div>

        <div class="flex items-center gap-2">
            <!-- Extension point: external modules may inject toolbar actions here -->
            @stack('block_manager_actions')

            <!-- Fullscreen Trigger Button -->
            <button type="button"
                    @click="toggleFullscreen()"
                    class="px-2.5 py-1 bg-[#1A1A1A] hover:bg-[#00FF99]/10 text-gray-300 hover:text-[#00FF99] rounded border border-gray-700 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 transition-colors"
                    title="{{ __('admin.block_manager.fullscreen_tooltip') }}">
                <i class="fa-solid fa-expand"></i>
                <span class="hidden sm:inline">{{ __('admin.block_manager.fullscreen') }}</span>
            </button>

            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(true)" icon="fa-solid fa-angles-up" title="{{ __('admin.block_manager.collapse_all_tooltip') }}">
                {{ __('admin.block_manager.collapse_all') }}
            </x-admin.button>
            <x-admin.button variant="ghost" size="sm" @click="toggleCollapseAll(false)" icon="fa-solid fa-angles-down" title="{{ __('admin.block_manager.expand_all_tooltip') }}">
                {{ __('admin.block_manager.expand_all') }}
            </x-admin.button>
        </div>
    </div>

    <!-- CANVAS BODY WRAPPER (Scrollable in Fullscreen, Normal in Embedded) -->
    <div :class="isFullscreen ? 'flex-1 overflow-y-auto px-4 sm:px-8 py-6 sidebar-scroll' : ''">
        <div :class="isFullscreen ? (canvasWidth === 'container' ? 'max-w-7xl mx-auto w-full space-y-6' : 'w-full space-y-6') : 'space-y-6'">

            <!-- Empty State for Active Locale (Centered in Canvas) -->
            <div x-show="blocks.length === 0" class="my-6 p-10 border border-dashed border-gray-800 hover:border-gray-700 rounded-2xl text-center space-y-4 bg-[#121212]/50 max-w-xl mx-auto transition-all">
                <div class="w-14 h-14 rounded-2xl bg-[#1A1A1A] border border-[#00FF99]/30 flex items-center justify-center text-2xl text-[#00FF99] mx-auto shadow-[0_0_20px_rgba(0,255,153,0.15)]">
                    <i class="fa-solid fa-cubes"></i>
                </div>
                <div>
                    <h4 class="text-white font-bold text-sm">{{ __('admin.block_manager.canvas_empty_title') }} <span x-text="activeLocale.toUpperCase()" class="text-[#00FF99]"></span></h4>
                    <p class="text-xs text-gray-400 mt-1">{{ __('admin.block_manager.canvas_empty_desc') }}</p>
                </div>
                <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                    <button type="button" @click="openAddModalAt(0)" class="btn-neon text-xs py-2 px-4 flex items-center gap-2 shadow-[0_0_15px_rgba(0,255,153,0.3)]">
                        <i class="fa-solid fa-plus text-[11px]"></i>
                        <span>{{ __('admin.block_manager.add_first') }}</span>
                    </button>
                    <div x-show="Object.keys(sectionsByLocale).some(k => k !== activeLocale && sectionsByLocale[k] && sectionsByLocale[k].length > 0)" class="flex items-center gap-2 bg-[#1A1A1A] p-1.5 rounded-lg border border-gray-800">
                        <select x-model="copySourceLocale" class="bg-[#121212] border border-gray-700 text-gray-300 text-xs rounded px-2 py-1">
                            <template x-for="(locBlocks, locCode) in sectionsByLocale" :key="locCode">
                                <option :value="locCode" x-show="locCode !== activeLocale && locBlocks.length > 0" x-text="@js(__('admin.block_manager.copy_from')).replace(':locale', locCode.toUpperCase()).replace(':count', locBlocks.length)"></option>
                            </template>
                        </select>
                        <button type="button" @click="copyBlocksFrom(copySourceLocale)" class="px-3 py-1 bg-blue-600 hover:bg-blue-500 text-white rounded text-xs font-bold">
                            Copiar bloques &rarr;
                        </button>
                    </div>
                </div>
            </div>

    <!-- Blocks List -->
    <div class="space-y-4 pb-6">
        <template x-for="(block, index) in blocks" :key="block.id || index">
            <div :data-block-index="index">
                <!-- Top Insertion Zone (visible on hover before the first block) -->
                <div x-show="index === 0" class="group/insert relative z-20 py-2 -my-1 transition-all">
                    <div class="h-[2px] w-full bg-gradient-to-r from-transparent via-[#00FF99]/40 to-transparent opacity-0 group-hover/insert:opacity-100 transition-opacity"></div>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none group-hover/insert:pointer-events-auto">
                        <button type="button"
                                @click="openAddModalAt(0)"
                                class="opacity-0 group-hover/insert:opacity-100 scale-95 group-hover/insert:scale-100 transition-all duration-200 px-3 py-0.5 rounded-full bg-[#161616] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/50 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 shadow-[0_0_15px_rgba(0,255,153,0.3)]">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span>{{ __('admin.block_manager.add_section_start') }}</span>
                        </button>
                    </div>
                </div>

                <div class="bg-[#141414] border border-gray-800 hover:border-gray-700 rounded-2xl overflow-hidden transition-all shadow-xl">

                <!-- Block Header Toolbar -->
                <div class="bg-[#181818] px-4 py-3 border-b border-gray-800 flex flex-wrap items-center justify-between gap-2">
                    <div class="flex items-center gap-3 cursor-pointer select-none" @click="block.is_collapsed = !block.is_collapsed">
                        <span class="text-gray-500 font-bold font-mono" x-text="'#' + (index + 1)"></span>
                        <i x-show="isFontAwesomeIcon(typeDef(block.type).icon)" :class="typeDef(block.type).icon" class="text-base text-[#00FF99]" x-cloak></i>
                        <span x-show="!isFontAwesomeIcon(typeDef(block.type).icon)" class="text-base text-[#00FF99] font-bold" x-text="typeGlyph(block.type)" x-cloak></span>
                        <div>
                            <span class="font-bold text-white uppercase text-xs" x-text="availableTypes.find(t => t.type === block.type)?.name || block.type"></span>
                            <span x-show="block.title" class="text-gray-400 text-[11px] ml-2" x-text="'- ' + block.title"></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-2" x-show="isBlockAvailable(block)">
                        <!-- Column Layout Selector -->
                        <div class="flex items-center gap-1 bg-[#101010] p-1 rounded-lg border border-gray-800">
                            <span class="text-[10px] text-gray-400 font-bold px-1 uppercase font-mono">{{ __('admin.block_manager.columns') }}</span>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '1')" :alpine-active="$layoutActiveExpr('1')" title="1 Columna (100%)">1</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '2')" :alpine-active="$layoutActiveExpr('2')" title="2 Columnas (50/50)">2</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '1-2')" :alpine-active="$layoutActiveExpr('1-2')" title="2 Columnas (33/66)">1:2</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '2-1')" :alpine-active="$layoutActiveExpr('2-1')" title="2 Columnas (66/33)">2:1</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '3')" :alpine-active="$layoutActiveExpr('3')" title="3 Columnas">3</x-admin.button>
                            <x-admin.button variant="ghost" size="sm" @click="setColumnsLayout(index, '4')" :alpine-active="$layoutActiveExpr('4')" title="4 Columnas">4</x-admin.button>
                        </div>

                        <!-- Botón Emergente de Apariencia -->
                        <button type="button" @click="openAppearanceModal(index)" class="px-2 py-1 rounded bg-[#101010] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] text-xs font-bold flex items-center gap-1.5 transition-colors" title="{{ __('admin.block_manager.appearance_tooltip') }}">
                            <i class="fa-solid fa-palette"></i>
                            <span class="hidden sm:inline">{{ __('admin.block_manager.appearance') }}</span>
                        </button>

                        <!-- Controles de Reordenación (↑ / ↓) -->
                        <button type="button" @click="moveUp(index)" :disabled="index === 0" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-white disabled:opacity-20 transition-colors" title="{{ __('admin.block_manager.move_up') }}">
                            <span class="text-sm font-bold">↑</span>
                        </button>
                        <button type="button" @click="moveDown(index)" :disabled="index === blocks.length - 1" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-white disabled:opacity-20 transition-colors" title="{{ __('admin.block_manager.move_down') }}">
                            <span class="text-sm font-bold">↓</span>
                        </button>
                        <button type="button" @click="cloneBlock(index)" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-[#00FF99] transition-colors" title="{{ __('admin.block_manager.duplicate') }}"><i class="fa-solid fa-copy"></i></button>
                        
                        <!-- Plegar / Desplegar -->
                        <button type="button" @click="block.is_collapsed = !block.is_collapsed" class="p-1.5 rounded hover:bg-gray-800 text-gray-400 hover:text-white transition-colors" title="{{ __('admin.block_manager.collapse_toggle') }}">
                            <svg class="w-4 h-4 transition-transform duration-200" :class="block.is_collapsed ? '-rotate-90 text-gray-500' : 'rotate-0 text-[#00FF99]'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <button type="button" @click="removeBlock(index)" class="p-1.5 rounded hover:bg-red-950/40 text-red-400 hover:text-red-300 ml-1 transition-colors" title="{{ __('admin.block_manager.delete_section') }}">&times;</button>
                    </div>
                </div>

                <!-- Block Body (Collapsible) -->
                <div x-show="!block.is_collapsed" class="p-5 space-y-6">

                    <!-- MULTI-COLUMN SECTION EDITOR -->
                    <template x-if="block.columns_layout && block.columns_layout !== '1'">
                        <div class="space-y-4">
                            <div class="flex items-center justify-between pb-2 border-b border-gray-800">
                                <div class="text-xs text-gray-400 font-mono flex items-center gap-2">
                                    <span class="text-[#00FF99] font-bold">▦ {{ __('admin.block_manager.columns_layout') }}</span>
                                    <span class="px-2 py-0.5 rounded bg-gray-800 text-white font-bold" x-text="block.columns_layout === '2' ? '2 Columnas (50% / 50%)' : (block.columns_layout === '1-2' ? '2 Columnas (33% / 66%)' : (block.columns_layout === '2-1' ? '2 Columnas (66% / 33%)' : (block.columns_layout === '3' ? '3 Columnas (33% / 33% / 33%)' : '4 Columnas')))"></span>
                                </div>
                            </div>

                            <!-- Columns Grid -->
                            <div class="grid gap-6"
                                 :class="block.columns_layout === '2' ? 'grid-cols-1 md:grid-cols-2' : (block.columns_layout === '3' ? 'grid-cols-1 md:grid-cols-3' : (block.columns_layout === '4' ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4' : 'grid-cols-1 md:grid-cols-3'))">
                                <template x-for="(col, colIdx) in blockColumns(block)" :key="col.id || colIdx">
                                    <div class="bg-[#101010] border border-gray-800 rounded-xl p-4 flex flex-col justify-between"
                                         :class="block.columns_layout === '1-2' && colIdx === 1 ? 'md:col-span-2' : (block.columns_layout === '2-1' && colIdx === 0 ? 'md:col-span-2' : 'col-span-1')">
                                        
                                        <!-- Column Header -->
                                        <div class="flex items-center justify-between pb-3 mb-3 border-b border-gray-800/80">
                                            <span class="text-xs font-bold text-gray-300 font-mono" x-text="'Columna ' + (colIdx + 1) + (block.columns_layout === '1-2' ? (colIdx === 0 ? ' (33%)' : ' (66%)') : (block.columns_layout === '2-1' ? (colIdx === 0 ? ' (66%)' : ' (33%)') : ''))"></span>

                                            <div class="flex items-center gap-1.5">
                                                <button type="button"
                                                        @click="openColumnAppearanceModal(index, colIdx)"
                                                        class="p-1 text-gray-400 hover:text-[#00FF99] text-xs"
                                                        title="{{ __('admin.block_manager.column_appearance_tooltip') }}">
                                                    <i class="fa-solid fa-palette"></i>
                                                </button>

                                                <!-- Un único flujo: el mismo modal de bloques -->
                                                <button type="button"
                                                        @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx })"
                                                        class="px-2 py-1 rounded bg-[#101010] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/40 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 transition-colors">
                                                    <i class="fa-solid fa-plus text-[10px]"></i>
                                                    <span>{{ __('admin.block_manager.add_block') }}</span>
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Blocks inside this column -->
                                        <div class="space-y-4 min-h-[100px]">
                                            <template x-if="!col.blocks || col.blocks.length === 0">
                                                <button type="button"
                                                        @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx })"
                                                        class="w-full text-center py-8 border border-dashed border-gray-800 hover:border-[#00FF99]/60 hover:bg-[#00FF99]/[0.03] rounded-lg text-gray-500 hover:text-[#00FF99] text-xs font-mono transition-colors">
                                                    <i class="fa-solid fa-plus mr-1.5"></i>{{ __('admin.block_manager.add_block_empty') }}
                                                </button>
                                            </template>

                                            <template x-for="(subBlock, subIdx) in (col.blocks || [])" :key="subBlock.id || subIdx">
                                                <div class="bg-[#161616] border border-gray-800 rounded-lg p-3 space-y-3">
                                                    <!-- Sub-block header -->
                                                    <div class="flex items-center justify-between pb-2 border-b border-gray-800">
                                                        <div class="flex items-center gap-2">
                                                            <i x-show="isFontAwesomeIcon(typeDef(subBlock.type).icon)" :class="typeDef(subBlock.type).icon" class="text-xs text-[#00FF99]" x-cloak></i>
                                                            <span x-show="!isFontAwesomeIcon(typeDef(subBlock.type).icon)" class="text-xs text-[#00FF99] font-bold" x-text="typeGlyph(subBlock.type)" x-cloak></span>
                                                            <span class="text-xs font-bold text-white uppercase font-mono" x-text="availableTypes.find(t => t.type === subBlock.type)?.name || subBlock.type"></span>
                                                        </div>
                                                        <div class="flex items-center gap-1">
                                                            <button type="button"
                                                                    @click="openAppearanceModal(index, { colIndex: colIdx, subIndex: subIdx })"
                                                                    class="p-1 text-gray-400 hover:text-[#00FF99] text-xs"
                                                                    title="{{ __('admin.block_manager.appearance_tooltip') }}">
                                                                <i class="fa-solid fa-palette"></i>
                                                            </button>
                                                            <button type="button" @click="moveColumnBlockUp(index, colIdx, subIdx)" :disabled="subIdx === 0" class="p-1 text-gray-400 hover:text-white disabled:opacity-20 text-xs font-bold" title="{{ __('admin.block_manager.move_up_short') }}">↑</button>
                                                            <button type="button" @click="moveColumnBlockDown(index, colIdx, subIdx)" :disabled="subIdx === col.blocks.length - 1" class="p-1 text-gray-400 hover:text-white disabled:opacity-20 text-xs font-bold" title="{{ __('admin.block_manager.move_down_short') }}">↓</button>
                                                            <button type="button" @click="removeColumnBlock(index, colIdx, subIdx)" class="p-1 text-red-400 hover:text-red-300 text-xs font-bold" title="{{ __('admin.block_manager.delete') }}">&times;</button>
                                                        </div>
                                                    </div>

                                                    <!-- Sub-block Full Form Body (100% Parity with Root Blocks) -->
                                                    <div class="pt-1">
                                                        @include('components.admin.blocks._block_form_body', [
                                                            'blockKey' => 'subBlock',
                                                            'dynamicPayloads' => $dynamicPayloads,
                                                            'isSubBlock' => true,
                                                        ])
                                                    </div>
                                                </div>
                                            </template>
                                        </div>

                                        <!-- Mismo flujo de añadir, al final de la columna -->
                                        <button type="button"
                                                @click="openAddModalAt(null, { blockIndex: index, colIndex: colIdx })"
                                                x-show="col.blocks && col.blocks.length > 0"
                                                x-cloak
                                                class="mt-3 w-full py-2 rounded-lg border border-dashed border-gray-800 hover:border-[#00FF99]/60 hover:text-[#00FF99] text-gray-500 text-[11px] font-mono transition-colors">
                                            <i class="fa-solid fa-plus mr-1"></i>{{ __('admin.block_manager.add_block_here') }}
                                        </button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- SINGLE-BLOCK SECTION EDITOR (Cuando columns_layout es 1) -->
                    <div x-show="!block.columns_layout || block.columns_layout === '1'" class="space-y-6">
                        @include('components.admin.blocks._block_form_body', [
                            'blockKey' => 'block',
                            'dynamicPayloads' => $dynamicPayloads,
                            'isSubBlock' => false,
                        ])
                    </div>

                </div>

                <!-- Intermediate / Bottom Insertion Zone (visible on hover between blocks or at the end) -->
                <div class="group/insert relative z-20 py-2.5 -my-1 transition-all">
                    <div class="h-[2px] w-full bg-gradient-to-r from-transparent via-[#00FF99]/40 to-transparent opacity-0 group-hover/insert:opacity-100 transition-opacity"></div>
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none group-hover/insert:pointer-events-auto">
                        <button type="button"
                                @click="openAddModalAt(index + 1)"
                                class="opacity-0 group-hover/insert:opacity-100 scale-95 group-hover/insert:scale-100 transition-all duration-200 px-3 py-0.5 rounded-full bg-[#161616] hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/50 hover:border-[#00FF99] text-[11px] font-bold flex items-center gap-1.5 shadow-[0_0_15px_rgba(0,255,153,0.3)]">
                            <i class="fa-solid fa-plus text-[10px]"></i>
                            <span x-text="index === blocks.length - 1 ? @js(__('admin.block_manager.add_section_end')) : @js(__('admin.block_manager.add_section_here'))"></span>
                        </button>
                    </div>
                </div>
            </div>
            </div>
        </template>
    </div>

        </div>
    </div>

    <!-- Add Section / Widget Modal with Demo vs Blank Choice -->
    <div x-show="showAddModal"
         @click.self="showAddModal = false"
         @keydown.escape.window="showAddModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[#141414] border border-[#00FF99]/40 max-w-2xl w-full rounded-2xl p-6 font-mono space-y-4 shadow-[0_0_50px_rgba(0,0,0,0.9)] max-h-[90vh] flex flex-col"
             @click.outside="showAddModal = false">

            <!-- Modal Header with Insertion Mode Selector & Position Indicator -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800">
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>&gt;</span>
                            <span x-show="!columnTarget">{{ __('admin.block_manager.add_section') }} / Widget</span>
                            <span x-show="columnTarget" x-cloak>{{ __('admin.block_manager.add_block_to_column') }}</span>
                        </h3>
                        <span x-show="columnTarget" x-cloak class="px-2 py-0.5 rounded bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30 text-[10px] font-bold"
                              x-text="'Columna ' + (columnTarget ? (columnTarget.colIndex + 1) : 1)"></span>
                        <span x-show="insertIndex === 0" class="px-2 py-0.5 rounded bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30 text-[10px] font-bold">
                            Al principio (#1)
                        </span>
                        <span x-show="insertIndex !== null && insertIndex > 0 && insertIndex < blocks.length" class="px-2 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/30 text-[10px] font-bold" x-text="@js(__('admin.block_manager.at_position')).replace(':n', insertIndex + 1)"></span>
                        <span x-show="insertIndex === null || insertIndex >= blocks.length" class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/30 text-[10px] font-bold">
                            Al final
                        </span>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-0.5">{{ __('admin.block_manager.insert_block_desc') }}</p>
                </div>
                <button @click="showAddModal = false" class="text-gray-400 hover:text-white text-xl">&times;</button>
            </div>

            <!-- Insertion Mode Tabs (Demo Data vs Empty) -->
            <div class="flex items-center justify-between p-2.5 bg-[#1A1A1A] rounded-xl border border-gray-800">
                <span class="text-xs text-gray-300 font-bold flex items-center gap-1.5">
                    <span class="flex items-center gap-1.5"><i class="fa-solid fa-bolt text-[#00FF99]"></i> {{ __('admin.block_manager.insertion_mode') }}</span>
                </span>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="insertionMode = 'demo'"
                            :class="insertionMode === 'demo' ? 'bg-[#00FF99] text-black font-bold' : 'bg-gray-800 text-gray-300'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-wand-magic-sparkles text-amber-400"></i> {{ __('admin.block_manager.mode_demo') }}</span>
                    </button>
                    <button type="button"
                            @click="insertionMode = 'empty'"
                            :class="insertionMode === 'empty' ? 'bg-[#00FF99] text-black font-bold' : 'bg-gray-800 text-gray-300'"
                            class="px-3 py-1.5 rounded-lg text-xs transition-all flex items-center gap-1">
                        <span class="flex items-center gap-1.5"><i class="fa-solid fa-file text-gray-400"></i> {{ __('admin.block_manager.mode_empty') }}</span>
                    </button>
                </div>
            </div>

            <!-- Search and Filter Tabs -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-1 bg-[#1A1A1A] p-1 rounded-lg border border-gray-800">
                    <button type="button" @click="activeCategory = 'all'"
                            :class="activeCategory === 'all' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.cat_all') }}</button>
                    <button type="button" @click="activeCategory = 'content'"
                            :class="activeCategory === 'content' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.tab_content') }}</button>
                    <button type="button" @click="activeCategory = 'widget'"
                            :class="activeCategory === 'widget' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                            class="px-3 py-1 rounded text-xs transition-all">{{ __('admin.block_manager.tab_widgets') }}</button>
                </div>

                <input type="text" x-model="searchQuery" placeholder="{{ __('admin.block_manager.search_component') }}" class="bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-1.5 text-white text-xs w-48">
            </div>

            <!-- Component Grid with Quick Insertion Options -->
            <div class="flex-1 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-3 pr-1">
                <template x-for="item in filteredTypes()" :key="item.type">
                    <div class="p-3.5 rounded-xl bg-[#1A1A1A] border border-gray-800 hover:border-[#00FF99] transition-all group flex flex-col justify-between hover:bg-gray-800/40">
                        <div class="flex items-start gap-3">
                            <span class="text-xl shrink-0 w-10 h-10 flex items-center justify-center rounded-lg bg-black/50 border border-gray-800 group-hover:border-[#00FF99]/40 text-[#00FF99]">
                                <i x-show="isFontAwesomeIcon(item.icon)" :class="item.icon" x-cloak></i>
                                <span x-show="!isFontAwesomeIcon(item.icon)" class="font-bold" x-text="item.icon || (item.name ? item.name.trim().charAt(0).toUpperCase() : '?')" x-cloak></span>
                            </span>
                            <div>
                                <div class="font-bold text-white group-hover:text-[#00FF99] text-xs" x-text="item.name"></div>
                                <div class="text-[11px] text-gray-400 mt-0.5 leading-tight" x-text="item.desc"></div>
                            </div>
                        </div>
                        <div class="mt-3 pt-2.5 border-t border-gray-800 flex items-center justify-end gap-2">
                            <button type="button"
                                     @click="insertType(item.type, 'empty')"
                                     class="px-2.5 py-1 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded text-[10px]"
                                     title="{{ __('admin.block_manager.empty_tooltip') }}">
                                <i class="fa-solid fa-file mr-1"></i> En blanco
                            </button>
                            <button type="button"
                                     @click="insertType(item.type, 'demo')"
                                     class="px-2.5 py-1 bg-[#00FF99]/20 hover:bg-[#00FF99] text-[#00FF99] hover:text-black font-bold rounded text-[10px] border border-[#00FF99]/40"
                                     title="{{ __('admin.block_manager.demo_tooltip') }}">
                                <i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Con datos de ejemplo
                            </button>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 border-t border-gray-800 flex justify-end">
                <button type="button" @click="closeAddModal()" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-xs">
                    Cancelar
                </button>
            </div>

        </div>
    </div>

    <!-- Appearance Modal (Ventana Emergente de Personalización Visual) -->
    <div x-show="showAppearanceModal && editingAppearanceIndex !== null"
         @click.self="closeAppearanceModal()"
         @keydown.escape.window="closeAppearanceModal()"
         x-transition
         x-cloak
         class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[#141414] border border-[#00FF99]/40 rounded-2xl p-6 font-mono space-y-5 shadow-[0_0_50px_rgba(0,0,0,0.9)] flex flex-col transition-all duration-200"
             :class="isAppearanceMaximized ? 'w-[98vw] h-[96vh] max-w-none' : 'max-w-3xl w-full max-h-[90vh]'"
             @click.outside="closeAppearanceModal()">

            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-xl text-[#00FF99]"><i class="fa-solid fa-palette"></i></span>
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>{{ __('admin.block_manager.appearance_title') }}</span>
                            <span class="text-[#00FF99]" x-text="'#' + (editingAppearanceIndex + 1)"></span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold border"
                                  :class="appearanceScope === 'section' ? 'bg-[#00FF99]/10 text-[#00FF99] border-[#00FF99]/40' : (appearanceScope === 'column' ? 'bg-amber-500/10 text-amber-300 border-amber-500/40' : 'bg-sky-500/10 text-sky-300 border-sky-500/40')"
                                  x-text="appearanceScope === 'section' ? @js(__('admin.block_manager.scope_section')) : (appearanceScope === 'column' ? @js(__('admin.block_manager.scope_column')) : @js(__('admin.block_manager.scope_block')))"></span>
                        </h3>
                        <p class="text-[11px] text-gray-400">{{ __('admin.block_manager.appearance_subtitle') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button"
                            @click="isAppearanceMaximized = !isAppearanceMaximized"
                            class="p-1.5 text-gray-400 hover:text-[#00FF99] rounded-lg hover:bg-white/5 transition-colors"
                            :title="isAppearanceMaximized ? 'Restaurar tamaño' : 'Maximizar'">
                        <i class="fa-solid text-sm" :class="isAppearanceMaximized ? 'fa-compress' : 'fa-expand'"></i>
                    </button>
                    <button type="button" @click="closeAppearanceModal()" class="text-gray-400 hover:text-white text-xl p-1 leading-none">&times;</button>
                </div>
            </div>

            <!-- Appearance Navigation Tabs -->
            <div class="flex items-center gap-2 border-b border-gray-800 pb-2 shrink-0">
                <button type="button"
                        @click="appearanceTab = 'container'"
                        :class="appearanceTab === 'container' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-box"></i>
                    <span>{{ __('admin.block_manager.container') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'background'"
                        :class="appearanceTab === 'background' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-image"></i>
                    <span>{{ __('admin.block_manager.background') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'dividers'"
                        :class="appearanceTab === 'dividers' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-water"></i>
                    <span>{{ __('admin.block_manager.dividers') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'borders'"
                        :class="appearanceTab === 'borders' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-bolt"></i>
                    <span>{{ __('admin.block_manager.borders') }}</span>
                </button>
                <button type="button"
                        @click="appearanceTab = 'scroll'"
                        :class="appearanceTab === 'scroll' ? 'bg-[#00FF99]/20 text-[#00FF99] border-[#00FF99]/40' : 'text-gray-400 hover:text-white border-transparent'"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold border transition-all flex items-center gap-1.5">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    <span>{{ __('admin.block_manager.animations') }}</span>
                </button>
            </div>

            <!-- Appearance Content Tabs -->
            <div class="flex-1 overflow-y-auto pr-1">
                <template x-if="editingAppearanceIndex !== null && blocks[editingAppearanceIndex]">
                    <div>
                        <!-- TAB 1: CONTAINER -->
                        <div x-show="appearanceTab === 'container'" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.container_style') }}</label>
                                    <select x-model="appearanceModel().container_style" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <template x-for="style in containerStyles" :key="style.value">
                                            <option :value="style.value" x-text="style.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.container_content') }}</label>
                                    <select x-model="appearanceModel().content_width" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <option value="centered">{{ __('admin.block_manager.container_standard') }}</option>
                                        <option value="narrow">{{ __('admin.block_manager.container_narrow') }}</option>
                                        <option value="full">{{ __('admin.block_manager.container_full') }}</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: BACKGROUND -->
                        <div x-show="appearanceTab === 'background'" class="space-y-4">
                            <div>
                                <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_type') }}</label>
                                <select x-model="appearanceModel().bg_type" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                    <option value="none">{{ __('admin.block_manager.bg_none') }}</option>
                                    <option value="color">{{ __('admin.block_manager.bg_color') }}</option>
                                    <option value="image">{{ __('admin.block_manager.bg_image') }}</option>
                                    <option value="video">{{ __('admin.block_manager.bg_video') }}</option>
                                </select>
                            </div>

                            <div x-show="appearanceModel().bg_type === 'color'">
                                <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_color_label') }}</label>
                                <input type="text" x-model="appearanceModel().bg_color" @input="syncBlocksToLocale()" placeholder="#111827 o linear-gradient(...)" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                            </div>

                            <div x-show="appearanceModel().bg_type === 'image'" class="space-y-3">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_image_url') }}</label>
                                    <div class="flex gap-2">
                                        <input type="text" x-model="appearanceModel().bg_image_url" @input="syncBlocksToLocale()" placeholder="https://... o ruta multimedia" class="flex-1 bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                        <button type="button" @click="openMediaPickerForObject(blocks[editingAppearanceIndex], 'bg_image_url')" class="px-3 py-2 bg-gray-800 text-[#00FF99] rounded-lg font-bold"><i class="fa-solid fa-image"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.overlay') }}</label>
                                    <input type="text" x-model="appearanceModel().bg_image_overlay" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_manager.overlay_placeholder') }}" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>

                                <!-- Comportamiento del fondo: solo donde hay fondo de verdad
                                     (Sección y Columna); nunca en el ámbito de Bloque. -->
                                <div x-show="appearanceScope !== 'block'">
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_behavior') }}</label>
                                    <select x-model="appearanceModel().bg_behavior" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <option value="normal">{{ __('admin.block_manager.bg_behavior_normal') }}</option>
                                        <option value="parallax">{{ __('admin.block_manager.bg_behavior_parallax') }}</option>
                                        <option value="fixed">{{ __('admin.block_manager.bg_behavior_fixed') }}</option>
                                    </select>
                                    <p class="text-[10px] text-gray-500 mt-1">{{ __('admin.block_manager.bg_behavior_hint') }}</p>
                                </div>
                            </div>

                            <div x-show="appearanceModel().bg_type === 'video'" class="space-y-3">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.bg_video_url') }}</label>
                                    <div class="flex gap-2">
                                        <input type="text" x-model="appearanceModel().bg_video_url" @input="syncBlocksToLocale()" placeholder="https://... o archivo multimedia" class="flex-1 bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                        <button type="button" @click="openMediaPickerForObject(blocks[editingAppearanceIndex], 'bg_video_url')" class="px-3 py-2 bg-gray-800 text-[#00FF99] rounded-lg font-bold"><i class="fa-solid fa-film"></i></button>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.video_poster') }}</label>
                                    <input type="text" x-model="appearanceModel().bg_video_poster" @input="syncBlocksToLocale()" placeholder="https://..." class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.overlay') }}</label>
                                    <input type="text" x-model="appearanceModel().bg_video_overlay" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_manager.overlay_placeholder') }}" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>
                            </div>
                        </div>

                        <!-- TAB 3: DIVIDERS -->
                        <div x-show="appearanceTab === 'dividers'" class="space-y-4">
                            <!-- Divisor Superior -->
                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="block text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.divider_top') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <select x-model="appearanceModel().divider_top.type" @change="syncBlocksToLocale()" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs">
                                            <template x-for="d in dividerTypes" :key="d.value">
                                                <option :value="d.value" x-text="d.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <input type="text" x-model="appearanceModel().divider_top.color" @input="syncBlocksToLocale()" placeholder="#0E0E0E" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="number" x-model.number="appearanceModel().divider_top.height" @input="syncBlocksToLocale()" placeholder="60" class="w-20 bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                        <label class="flex items-center gap-1 text-gray-400 text-[11px] cursor-pointer">
                                            <x-admin.switch x-model="appearanceModel().divider_top.flip" @change="syncBlocksToLocale()" size="sm" />
                                            <span>{{ __('admin.block_manager.invert') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Divisor Inferior -->
                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="block text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.divider_bottom') }}</label>
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                    <div>
                                        <select x-model="appearanceModel().divider_bottom.type" @change="syncBlocksToLocale()" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs">
                                            <template x-for="d in dividerTypes" :key="d.value">
                                                <option :value="d.value" x-text="d.label"></option>
                                            </template>
                                        </select>
                                    </div>
                                    <div>
                                        <input type="text" x-model="appearanceModel().divider_bottom.color" @input="syncBlocksToLocale()" placeholder="#0E0E0E" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <input type="number" x-model.number="appearanceModel().divider_bottom.height" @input="syncBlocksToLocale()" placeholder="60" class="w-20 bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                        <label class="flex items-center gap-1 text-gray-400 text-[11px] cursor-pointer">
                                            <x-admin.switch x-model="appearanceModel().divider_bottom.flip" @change="syncBlocksToLocale()" size="sm" />
                                            <span>{{ __('admin.block_manager.invert') }}</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- TAB 4: BORDERS -->
                        <div x-show="appearanceTab === 'borders'" class="space-y-4">
                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <x-admin.switch x-model="appearanceModel().border_top.enabled" @change="syncBlocksToLocale()" size="sm" />
                                    <span class="text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.border_top') }}</span>
                                </label>
                                <div x-show="appearanceModel().border_top.enabled" class="grid grid-cols-2 gap-3">
                                    <input type="text" x-model="appearanceModel().border_top.color" @input="syncBlocksToLocale()" placeholder="#00FF99" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    <input type="text" x-model="appearanceModel().border_top.width" @input="syncBlocksToLocale()" placeholder="2px" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                </div>
                            </div>

                            <div class="p-3 bg-[#1A1A1A] rounded-xl border border-gray-800 space-y-3">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <x-admin.switch x-model="appearanceModel().border_bottom.enabled" @change="syncBlocksToLocale()" size="sm" />
                                    <span class="text-[#00FF99] text-[10px] uppercase font-bold">{{ __('admin.block_manager.border_bottom') }}</span>
                                </label>
                                <div x-show="appearanceModel().border_bottom.enabled" class="grid grid-cols-2 gap-3">
                                    <input type="text" x-model="appearanceModel().border_bottom.color" @input="syncBlocksToLocale()" placeholder="#00FF99" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                    <input type="text" x-model="appearanceModel().border_bottom.width" @input="syncBlocksToLocale()" placeholder="2px" class="bg-[#121212] border border-gray-700 rounded px-2 py-1.5 text-white text-xs font-mono">
                                </div>
                            </div>
                        </div>

                        <!-- TAB 5: SCROLL ANIMATIONS -->
                        <div x-show="appearanceTab === 'scroll'" class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.effect') }}</label>
                                    <select x-model="appearanceModel().animation" @change="appearanceModel().scroll_magic.behavior = appearanceModel().animation; syncBlocksToLocale();" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <template x-for="anim in animationOptions" :key="anim.value">
                                            <option :value="anim.value" x-text="anim.label"></option>
                                        </template>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.effect_scrub') }}</label>
                                    <select x-model="appearanceModel().scroll_magic.scrub" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs">
                                        <option value="none">{{ __('admin.block_manager.effect_standard') }}</option>
                                        <option value="rotate">{{ __('admin.block_manager.effect_rotate') }}</option>
                                        <option value="playback">{{ __('admin.block_manager.effect_play_visibility') }}</option>
                                        <option value="parallax">{{ __('admin.block_manager.effect_parallax') }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('admin.block_manager.delay') }}</label>
                                    <input type="text" x-model="appearanceModel().animation_delay" @input="syncBlocksToLocale()" placeholder="0.2s" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white text-xs font-mono">
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="pt-3 border-t border-gray-800 flex items-center justify-between shrink-0">
                <span class="text-[11px] text-gray-500">{{ __('admin.block_manager.changes_immediate') }}</span>
                <button type="button" @click="closeAppearanceModal()" class="btn-neon py-1.5 px-4 text-xs font-bold">
                    <i class="fa-solid fa-check mr-1.5"></i> Aplicar y cerrar
                </button>
            </div>
        </div>
    </div>

    <!-- Icon & Emoji Picker Modal -->
    <div x-show="showIconPickerModal"
         @click.self="showIconPickerModal = false"
         @keydown.escape.window="showIconPickerModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/40 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[#141414] border border-[#00FF99]/40 max-w-2xl w-full rounded-2xl p-6 font-mono space-y-4 shadow-[0_0_50px_rgba(0,0,0,0.9)] max-h-[85vh] flex flex-col"
             @click.outside="showIconPickerModal = false">

            <!-- Modal Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800 shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-xl text-[#00FF99]"><i class="fa-solid fa-icons"></i></span>
                    <div>
                        <h3 class="text-base font-bold text-white flex items-center gap-2">
                            <span>{{ __('admin.block_manager.icon_selector_title') }}</span>
                        </h3>
                        <p class="text-[11px] text-gray-400">{{ __('admin.block_manager.icon_selector_desc') }}</p>
                    </div>
                </div>
                <button type="button" @click="showIconPickerModal = false" class="text-gray-400 hover:text-white text-xl">&times;</button>
            </div>

            <!-- Search bar -->
            <div class="shrink-0">
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 pointer-events-none">
                        <i class="fa-solid fa-magnifying-glass text-xs"></i>
                    </span>
                    <input type="text"
                           x-model="iconPickerSearch"
                           placeholder="{{ __('admin.block_manager.icon_search_placeholder') }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-xl pl-9 pr-4 py-2 text-white text-xs placeholder-gray-500 focus:outline-none transition-colors">
                </div>
            </div>

            <!-- Category Filter Tabs -->
            <div class="flex flex-wrap items-center gap-1.5 shrink-0 border-b border-gray-800/80 pb-3">
                <button type="button" @click="iconPickerCategory = 'all'"
                        :class="iconPickerCategory === 'all' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_all') }}</button>
                <button type="button" @click="iconPickerCategory = 'popular'"
                        :class="iconPickerCategory === 'popular' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_popular') }}</button>
                <button type="button" @click="iconPickerCategory = 'business'"
                        :class="iconPickerCategory === 'business' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_business') }}</button>
                <button type="button" @click="iconPickerCategory = 'tech'"
                        :class="iconPickerCategory === 'tech' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_tech') }}</button>
                <button type="button" @click="iconPickerCategory = 'comm'"
                        :class="iconPickerCategory === 'comm' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_comm') }}</button>
                <button type="button" @click="iconPickerCategory = 'commerce'"
                        :class="iconPickerCategory === 'commerce' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_commerce') }}</button>
                <button type="button" @click="iconPickerCategory = 'emojis'"
                        :class="iconPickerCategory === 'emojis' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white bg-[#1A1A1A] border border-gray-800'"
                        class="px-2.5 py-1 rounded-lg text-[11px] transition-all">{{ __('admin.block_manager.cat_emojis') }}</button>
            </div>

            <!-- Scrollable Grid Area -->
            <div class="flex-1 overflow-y-auto pr-1 space-y-4">
                <!-- FontAwesome Icons Section -->
                <div x-show="iconPickerCategory !== 'emojis'">
                    <div class="text-[10px] text-gray-500 uppercase font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-solid fa-font-awesome text-[#00FF99]"></i>
                        <span>{{ __('admin.block_manager.icon_fa') }} (<span x-text="filteredPickerIcons().length"></span>)</span>
                    </div>
                    <div class="grid grid-cols-4 sm:grid-cols-6 gap-2">
                        <template x-for="item in filteredPickerIcons()" :key="item.icon">
                            <button type="button"
                                    @click="selectIcon(item.icon)"
                                    class="p-2.5 rounded-xl bg-[#1A1A1A] hover:bg-[#00FF99]/15 border border-gray-800 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] flex flex-col items-center justify-center gap-1.5 transition-all group"
                                    :title="item.name + ' (' + item.icon + ')'">
                                <i :class="item.icon" class="text-xl group-hover:scale-110 transition-transform"></i>
                                <span class="text-[9px] text-gray-400 group-hover:text-white truncate w-full text-center" x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <!-- Emojis Section -->
                <div x-show="iconPickerCategory === 'all' || iconPickerCategory === 'emojis'">
                    <div class="text-[10px] text-gray-500 uppercase font-bold mb-2 flex items-center gap-1.5">
                        <i class="fa-regular fa-face-smile text-amber-400"></i>
                        <span>{{ __('admin.block_manager.icon_emojis') }} (<span x-text="filteredPickerEmojis().length"></span>)</span>
                    </div>
                    <div class="grid grid-cols-5 sm:grid-cols-8 gap-2">
                        <template x-for="item in filteredPickerEmojis()" :key="item.emoji">
                            <button type="button"
                                    @click="selectIcon(item.emoji)"
                                    class="p-2 rounded-xl bg-[#1A1A1A] hover:bg-amber-400/15 border border-gray-800 hover:border-amber-400 text-gray-200 flex flex-col items-center justify-center gap-1 transition-all group"
                                    :title="item.name">
                                <span class="text-2xl group-hover:scale-110 transition-transform" x-text="item.emoji"></span>
                                <span class="text-[8px] text-gray-400 group-hover:text-white truncate w-full text-center" x-text="item.name"></span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Manual Value Bar & Footer -->
            <div class="pt-3 border-t border-gray-800 flex flex-wrap items-center justify-between gap-3 shrink-0">
                <div class="flex items-center gap-2 flex-1 max-w-sm">
                    <input type="text"
                           x-model="iconPickerManual"
                           placeholder="{{ __('admin.block_manager.icon_manual_placeholder') }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-1.5 text-white text-xs font-mono">
                    <button type="button"
                            @click="if (iconPickerManual.trim()) selectIcon(iconPickerManual.trim())"
                            class="px-3 py-1.5 bg-gray-800 hover:bg-[#00FF99] text-gray-300 hover:text-black rounded-lg text-xs font-bold transition-colors whitespace-nowrap">
                        Aplicar
                    </button>
                </div>
                <button type="button" @click="showIconPickerModal = false" class="px-4 py-1.5 bg-gray-800 hover:bg-gray-700 text-gray-300 rounded-lg text-xs">
                    Cerrar
                </button>
            </div>

        </div>
    </div>

</div>
