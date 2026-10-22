@php($b = $blockKey)
        <div class="space-y-3" x-data="{
            format: {{ $b }}.format || 'markdown',
            viewMode: 'edit',
            wordCount: 0,
            charCount: 0,
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
            }
        }" x-init="if (!{{ $b }}.format) {{ $b }}.format = 'markdown'; updateStats(); $watch('{{ $b }}.content', () => updateStats())">
            <div>
                <label class="admin-field-label block text-[color:var(--admin-muted)] text-[10px] font-bold uppercase mb-1">{{ __('admin.block_form.section_title_optional') }}</label>
                <input type="text" x-model="{{ $b }}.title" @input="syncBlocksToLocale()" placeholder="{{ __('admin.block_form.ph_about_tech') }}" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] focus:border-[color:var(--admin-accent)] rounded-lg px-3 py-2 text-[color:var(--admin-text)] text-xs">
            </div>

            <!-- Cyber WYSIWYG Editor Container -->
            <div class="admin-rich-editor focus-within:border-[color:var(--admin-accent)] transition-colors">

                <!-- TOP CONTROL BAR -->
                <div class="admin-rich-toolbar bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)] p-2.5 flex flex-wrap items-center justify-between gap-2.5">
                    <!-- Left: Format Switcher (Markdown vs HTML) -->
                    <div class="admin-rich-control-group flex flex-wrap items-center gap-2">
                        <div class="admin-rich-control-group flex flex-wrap items-center gap-1 bg-[color:var(--admin-surface)] p-1 rounded-lg border border-[color:var(--admin-border)]">
                            <span class="text-[10px] text-[color:var(--admin-muted)] font-bold uppercase px-1 font-mono">{{ __('admin.block_form.syntax') }}</span>
                            <button type="button" @click="setFormat('markdown')"
                                    :class="format === 'markdown' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-2 py-0.5 rounded text-[11px] font-mono transition-all flex items-center gap-1">
                                <i class="fa-solid fa-bolt"></i>
                                <span>{{ __('admin.block_form.markdown') }}</span>
                            </button>
                            <button type="button" @click="setFormat('html')"
                                    :class="format === 'html' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-2 py-0.5 rounded text-[11px] font-mono transition-all flex items-center gap-1">
                                <i class="fa-solid fa-code"></i>
                                <span>{{ __('admin.block_form.html_pure') }}</span>
                            </button>
                        </div>

                        <!-- Stats Badge -->
                        <span class="text-[10px] text-[color:var(--admin-muted)] font-mono hidden sm:inline"
                              x-text="wordCount + ' palabras · ' + charCount + ' caracteres'"></span>
                    </div>

                    <!-- Right: View Mode (Edit / Split / Preview) & Multimedia -->
                    <div class="admin-rich-control-group flex flex-wrap items-center gap-2">
                        <div class="admin-rich-control-group flex flex-wrap items-center gap-1 bg-[color:var(--admin-surface)] p-0.5 rounded-lg border border-[color:var(--admin-border)]">
                            <button type="button" @click="viewMode = 'edit'"
                                    :class="viewMode === 'edit' ? 'bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] font-bold border border-[color:var(--admin-accent)]/40' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-2.5 py-1 rounded text-[11px] transition-all flex items-center gap-1">
                                <i class="fa-solid fa-pen"></i>
                                <span>{{ __('admin.block_form.write') }}</span>
                            </button>
                            <button type="button" @click="viewMode = 'split'"
                                    :class="viewMode === 'split' ? 'bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] font-bold border border-[color:var(--admin-accent)]/40' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-2.5 py-1 rounded text-[11px] transition-all flex items-center gap-1"
                                    title="Vista dividida: editor y previsualización en vivo simultánea">
                                <i class="fa-solid fa-columns"></i>
                                <span>{{ __('admin.block_form.split') }}</span>
                            </button>
                            <button type="button" @click="viewMode = 'preview'"
                                    :class="viewMode === 'preview' ? 'bg-[color:var(--admin-surface)] text-[color:var(--admin-accent)] font-bold border border-[color:var(--admin-accent)]/40' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                                    class="px-2.5 py-1 rounded text-[11px] transition-all flex items-center gap-1">
                                <i class="fa-solid fa-eye"></i>
                                <span>{{ __('admin.block_form.preview') }}</span>
                            </button>
                        </div>

                        <!-- Multimedia Picker Button -->
                        <button type="button" @click="openMediaPickerForMarkdown({{ $b }})"
                                class="px-2.5 py-1 bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/40 text-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent)] hover:text-black rounded text-[11px] font-bold flex items-center gap-1 transition-colors">
                            <i class="fa-solid fa-image"></i>
                            <span class="hidden sm:inline">{{ __('admin.block_form.add_multimedia') }}</span>
                        </button>
                    </div>
                </div>

                <!-- CONTEXTUAL TOOLBAR (Shown in Edit and Split views) -->
                <div x-show="viewMode !== 'preview'" class="admin-rich-toolbar bg-[color:var(--admin-surface)] border-b border-[color:var(--admin-border)]/80 px-2.5 py-1.5 flex flex-wrap items-center justify-between gap-1.5">
                    <!-- Markdown Toolbar Buttons -->
                    <div x-show="format === 'markdown'" class="flex items-center flex-wrap gap-1">
                        <button type="button" @click="appendSnippet({{ $b }}, '# ', '')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded font-bold text-[11px]" title="Encabezado 1">H1</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '## ', '')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded font-bold text-[11px]" title="Encabezado 2">H2</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '### ', '')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded font-bold text-[11px]" title="Encabezado 3">H3</button>
                        <span class="text-[color:var(--admin-muted)]">|</span>
                        <button type="button" @click="appendSnippet({{ $b }}, '**', '**')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded font-bold text-[11px]" title="Negrita">**B**</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '*', '*')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded italic text-[11px]" title="Cursiva">*I*</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '~~', '~~')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded line-through text-[11px]" title="Tachado">~S~</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '`', '`')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] rounded font-mono text-[11px]" title="Código en línea">&lt;/&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n```bash\n', '\n```\n')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] rounded font-mono text-[11px]" title="Bloque de código">```</button>
                        <span class="text-[color:var(--admin-muted)]">|</span>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n> ', '\n')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]" title="Cita">&ldquo; {{ __('admin.block_form.quote_short') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n- ', '')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]" title="Lista">• {{ __('admin.wysiwyg.list_label') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n1. ', '')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]" title="Lista numerada">1. {{ __('admin.wysiwyg.list_label') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '\n| Encabezado 1 | Encabezado 2 |\n|---|---|\n| Celda 1 | Celda 2 |\n')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]" title="Tabla Markdown">▦ {{ __('admin.block_form.table') }}</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '[Texto enlace](', ')')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]" title="Enlace"><i class="fa-solid fa-link"></i></button>
                    </div>

                    <!-- HTML Toolbar Buttons -->
                    <div x-show="format === 'html'" class="flex items-center flex-wrap gap-1">
                        <button type="button" @click="appendSnippet({{ $b }}, '<p>', '</p>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] rounded font-mono text-[11px]">&lt;p&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<div class=\'my-4\'>\n  ', '\n</div>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] rounded font-mono text-[11px]">&lt;div&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<h2>', '</h2>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] rounded font-mono text-[11px]">&lt;h2&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<h3>', '</h3>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-accent)] rounded font-mono text-[11px]">&lt;h3&gt;</button>
                        <span class="text-[color:var(--admin-muted)]">|</span>
                        <button type="button" @click="appendSnippet({{ $b }}, '<strong>', '</strong>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded font-bold text-[11px]">&lt;b&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<em>', '</em>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded italic text-[11px]">&lt;i&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<ul class=\'list-disc pl-5 my-2\'>\n  <li>', '</li>\n</ul>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]">&lt;ul&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<li>', '</li>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]">&lt;li&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<a href=\'https://...\' class=\'text-[#00FF99] underline\'>', '</a>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]">&lt;a&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<div class=\'overflow-x-auto my-3\'><table class=\'w-full border border-gray-800\'>\n  <thead><tr><th class=\'p-2 text-left\'>Col 1</th></tr></thead>\n  <tbody><tr><td class=\'p-2 border-t border-gray-800\'>Val 1</td></tr></tbody>\n</table></div>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]">&lt;table&gt;</button>
                        <button type="button" @click="appendSnippet({{ $b }}, '<iframe src=\'https://...\' class=\'w-full h-64 rounded-lg border border-gray-800\'>', '</iframe>')" class="px-1.5 py-0.5 bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] rounded text-[11px]">&lt;iframe&gt;</button>
                        <span class="text-[color:var(--admin-muted)]">|</span>
                        <button type="button" @click="formatHtmlCode()" class="px-2 py-0.5 bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-control-ring)] border border-[color:var(--admin-border)] text-[color:var(--admin-accent)] rounded font-mono text-[11px] flex items-center gap-1" title="Formatear e indentar código HTML"><i class="fa-solid fa-wand-magic-sparkles"></i> {{ __('admin.block_form.indent_html') }}</button>
                    </div>
                </div>

                <!-- 1. EDIT VIEW (Full-width Textarea) -->
                <div x-show="viewMode === 'edit'" class="admin-rich-pane p-3">
                    <textarea x-model="{{ $b }}.content"
                              @input="syncBlocksToLocale()"
                              rows="{{ $isSubBlock ? 6 : 9 }}"
                              :placeholder="format === 'markdown' ? 'Redacta tu contenido en Markdown con soporte completo para HTML incrustado...' : 'Escribe tu código HTML estructurado (etiquetas, estilos, tablas, componentes)...'"
                              class="w-full bg-transparent border-0 focus:ring-0 text-[color:var(--admin-text)] font-mono text-xs leading-relaxed placeholder-[color:var(--admin-muted)] resize-y outline-none"></textarea>
                </div>

                <!-- 2. SPLIT VIEW (Side-by-side Editor & Live Preview) -->
                <div x-show="viewMode === 'split'" class="admin-rich-pane p-3">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
                        <!-- Left: Editor Textarea -->
                        <div class="border border-[color:var(--admin-border)] rounded-lg p-2.5 bg-[color:var(--admin-surface)]/40">
                            <div class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold mb-1.5 flex items-center justify-between">
                                <span x-text="'Editor: ' + (format === 'markdown' ? 'Markdown' : 'HTML')"></span>
                                <span class="text-[color:var(--admin-muted)]">{{ __('admin.block_form.realtime') }}</span>
                            </div>
                            <textarea x-model="{{ $b }}.content"
                                      @input="syncBlocksToLocale()"
                                      rows="{{ $isSubBlock ? 8 : 12 }}"
                                      :placeholder="format === 'markdown' ? 'Escribe Markdown aquí...' : 'Escribe HTML aquí...'"
                                      class="w-full bg-transparent border-0 focus:ring-0 text-[color:var(--admin-text)] font-mono text-xs leading-relaxed placeholder-[color:var(--admin-muted)] resize-y outline-none"></textarea>
                        </div>

                        <!-- Right: Real-time Live Preview -->
                        <div class="border border-[color:var(--admin-border)] rounded-lg p-3 bg-[color:var(--admin-surface)] min-h-[220px] max-h-[400px] overflow-y-auto sidebar-scroll">
                            <div class="text-[10px] text-[color:var(--admin-accent)] uppercase font-bold mb-2 pb-1 border-b border-[color:var(--admin-border)] flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-[color:var(--admin-accent)] animate-pulse"></span>
                                    <span>{{ __('admin.block_form.live_preview') }}</span>
                                </span>
                                <span class="text-[color:var(--admin-muted)] text-[9px] font-mono">{{ __('admin.block_form.instant_render') }}</span>
                            </div>
                            <div class="prose admin-rich-preview max-w-none text-xs leading-relaxed text-[color:var(--admin-text)]"
                                 x-html="renderContentPreview({{ $b }}.content, format)"></div>
                        </div>
                    </div>
                </div>
                <div x-show="viewMode === 'preview'" class="admin-rich-pane p-3">
                    <div class="prose admin-rich-preview max-w-none text-xs leading-relaxed text-[color:var(--admin-text)]"
                         x-html="renderContentPreview({{ $b }}.content, format)"></div>
                </div>
            </div>
        </div>
