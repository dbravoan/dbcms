@props([
    'blockKey' => 'block',
    'dynamicPayloads' => [],
    'isSubBlock' => false,
    'colIdx' => null,
    'subIdx' => null,
])

@php
    $b = $blockKey; // e.g. 'block' or 'subBlock'
    $dynamicPayloads = $dynamicPayloads ?? [];
    $forms = $dynamicPayloads['forms'] ?? [];
    $blogCategories = $dynamicPayloads['blog_categories'] ?? [];
    $blogPosts = $dynamicPayloads['blog_posts'] ?? [];
    $shopCategories = $dynamicPayloads['shop_categories'] ?? [];
    $shopProducts = $dynamicPayloads['shop_products'] ?? [];
    $galleryAlbums = $dynamicPayloads['gallery_albums'] ?? [];
    $chartTypes = $dynamicPayloads['charts_types'] ?? [];
@endphp

<div class="space-y-4 font-mono text-xs">

    <!-- 1. HERO BLOCK -->
    <template x-if="{{ $b }}.type === 'hero'">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.hero_title') }} *</label>
                <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_perf') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-bold text-xs">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.subtitle_desc') }}</label>
                <textarea x-model="{{ $b }}.content" @input="syncBlocksToLocale()" rows="2" placeholder="{{ __('admin.block_form.ph_architecture') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-gray-300 text-xs"></textarea>
            </div>
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.main_button_text') }}</label>
                <input type="text" x-model="{{ $b }}.cta_text" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_discover') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            </div>
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.cta_url_label') }}</label>
                <input type="text" x-model="{{ $b }}.cta_url" @input="syncBlocksToLocale()" placeholder="/shop" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.featured_image') }}</label>
                <div class="flex gap-2">
                    <input type="text" x-model="{{ $b }}.image_url" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_media_url') }}" class="flex-1 bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-mono text-xs">
                    <button type="button" @click="openMediaPickerForObject({{ $b }}, 'image_url')" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-[#00FF99] border border-gray-700 rounded-lg font-bold text-xs flex items-center gap-1">
                        <i class="fa-solid fa-image"></i>
                        <span>{{ __('admin.block_form.select') }}</span>
                    </button>
                </div>
                <template x-if="{{ $b }}.image_url">
                    <div class="mt-2 flex items-center gap-3 p-2 bg-black/40 rounded-lg border border-gray-800">
                        <img :src="{{ $b }}.image_url" alt="Preview" class="h-12 w-20 object-cover rounded border border-gray-700">
                        <span class="text-[11px] text-gray-400 truncate flex-1" x-text="{{ $b }}.image_url"></span>
                        <button type="button" @click="{{ $b }}.image_url = ''; syncBlocksToLocale()" class="text-red-400 hover:text-red-300 text-xs px-2 py-1">&times; {{ __('admin.block_form.remove') }}</button>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <!-- 2. TEXT / DUAL WYSIWYG (MARKDOWN & HTML) BLOCK -->
    <template x-if="{{ $b }}.type === 'text'">
        <div class="space-y-3" x-data="{
            format: {{ $b }}.format || 'markdown',
            viewMode: 'edit',
            previewTab: 'rendered',
            wordCount: 0,
            charCount: 0,
            copied: false,
            updateStats() {
                const txt = {{ $b }}.content || '';
                this.charCount = txt.length;
                this.wordCount = txt.trim() ? txt.trim().split(/\s+/).length : 0;
            },
            setFormat(f) {
                this.format = f;
                {{ $b }}.format = f;
                syncBlocksToLocale();
            },
            formatHtmlCode() {
                if ({{ $b }}.content) {
                    {{ $b }}.content = formatHtmlString({{ $b }}.content);
                    syncBlocksToLocale();
                }
            },
            copySource() {
                const compiled = renderContentPreview({{ $b }}.content, this.format);
                navigator.clipboard.writeText(compiled).then(() => {
                    this.copied = true;
                    setTimeout(() => this.copied = false, 2000);
                });
            }
        }" x-init="if (!{{ $b }}.format) {{ $b }}.format = 'markdown'; updateStats(); $watch('{{ $b }}.content', () => updateStats())">
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title_optional') }}</label>
                <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_about_tech') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            </div>

            <!-- Cyber WYSIWYG Editor Container -->
            <div class="border border-gray-700 focus-within:border-[#00FF99] rounded-xl overflow-hidden bg-[#141414] transition-colors shadow-inner">

                <!-- TOP CONTROL BAR -->
                <div class="bg-[#181818] border-b border-gray-800 p-2.5 flex flex-wrap items-center justify-between gap-2.5">
                    <!-- Left: Format Switcher (Markdown vs HTML) -->
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1 bg-[#101010] p-1 rounded-lg border border-gray-800">
                            <span class="text-[10px] text-gray-400 font-bold uppercase px-1 font-mono">{{ __('admin.block_form.syntax') }}</span>
                            <button type="button" @click="setFormat('markdown')"
                                    :class="format === 'markdown' ? 'bg-[#00FF99] text-black font-bold shadow-[0_0_8px_rgba(0,255,153,0.3)]' : 'text-gray-400 hover:text-white'"
                                    class="px-2 py-0.5 rounded text-[11px] font-mono transition-all flex items-center gap-1">
                                <i class="fa-solid fa-bolt"></i>
                                <span>{{ __('admin.block_form.markdown') }}</span>
                            </button>
                            <button type="button" @click="setFormat('html')"
                                    :class="format === 'html' ? 'bg-[#00FF99] text-black font-bold shadow-[0_0_8px_rgba(0,255,153,0.3)]' : 'text-gray-400 hover:text-white'"
                                    class="px-2 py-0.5 rounded text-[11px] font-mono transition-all flex items-center gap-1">
                                <i class="fa-solid fa-code"></i>
                                <span>{{ __('admin.block_form.html_pure') }}</span>
                            </button>
                        </div>

                        <!-- Stats Badge -->
                        <span class="text-[10px] text-gray-500 font-mono hidden sm:inline"
                              x-text="wordCount + ' palabras · ' + charCount + ' caracteres'"></span>
                    </div>

                    <!-- Right: View Mode (Edit / Split / Preview) & Multimedia -->
                    <div class="flex items-center gap-2">
                        <div class="flex items-center gap-1 bg-[#101010] p-0.5 rounded-lg border border-gray-800">
                            <button type="button" @click="viewMode = 'edit'"
                                    :class="viewMode === 'edit' ? 'bg-[#1F1F1F] text-[#00FF99] font-bold border border-[#00FF99]/40' : 'text-gray-400 hover:text-white'"
                                    class="px-2.5 py-1 rounded text-[11px] transition-all flex items-center gap-1">
                                <i class="fa-solid fa-pen"></i>
                                <span>{{ __('admin.block_form.write') }}</span>
                            </button>
                            <button type="button" @click="viewMode = 'split'"
                                    :class="viewMode === 'split' ? 'bg-[#1F1F1F] text-[#00FF99] font-bold border border-[#00FF99]/40' : 'text-gray-400 hover:text-white'"
                                    class="px-2.5 py-1 rounded text-[11px] transition-all flex items-center gap-1"
                                    title="Vista dividida: editor y previsualización en vivo simultánea">
                                <i class="fa-solid fa-columns"></i>
                                <span>{{ __('admin.block_form.split') }}</span>
                            </button>
                            <button type="button" @click="viewMode = 'preview'"
                                    :class="viewMode === 'preview' ? 'bg-[#1F1F1F] text-[#00FF99] font-bold border border-[#00FF99]/40' : 'text-gray-400 hover:text-white'"
                                    class="px-2.5 py-1 rounded text-[11px] transition-all flex items-center gap-1">
                                <i class="fa-solid fa-eye"></i>
                                <span>{{ __('admin.block_form.preview') }}</span>
                            </button>
                        </div>

                        <!-- Multimedia Picker Button -->
                        <button type="button" @click="openMediaPickerForMarkdown({{ $b }})"
                                class="px-2.5 py-1 bg-[#00FF99]/10 border border-[#00FF99]/40 text-[#00FF99] hover:bg-[#00FF99] hover:text-black rounded text-[11px] font-bold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-image"></i>
                            <span class="hidden sm:inline">{{ __('admin.block_form.add_multimedia') }}</span>
                        </button>
                    </div>
                </div>

                <!-- CONTEXTUAL TOOLBAR (Shown in Edit and Split views) -->
                <div x-show="viewMode !== 'preview'" class="bg-[#121212] border-b border-gray-800/80 px-2.5 py-1.5 flex flex-wrap items-center justify-between gap-1.5">
                    <!-- Markdown Toolbar Buttons -->
                    <div x-show="format === 'markdown'" class="flex items-center flex-wrap gap-1">
                        <button type="button" @click="appendSnippet({{ $b }}, '# ', '')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded font-bold text-[11px]" title="Encabezado 1">H1</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '## ', '')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded font-bold text-[11px]" title="Encabezado 2">H2</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '### ', '')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded font-bold text-[11px]" title="Encabezado 3">H3</button>
                        <span class="text-gray-700">|</span>
                        <button type="button" @click="appendSnippet({{ $b }}, '**', '**')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded font-bold text-[11px]" title="Negrita">**B**</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '*', '*')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded italic text-[11px]" title="Cursiva">*I*</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '~~', '~~')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded line-through text-[11px]" title="Tachado">~S~</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '`', '`')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-[#00FF99] rounded font-mono text-[11px]" title="Código en línea">&lt;/&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n```bash\n', '\n```\n')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-[#00FF99] rounded font-mono text-[11px]" title="Bloque de código">```</button>
                        <span class="text-gray-700">|</span>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n> ', '\n')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]" title="Cita">&ldquo; {{ __('admin.block_form.quote_short') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n- ', '')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]" title="Lista">• {{ __('admin.wysiwyg.list_label') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n1. ', '')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]" title="Lista numerada">1. {{ __('admin.wysiwyg.list_label') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n| Encabezado 1 | Encabezado 2 |\n|---|---|\n| Celda 1 | Celda 2 |\n')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]" title="Tabla Markdown">▦ {{ __('admin.block_form.table') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '[Texto enlace](', ')')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]" title="Enlace"><i class="fa-solid fa-link"></i></button>
                    </div>

                    <!-- HTML Toolbar Buttons -->
                    <div x-show="format === 'html'" class="flex items-center flex-wrap gap-1">
                        <button type="button" @click="appendSnippet({{ $b }}, '<p>', '</p>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-[#00FF99] rounded font-mono text-[11px]">&lt;p&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<div class=\'my-4\'>\n  ', '\n</div>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-[#00FF99] rounded font-mono text-[11px]">&lt;div&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<h2>', '</h2>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-[#00FF99] rounded font-mono text-[11px]">&lt;h2&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<h3>', '</h3>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-[#00FF99] rounded font-mono text-[11px]">&lt;h3&gt;</button>
                        <span class="text-gray-700">|</span>
                        <button type="button" @click="appendSnippet({{ $b }}, '<strong>', '</strong>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded font-bold text-[11px]">&lt;b&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<em>', '</em>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded italic text-[11px]">&lt;i&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<ul class=\'list-disc pl-5 my-2\'>\n  <li>', '</li>\n</ul>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]">&lt;ul&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<li>', '</li>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]">&lt;li&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<a href=\'https://...\' class=\'text-[#00FF99] underline\'>', '</a>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]">&lt;a&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<div class=\'overflow-x-auto my-3\'><table class=\'w-full border border-gray-800\'>\n  <thead><tr><th class=\'p-2 text-left\'>Col 1</th></tr></thead>\n  <tbody><tr><td class=\'p-2 border-t border-gray-800\'>Val 1</td></tr></tbody>\n</table></div>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]">&lt;table&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<iframe src=\'https://...\' class=\'w-full h-64 rounded-lg border border-gray-800\'>', '</iframe>')" class="px-1.5 py-0.5 bg-[#181818] hover:bg-gray-800 text-gray-300 rounded text-[11px]">&lt;iframe&gt;</button>
                        <span class="text-gray-700">|</span>
                        <button type="button" @click="formatHtmlCode()" class="px-2 py-0.5 bg-blue-950/40 hover:bg-blue-900/60 border border-blue-500/40 text-blue-300 rounded font-mono text-[11px] flex items-center gap-1" title="Formatear e indentar código HTML"><i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('admin.block_form.indent_html') }}</button>
                    </div>
                </div>

                <!-- 1. EDIT VIEW (Full-width Textarea) -->
                <div x-show="viewMode === 'edit'" class="p-3">
                    <textarea x-model="{{ $b }}.content"
                              @input="syncBlocksToLocale()"
                              rows="{{ $isSubBlock ? 6 : 9 }}"
                              :placeholder="format === 'markdown' ? 'Redacta tu contenido en Markdown con soporte completo para HTML incrustado...' : 'Escribe tu código HTML estructurado (etiquetas, estilos, tablas, componentes)...'"
                              class="w-full bg-transparent border-0 focus:ring-0 text-white font-mono text-xs leading-relaxed placeholder-gray-600 resize-y outline-none"></textarea>
                </div>

                <!-- 2. SPLIT VIEW (Side-by-side Editor & Live Preview) -->
                <div x-show="viewMode === 'split'" class="p-3">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
                        <!-- Left: Editor Textarea -->
                        <div class="border border-gray-800 rounded-lg p-2.5 bg-black/40">
                            <div class="text-[10px] text-gray-500 uppercase font-bold mb-1.5 flex items-center justify-between">
                                <span x-text="'Editor: ' + (format === 'markdown' ? 'Markdown' : 'HTML')"></span>
                                <span class="text-gray-600">{{ __('admin.block_form.realtime') }}</span>
                            </div>
                            <textarea x-model="{{ $b }}.content"
                                      @input="syncBlocksToLocale()"
                                      rows="{{ $isSubBlock ? 8 : 12 }}"
                                      :placeholder="format === 'markdown' ? 'Escribe Markdown aquí...' : 'Escribe HTML aquí...'"
                                      class="w-full bg-transparent border-0 focus:ring-0 text-white font-mono text-xs leading-relaxed placeholder-gray-600 resize-y outline-none"></textarea>
                        </div>

                        <!-- Right: Real-time Live Preview -->
                        <div class="border border-gray-800 rounded-lg p-3 bg-[#0B0B0B] min-h-[220px] max-h-[400px] overflow-y-auto sidebar-scroll">
                            <div class="text-[10px] text-[#00FF99] uppercase font-bold mb-2 pb-1 border-b border-gray-800 flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[#00FF99] animate-pulse"></span>
                                    <span>{{ __('admin.block_form.live_preview') }}</span>
                                </span>
                                <span class="text-gray-500 text-[9px] font-mono">{{ __('admin.block_form.instant_render') }}</span>
                            </div>
                            <div class="prose prose-invert max-w-none text-xs leading-relaxed text-gray-200"
                                 x-html="renderContentPreview({{ $b }}.content, format)"></div>
                        </div>
                    </div>
                </div>

                <!-- 3. FULL PREVIEW VIEW -->
                <div x-show="viewMode === 'preview'" class="p-4 bg-[#0E0E0E] min-h-[160px] max-h-[420px] overflow-y-auto sidebar-scroll">
                    <!-- Preview Sub-header -->
                    <div class="flex items-center justify-between pb-2 mb-3 border-b border-gray-800">
                        <div class="flex items-center gap-2">
                            <button type="button" @click="previewTab = 'rendered'"
                                    :class="previewTab === 'rendered' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                                    class="px-2 py-0.5 rounded text-[11px] font-mono transition-all flex items-center gap-1">
                                <i class="fa-solid fa-eye"></i>
                                <span>{{ __('admin.block_form.rendered') }}</span>
                            </button>
                            <button type="button" @click="previewTab = 'source'"
                                    :class="previewTab === 'source' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                                    class="px-2 py-0.5 rounded text-[11px] font-mono transition-all flex items-center gap-1">
                                <span>&lt;/&gt; Código HTML</span>
                            </button>
                        </div>

                        <div x-show="previewTab === 'source'">
                            <button type="button" @click="copySource()" class="px-2 py-0.5 rounded bg-gray-800 hover:bg-gray-700 text-[#00FF99] text-[10px] font-mono font-bold transition-colors flex items-center gap-1">
                                <i :class="copied ? 'fa-solid fa-check' : 'fa-solid fa-copy'"></i>
                                <span x-text="copied ? 'Copiado' : 'Copiar HTML'"></span>
                            </button>
                        </div>
                    </div>

                    <!-- Rendered DOM output -->
                    <div x-show="previewTab === 'rendered'"
                         class="prose prose-invert max-w-none text-xs leading-relaxed text-gray-200"
                         x-html="renderContentPreview({{ $b }}.content, format)"></div>

                    <!-- Compiled HTML Source Output -->
                    <div x-show="previewTab === 'source'">
                        <pre class="bg-black/80 border border-gray-800 rounded-lg p-3 text-[#00FF99] font-mono text-[11px] overflow-x-auto whitespace-pre-wrap select-all"
                             x-text="renderContentPreview({{ $b }}.content, format)"></pre>
                    </div>
                </div>

            </div>
        </div>
    </template>

    <!-- 3. FEATURES BLOCK -->
    <template x-if="{{ $b }}.type === 'features'">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_features_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.columns') }}</label>
                    <select x-model="{{ $b }}.columns" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        @foreach(config('cms.options.columns') as $col)
                            <option value="{{ $col['value'] }}">{{ __($col['label']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Features Items -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.block_form.feature_cards') }}</label>
                    <button type="button" @click="ensureArray({{ $b }}, 'items'); {{ $b }}.items.push({ icon: 'fa-solid fa-bolt', title: 'Nueva característica', desc: 'Descripción del punto técnico...' }); syncBlocksToLocale();" class="text-[#00FF99] hover:underline text-[11px] font-bold">{{ __('admin.block_form.add_card') }}</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="(fItem, fIdx) in ({{ $b }}.items || [])" :key="fIdx">
                        <div class="bg-[#181818] p-3 rounded-lg border border-gray-800 space-y-2 relative group">
                            <button type="button" @click="{{ $b }}.items.splice(fIdx, 1); syncBlocksToLocale();" class="absolute top-2 right-2 text-red-400 hover:text-red-300 text-sm">&times;</button>
                            <div class="flex items-center gap-2 pr-6">
                                <button type="button"
                                        @click="openIconPicker(fItem, 'icon')"
                                        class="w-9 h-9 rounded-lg bg-[#121212] border border-gray-700 hover:border-[#00FF99] flex items-center justify-center text-sm text-[#00FF99] shrink-0 overflow-hidden transition-colors shadow-sm"
                                        title="{{ __('admin.block_form.pick_icon') }}">
                                    <template x-if="isBlockIcon(fItem.icon)">
                                        <i :class="blockIconClass(fItem.icon)"></i>
                                    </template>
                                    <template x-if="fItem.icon && !isBlockIcon(fItem.icon)">
                                        <span x-text="fItem.icon" class="text-base leading-none"></span>
                                    </template>
                                    <template x-if="!fItem.icon">
                                        <i class="fa-solid fa-icons text-gray-500 text-xs"></i>
                                    </template>
                                </button>
                                <input type="text" x-model="fItem.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.title') }}" class="flex-1 min-w-0 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white font-bold text-xs">
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-code text-gray-600 text-[10px] shrink-0" title="{{ __('admin.block_form.icon_class') }}"></i>
                                <input type="text" x-model="fItem.icon" @input="syncBlocksToLocale()" class="flex-1 min-w-0 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-left text-[11px] font-mono truncate" placeholder="fa-solid fa-bolt">
                            </div>
                            <textarea x-model="fItem.desc" @input="syncBlocksToLocale()" rows="2" placeholder="{{ __('admin.block_form.ph_brief') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-300 text-[11px]"></textarea>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- 4. KPI / STATS BLOCK -->
    <template x-if="{{ $b }}.type === 'kpi' || {{ $b }}.type === 'widget_stats'">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_kpi_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.columns') }}</label>
                    <select x-model="{{ $b }}.columns" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        <option value="2">{{ __('admin.block_form.col_2') }}</option>
                        <option value="3">{{ __('admin.block_form.col_3') }}</option>
                        <option value="4">{{ __('admin.block_form.col_4_recommended') }}</option>
                    </select>
                </div>
            </div>

            <!-- KPI Counters -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.block_form.kpi_indicators') }}</label>
                    <button type="button" @click="ensureArray({{ $b }}, 'items'); {{ $b }}.items.push({ value: '99.9%', label: 'Disponibilidad SLA', icon: 'fa-solid fa-chart-line' }); syncBlocksToLocale();" class="text-[#00FF99] hover:underline text-[11px] font-bold">{{ __('admin.block_form.add_metric') }}</button>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-3">
                    <template x-for="(kpiItem, kpiIdx) in ({{ $b }}.items || [])" :key="kpiIdx">
                        <div class="bg-[#181818] p-3 rounded-lg border border-gray-800 space-y-2 relative">
                            <button type="button" @click="{{ $b }}.items.splice(kpiIdx, 1); syncBlocksToLocale();" class="absolute top-1.5 right-1.5 text-red-400 hover:text-red-300 text-xs">&times;</button>
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        @click="openIconPicker(kpiItem, 'icon')"
                                        class="w-8 h-8 rounded bg-[#121212] border border-gray-700 hover:border-[#00FF99] flex items-center justify-center text-xs text-[#00FF99] shrink-0 overflow-hidden transition-colors shadow-sm"
                                        title="{{ __('admin.block_form.pick_icon') }}">
                                    <template x-if="isBlockIcon(kpiItem.icon)">
                                        <i :class="blockIconClass(kpiItem.icon)"></i>
                                    </template>
                                    <template x-if="kpiItem.icon && !isBlockIcon(kpiItem.icon)">
                                        <span x-text="kpiItem.icon" class="text-sm leading-none"></span>
                                    </template>
                                    <template x-if="!kpiItem.icon">
                                        <i class="fa-solid fa-icons text-gray-500 text-[10px]"></i>
                                    </template>
                                </button>
                                <input type="text" x-model="kpiItem.value" @input="syncBlocksToLocale()" placeholder="99.9%" class="flex-1 min-w-0 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-[#00FF99] font-mono font-bold text-xs">
                            </div>
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-code text-gray-600 text-[10px] shrink-0" title="{{ __('admin.block_form.icon_class') }}"></i>
                                <input type="text" x-model="kpiItem.icon" @input="syncBlocksToLocale()" class="flex-1 min-w-0 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-left text-[11px] font-mono truncate" placeholder="fa-solid fa-chart-line">
                            </div>
                            <input type="text" x-model="kpiItem.label" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_label') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-300 text-[11px]">
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- 5. PRICING BLOCK -->
    <template x-if="{{ $b }}.type === 'pricing'">
        <div class="space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_pricing_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.subtitle') }}</label>
                    <input type="text" x-model="{{ $b }}.content" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_pricing_sub') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-gray-300 text-xs">
                </div>
            </div>

            <!-- Pricing Tiers -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.block_form.pricing_plans') }}</label>
                    <button type="button" @click="ensureArray({{ $b }}, 'items'); {{ $b }}.items.push({ name: 'Plan Estándar', price: '49€', period: '/mes', badge: 'Popular', features: 'Hasta 10 usuarios\nSoporte prioritario 24/7\nCopias automáticas', cta_label: 'Empezar ahora', cta_url: '/contacto', is_featured: false }); syncBlocksToLocale();" class="text-[#00FF99] hover:underline text-[11px] font-bold">{{ __('admin.block_form.add_plan') }}</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="(tier, tIdx) in ({{ $b }}.items || [])" :key="tIdx">
                        <div class="bg-[#181818] p-3.5 rounded-lg border border-gray-800 space-y-2.5 relative">
                            <button type="button" @click="{{ $b }}.items.splice(tIdx, 1); syncBlocksToLocale();" class="absolute top-2 right-2 text-red-400 hover:text-red-300 text-sm">&times;</button>
                            
                            <div class="grid grid-cols-2 gap-2 pr-6">
                                <div>
                                    <label class="block text-gray-500 text-[9px] uppercase font-bold">{{ __('admin.block_form.name') }}</label>
                                    <input type="text" x-model="tier.name" @input="syncBlocksToLocale()" placeholder="Pro" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white font-bold text-xs">
                                </div>
                                <div>
                                    <label class="block text-gray-500 text-[9px] uppercase font-bold">{{ __('admin.block_form.badge') }}</label>
                                    <input type="text" x-model="tier.badge" @input="syncBlocksToLocale()" placeholder="Recomendado" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-[#00FF99] text-xs">
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-gray-500 text-[9px] uppercase font-bold">{{ __('admin.block_form.price') }}</label>
                                    <input type="text" x-model="tier.price" @input="syncBlocksToLocale()" placeholder="29€" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white font-mono text-xs font-bold">
                                </div>
                                <div>
                                    <label class="block text-gray-500 text-[9px] uppercase font-bold">{{ __('admin.block_form.period') }}</label>
                                    <input type="text" x-model="tier.period" @input="syncBlocksToLocale()" placeholder="/mes" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-400 text-xs">
                                </div>
                            </div>

                            <div>
                                <label class="block text-gray-500 text-[9px] uppercase font-bold">{{ __('admin.block_form.features_per_line') }}</label>
                                <textarea x-model="tier.features" @input="syncBlocksToLocale()" rows="3" placeholder="{{ __('admin.block_form.ph_features') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-300 text-[11px]"></textarea>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" x-model="tier.cta_label" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_hire') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white text-xs">
                                <input type="text" x-model="tier.cta_url" @input="syncBlocksToLocale()" placeholder="/shop" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white text-xs font-mono">
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- 6. FAQ BLOCK -->
    <template x-if="{{ $b }}.type === 'faq'">
        <div class="space-y-4">
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title') }}</label>
                <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_faq_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
            </div>

            <!-- FAQ Items -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.block_form.faq_list') }}</label>
                    <button type="button" @click="ensureArray({{ $b }}, 'items'); {{ $b }}.items.push({ question: '¿Nueva pregunta?', answer: 'Respuesta detallada...' }); syncBlocksToLocale();" class="text-[#00FF99] hover:underline text-[11px] font-bold">{{ __('admin.block_form.add_question') }}</button>
                </div>

                <div class="space-y-2">
                    <template x-for="(faqItem, faqIdx) in ({{ $b }}.items || [])" :key="faqIdx">
                        <div class="bg-[#181818] p-3 rounded-lg border border-gray-800 space-y-2 relative">
                            <button type="button" @click="{{ $b }}.items.splice(faqIdx, 1); syncBlocksToLocale();" class="absolute top-2 right-2 text-red-400 hover:text-red-300 text-sm">&times;</button>
                            <input type="text" x-model="faqItem.question" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_question') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white font-bold text-xs pr-6">
                            <textarea x-model="faqItem.answer" @input="syncBlocksToLocale()" rows="2" placeholder="{{ __('admin.block_form.ph_answer') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-300 text-xs"></textarea>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- 7. TESTIMONIALS BLOCK -->
    <template x-if="{{ $b }}.type === 'testimonials'">
        <div class="space-y-4">
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title') }}</label>
                <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_testimonials_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
            </div>

            <!-- Testimonial items -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.block_form.testimonials') }}</label>
                    <button type="button" @click="ensureArray({{ $b }}, 'items'); {{ $b }}.items.push({ name: 'Laura Gómez', role: 'CTO en CyberCorp', quote: 'La velocidad de carga y modularidad han superado con creces nuestras expectativas.', rating: 5, avatar: '' }); syncBlocksToLocale();" class="text-[#00FF99] hover:underline text-[11px] font-bold">{{ __('admin.block_form.add_testimonial') }}</button>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <template x-for="(tItem, tIdx) in ({{ $b }}.items || [])" :key="tIdx">
                        <div class="bg-[#181818] p-3 rounded-lg border border-gray-800 space-y-2 relative">
                            <button type="button" @click="{{ $b }}.items.splice(tIdx, 1); syncBlocksToLocale();" class="absolute top-2 right-2 text-red-400 hover:text-red-300 text-sm">&times;</button>
                            <div class="grid grid-cols-2 gap-2 pr-6">
                                <input type="text" x-model="tItem.name" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_name') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white font-bold text-xs">
                                <input type="text" x-model="tItem.role" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_role') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-400 text-xs">
                            </div>
                            <textarea x-model="tItem.quote" @input="syncBlocksToLocale()" rows="2" placeholder="{{ __('admin.block_form.ph_quote') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-300 text-xs"></textarea>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex items-center gap-1">
                                    <span class="text-[10px] text-gray-400">{{ __('admin.block_form.stars') }}</span>
                                    <select x-model.number="tItem.rating" @change="syncBlocksToLocale()" class="bg-[#121212] border border-gray-700 rounded px-1 py-0.5 text-yellow-400 text-xs">
                                        @foreach(config('cms.options.ratings') as $rating)
                                            <option value="{{ $rating }}">{{ __('admin.block_form.rating_'.$rating) }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button type="button" @click="openMediaPickerForObject(tItem, 'avatar')" class="text-[11px] text-[#00FF99] hover:underline flex items-center gap-1">
                                    <i class="fa-solid fa-image"></i>
                                    <span>{{ __('admin.block_form.avatar') }}</span>
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- 8. CTA BLOCK -->
    <template x-if="{{ $b }}.type === 'cta'">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.cta_title') }} *</label>
                <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_cta_question') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-bold text-xs">
            </div>
            <div class="md:col-span-2">
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.value_prop') }}</label>
                <textarea x-model="{{ $b }}.content" @input="syncBlocksToLocale()" rows="2" placeholder="{{ __('admin.block_form.ph_cta_desc') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-gray-300 text-xs"></textarea>
            </div>
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.cta_button_text') }}</label>
                <input type="text" x-model="{{ $b }}.cta_text" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_contact') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            </div>
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.cta_link_label') }}</label>
                <input type="text" x-model="{{ $b }}.cta_url" @input="syncBlocksToLocale()" placeholder="/contacto" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
            </div>
        </div>
    </template>

    <!-- 9. BEFORE / AFTER BLOCK -->
    <template x-if="{{ $b }}.type === 'before_after'">
        <div class="space-y-4">
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.comparison_title') }}</label>
                <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_comparison') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-bold text-xs">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Before -->
                <div class="p-3 bg-[#181818] rounded-xl border border-gray-800 space-y-2">
                    <label class="block text-[#00FF99] text-[10px] font-bold uppercase">{{ __('admin.block_form.state_before') }}</label>
                    <input type="text" x-model="{{ $b }}.before_label" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_before') }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2.5 py-1.5 text-white text-xs">
                    <div class="flex gap-2">
                        <input type="text" x-model="{{ $b }}.image_url" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_image_before') }}" class="flex-1 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-300 font-mono text-xs">
                        <button type="button" @click="openMediaPickerForObject({{ $b }}, 'image_url')" class="px-2.5 py-1 bg-gray-800 text-[#00FF99] rounded text-xs font-bold"><i class="fa-solid fa-image"></i></button>
                    </div>
                </div>

                <!-- After -->
                <div class="p-3 bg-[#181818] rounded-xl border border-gray-800 space-y-2">
                    <label class="block text-[#00FF99] text-[10px] font-bold uppercase">{{ __('admin.block_form.state_after') }}</label>
                    <input type="text" x-model="{{ $b }}.after_label" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_after', ['app' => config('cms.name')]) }}" class="w-full bg-[#121212] border border-gray-700 rounded px-2.5 py-1.5 text-white text-xs">
                    <div class="flex gap-2">
                        <input type="text" x-model="{{ $b }}.after_image_url" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_image_after') }}" class="flex-1 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-gray-300 font-mono text-xs">
                        <button type="button" @click="openMediaPickerForObject({{ $b }}, 'after_image_url')" class="px-2.5 py-1 bg-gray-800 text-[#00FF99] rounded text-xs font-bold"><i class="fa-solid fa-image"></i></button>
                    </div>
                </div>
            </div>
        </div>
    </template>

    <!-- 10. CODE EMBED BLOCK -->
    <template x-if="{{ $b }}.type === 'code_embed'">
        <div class="space-y-3">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.code_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_api') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.language') }}</label>
                    <select x-model="{{ $b }}.language" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        @foreach(config('cms.options.code_languages') as $langValue => $langLabel)
                            <option value="{{ $langValue }}">{{ __($langLabel) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.code_content') }}</label>
                <textarea x-model="{{ $b }}.content" @input="syncBlocksToLocale()" rows="{{ $isSubBlock ? 5 : 8 }}" placeholder="<iframe ...></iframe> o script" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-[#00FF99] font-mono text-xs leading-relaxed"></textarea>
            </div>
        </div>
    </template>

    <!-- 11. ROI CALCULATOR BLOCK (Decoupled Domain Composite) -->
    <template x-if="{{ $b }}.type === 'roi'">
        <div class="space-y-4 p-4 bg-[#121212] border border-[#00FF99]/30 rounded-xl">
            <div class="flex items-center gap-2 text-[#00FF99] font-bold text-xs uppercase pb-2 border-b border-gray-800">
                <i class="fa-solid fa-chart-line"></i>
                <span>{{ __('admin.block_form.roi_title') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_roi_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-bold text-xs">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.initial_investment') }}</label>
                    <input type="number" x-model.number="{{ $b }}.initial_investment" @input="syncBlocksToLocale()" placeholder="10000" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-mono text-xs">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.annual_return') }}</label>
                    <input type="number" x-model.number="{{ $b }}.return_percentage" @input="syncBlocksToLocale()" placeholder="35" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-mono text-xs">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.roi_months') }}</label>
                    <input type="number" x-model.number="{{ $b }}.months" @input="syncBlocksToLocale()" placeholder="12" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white font-mono text-xs">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.chart_engine') }}</label>
                    <select x-model="{{ $b }}.chart_mode" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        @foreach(collect($chartTypes)->reject(fn ($ct) => ($ct['key'] ?? '') === 'roi') as $ct)
                            <option value="{{ $ct['key'] }}">{{ $ct['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.roi_note') }}</label>
                    <input type="text" x-model="{{ $b }}.content" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_roi_note') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-gray-300 text-xs">
                </div>
            </div>
        </div>
    </template>

    <!-- 12. WIDGET BLOG POSTS BLOCK -->
    <template x-if="{{ $b }}.type === 'widget_posts'">
        <div class="space-y-3 p-4 bg-[#121212] border border-gray-800 rounded-xl"
             x-on:search-select-change="if ($event.detail.field === 'category') { {{ $b }}.category = $event.detail.value; } else if ($event.detail.field === 'post_id') { {{ $b }}.post_id = $event.detail.value; } syncBlocksToLocale();">
            <div class="flex items-center gap-2 text-blue-400 font-bold text-xs uppercase pb-2 border-b border-gray-800">
                <i class="fa-solid fa-newspaper"></i>
                <span>{{ __('admin.block_form.blog_module') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.list_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_blog_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.filter_category') }}</label>
                    <x-admin.search-select
                        source="blog_categories"
                        field="category"
                        value-key="translation.slug"
                        :contextual="true"
                        placeholder="{{ __('admin.block_form.ph_all_categories') }}"
                        x-init="hydrate({{ $b }}.category || null)" />
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.highlight_article') }}</label>
                    <x-admin.search-select
                        source="blog_posts"
                        field="post_id"
                        :contextual="true"
                        placeholder="{{ __('admin.block_form.ph_none_recent') }}"
                        x-init="hydrate({{ $b }}.post_id || null)" />
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.article_limit') }}</label>
                    <input type="number" min="1" max="12" x-model.number="{{ $b }}.limit" @input="syncBlocksToLocale()" placeholder="3" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-mono">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.grid_columns') }}</label>
                    <select x-model="{{ $b }}.columns" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        @foreach(config('cms.options.columns') as $col)
                            <option value="{{ $col['value'] }}">{{ __($col['label']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </template>

    <!-- 13. WIDGET SHOP PRODUCTS BLOCK -->
    <template x-if="{{ $b }}.type === 'widget_products'">
        <div class="space-y-3 p-4 bg-[#121212] border border-gray-800 rounded-xl"
             x-on:search-select-change="if ($event.detail.field === 'category') { {{ $b }}.category = $event.detail.value; } else if ($event.detail.field === 'product_id') { {{ $b }}.product_id = $event.detail.value; } syncBlocksToLocale();">
            <div class="flex items-center gap-2 text-amber-400 font-bold text-xs uppercase pb-2 border-b border-gray-800">
                <i class="fa-solid fa-bag-shopping"></i>
                <span>{{ __('admin.block_form.shop_module') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.catalog_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_products_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.filter_shop_category') }}</label>
                    <x-admin.search-select
                        source="shop_categories"
                        field="category"
                        value-key="translation.slug"
                        :contextual="true"
                        placeholder="{{ __('admin.block_form.ph_all_categories') }}"
                        x-init="hydrate({{ $b }}.category || null)" />
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.highlight_product') }}</label>
                    <x-admin.search-select
                        source="shop_products"
                        field="product_id"
                        :contextual="true"
                        placeholder="{{ __('admin.block_form.ph_none_recent') }}"
                        x-init="hydrate({{ $b }}.product_id || null)" />
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.product_limit') }}</label>
                    <input type="number" min="1" max="12" x-model.number="{{ $b }}.limit" @input="syncBlocksToLocale()" placeholder="3" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-mono">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.grid_columns') }}</label>
                    <select x-model="{{ $b }}.columns" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        @foreach(config('cms.options.columns') as $col)
                            <option value="{{ $col['value'] }}">{{ __($col['label']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </template>

    <!-- 14. GALLERY BLOCK -->
    <template x-if="{{ $b }}.type === 'gallery'">
        <div class="space-y-3 p-4 bg-[#121212] border border-gray-800 rounded-xl"
             x-on:search-select-change="if ($event.detail.field === 'album_slug') { {{ $b }}.album_slug = $event.detail.value; } syncBlocksToLocale();">
            <div class="flex items-center gap-2 text-purple-400 font-bold text-xs uppercase pb-2 border-b border-gray-800">
                <i class="fa-solid fa-images"></i>
                <span>{{ __('admin.block_form.gallery_module') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.gallery_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_album') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.select_album') }}</label>
                    <x-admin.search-select
                        source="gallery_albums"
                        field="album_slug"
                        value-key="translation.slug"
                        :contextual="true"
                        placeholder="{{ __('admin.block_form.ph_all_galleries') }}"
                        x-init="hydrate({{ $b }}.album_slug || null)" />
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.columns') }}</label>
                    <select x-model="{{ $b }}.columns" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        @foreach(collect(config('cms.options.columns'))->filter(fn ($c) => (int) $c['value'] >= 2) as $col)
                            <option value="{{ $col['value'] }}">{{ __($col['label']) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>
    </template>

    <!-- 15. WIDGET FORM BLOCK -->
    <template x-if="{{ $b }}.type === 'widget_form'">
        <div class="space-y-3 p-4 bg-[#121212] border border-[#00FF99]/40 rounded-xl"
             x-on:search-select-change="if ($event.detail.field === 'form_slug') { {{ $b }}.form_slug = $event.detail.value; } syncBlocksToLocale();">
            <div class="flex items-center justify-between pb-2 border-b border-gray-800">
                <div class="flex items-center gap-2 text-[#00FF99] font-bold text-xs uppercase">
                    <i class="fa-solid fa-clipboard-list"></i>
                    <span>{{ __('admin.block_form.form_embed_title') }}</span>
                </div>
                <a href="{{ route('admin.forms.index') }}" target="_blank" class="text-xs text-[#00FF99] hover:underline flex items-center gap-1">
                    <span>{{ __('admin.block_form.forms_builder') }} &rarr;</span>
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.form_heading') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_form_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.select_form') }} *</label>
                    <x-admin.search-select
                        source="forms"
                        field="form_slug"
                        value-key="slug"
                        :contextual="true"
                        placeholder="{{ __('admin.block_form.ph_form') }}"
                        x-init="hydrate({{ $b }}.form_slug || null)" />
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.code_description') }}</label>
                    <input type="text" x-model="{{ $b }}.content" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_form_desc') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-gray-300 text-xs">
                </div>
            </div>
        </div>
    </template>

    <!-- 16. CHARTS BLOCK (Pure Reusable Primitives) -->
    <template x-if="{{ $b }}.type === 'charts'">
        <div class="space-y-4 p-4 bg-[#121212] border border-cyan-500/40 rounded-xl">
            <div class="flex items-center gap-2 text-cyan-400 font-bold text-xs uppercase pb-2 border-b border-gray-800">
                <i class="fa-solid fa-chart-pie"></i>
                <span>{{ __('admin.block_form.charts_title') }}</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.chart_title') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_chart_title') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.chart_type') }}</label>
                    <select x-model="{{ $b }}.chart_type" @change="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                        @foreach($chartTypes as $ct)
                            <option value="{{ $ct['key'] }}">{{ $ct['name'] }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Series Data List -->
            <div class="space-y-2">
                <div class="flex items-center justify-between">
                    <label class="text-[10px] text-gray-400 uppercase font-bold">{{ __('admin.block_form.data_points') }}</label>
                    <button type="button" @click="ensureArray({{ $b }}, 'items'); {{ $b }}.items.push({ label: 'Métrica ' + ({{ $b }}.items.length + 1), value: 50, color: '#00FF99' }); syncBlocksToLocale();" class="text-cyan-400 hover:underline text-[11px] font-bold">{{ __('admin.block_form.add_point') }}</button>
                </div>

                <div class="space-y-2">
                    <template x-for="(dataPoint, dpIdx) in ({{ $b }}.items || [])" :key="dpIdx">
                        <div class="flex items-center gap-2 bg-[#181818] p-2 rounded-lg border border-gray-800">
                            <input type="text" x-model="dataPoint.label" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_label_year') }}" class="flex-1 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-white text-xs">
                            <input type="number" x-model.number="dataPoint.value" @input="syncBlocksToLocale()" placeholder="80" class="w-24 bg-[#121212] border border-gray-700 rounded px-2 py-1 text-[#00FF99] font-mono text-xs font-bold">
                            <input type="color" x-model="dataPoint.color" @input="syncBlocksToLocale()" class="w-8 h-7 bg-transparent border-0 cursor-pointer">
                            <button type="button" @click="{{ $b }}.items.splice(dpIdx, 1); syncBlocksToLocale();" class="text-red-400 hover:text-red-300 px-2 py-1">&times;</button>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- 14. CAROUSEL BLOCK -->
    <template x-if="{{ $b }}.type === 'carousel'">
        <div class="space-y-3 p-4 bg-[#121212] border border-gray-800 rounded-xl">
            <div class="flex items-center gap-2 text-[#00FF99] font-bold text-xs uppercase pb-2 border-b border-gray-800">
                <i class="fa-solid fa-images"></i>
                <span>{{ __('admin.block_manager.carousel') }}</span>
                <span class="ml-auto normal-case font-normal text-gray-500 text-[10px]">{{ __('admin.block_manager.full_width_block_hint') }}</span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="col-span-2">
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title_optional') }}</label>
                    <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_manager.carousel') }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_manager.carousel_height') }}</label>
                    <input type="number" min="240" max="900" step="20" x-model.number="{{ $b }}.height" @input="syncBlocksToLocale()" placeholder="520" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-mono">
                </div>
                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_manager.carousel_interval') }}</label>
                    <input type="number" min="2000" max="20000" step="500" x-model.number="{{ $b }}.interval" @input="syncBlocksToLocale()" placeholder="6000" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-mono">
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-4 pt-1">
                <label class="inline-flex items-center gap-2 text-gray-300 text-xs cursor-pointer">
                    <input type="checkbox" x-model="{{ $b }}.autoplay" @change="syncBlocksToLocale()" class="rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99]">
                    <span>{{ __('admin.block_manager.carousel_autoplay') }}</span>
                </label>
                <label class="inline-flex items-center gap-2 text-gray-300 text-xs cursor-pointer">
                    <input type="checkbox" x-model="{{ $b }}.show_arrows" @change="syncBlocksToLocale()" class="rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99]">
                    <span>{{ __('admin.block_manager.carousel_arrows') }}</span>
                </label>
                <label class="inline-flex items-center gap-2 text-gray-300 text-xs cursor-pointer">
                    <input type="checkbox" x-model="{{ $b }}.show_dots" @change="syncBlocksToLocale()" class="rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99]">
                    <span>{{ __('admin.block_manager.carousel_dots') }}</span>
                </label>

                <button type="button"
                        @click="ensureArray({{ $b }}, 'slides'); {{ $b }}.slides.push({ image_url: '', title: '', subtitle: '', cta_text: '', cta_url: '' }); syncBlocksToLocale();"
                        class="ml-auto text-[#00FF99] hover:underline text-[11px] font-bold">
                    + {{ __('admin.block_manager.add_slide') }}
                </button>
            </div>

            <div class="space-y-3">
                <div class="text-[10px] text-gray-500 uppercase font-bold">{{ __('admin.block_manager.slides') }}</div>
                <template x-for="(slide, sIdx) in ({{ $b }}.slides || [])" :key="sIdx">
                    <div class="relative p-3 bg-[#181818] border border-gray-800 rounded-lg space-y-2.5">
                        <button type="button" @click="{{ $b }}.slides.splice(sIdx, 1); syncBlocksToLocale();" class="absolute top-2 right-2 text-red-400 hover:text-red-300 text-sm" title="{{ __('admin.block_manager.delete') }}">&times;</button>

                        <div class="text-[10px] text-[#00FF99] font-bold uppercase" x-text="@js(__('admin.block_manager.slides')) + ' ' + (sIdx + 1)"></div>

                        <div>
                            <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_manager.slide_image') }}</label>
                            <div class="flex gap-2">
                                <input type="text" x-model="slide.image_url" @input="syncBlocksToLocale()" placeholder="https://..." class="flex-1 bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-mono">
                                <button type="button" @click="openMediaPickerForObject(slide, 'image_url')" class="px-3 py-2 bg-gray-800 hover:bg-gray-700 text-[#00FF99] border border-gray-700 rounded-lg font-bold text-xs" title="{{ __('admin.entity_form.select_from_manager') }}">
                                    <i class="fa-solid fa-image"></i>
                                </button>
                            </div>
                            <template x-if="slide.image_url">
                                <img :src="slide.image_url" class="mt-2 w-full h-24 object-cover rounded-lg border border-gray-800" alt="">
                            </template>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                            <div>
                                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_manager.slide_title') }}</label>
                                <input type="text" x-model="slide.title" @input="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-bold">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_manager.slide_subtitle') }}</label>
                                <input type="text" x-model="slide.subtitle" @input="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_manager.slide_cta_text') }}</label>
                                <input type="text" x-model="slide.cta_text" @input="syncBlocksToLocale()" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs">
                            </div>
                            <div>
                                <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('admin.block_manager.slide_cta_url') }}</label>
                                <input type="text" x-model="slide.cta_url" @input="syncBlocksToLocale()" placeholder="/contacto" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs font-mono">
                            </div>
                        </div>
                    </div>
                </template>

                <template x-if="!({{ $b }}.slides || []).length">
                    <button type="button"
                            @click="ensureArray({{ $b }}, 'slides'); {{ $b }}.slides.push({ image_url: '', title: '', subtitle: '', cta_text: '', cta_url: '' }); syncBlocksToLocale();"
                            class="w-full py-6 border border-dashed border-gray-800 hover:border-[#00FF99]/60 rounded-lg text-gray-500 hover:text-[#00FF99] text-xs font-mono transition-colors">
                        + {{ __('admin.block_manager.add_slide') }}
                    </button>
                </template>
            </div>
        </div>
    </template>

</div>
