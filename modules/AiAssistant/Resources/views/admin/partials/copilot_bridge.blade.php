{{-- Contextual AI Copilot: acts on the element that invokes it (never on the whole page) --}}
@php
    // The assistant is a single UI; it resolves the entity it was invoked from
    // (page/post/product/gallery/form) from the current view via the core registry.
    $aiEntity = app(\Modules\AiAssistant\Services\AiTaxonomyService::class)
        ->entityForView(request()->route()?->getName());

    // The workbench and the create-form assistant resume their own tasks; the
    // contextual modal must not claim their deep links.
    $aiRouteName = request()->route()?->getName();
    $aiHandlesOwnResume = $aiRouteName === 'admin.ai_assistant.workbench'
        || ($aiEntity !== null && $aiRouteName === $aiEntity->createView);

    // Reactive catalog: every ACTIVE block, straight from the registry.
    $aiBlocks = app(\Modules\AiAssistant\Services\BlockIntelligenceService::class)->getBlockOptions();
@endphp
<div x-data="aiCopilotBridge({
        entity: @js($aiEntity?->type),
        label: @js($aiEntity?->label),
        fields: @js($aiEntity?->fields ?? []),
        sectionable: @js($aiEntity?->sectionable ?? false),
        handlesOwnResume: @js($aiHandlesOwnResume),
        blocks: @js($aiBlocks)
     })"
     x-init="initBridge()"
     class="relative font-mono text-xs admin-copilot-root">

    <!-- Docked trigger attached to the focused field -->
    <div x-show="showTrigger && activeTarget"
         x-transition:enter="transition ease-out duration-150"
         x-transition:enter-start="opacity-0 scale-75"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-100"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-75"
         :style="triggerStyle"
         class="fixed z-40 pointer-events-auto group"
         style="display: none;">
        <button type="button"
                @click="openCopilotModal()"
                :class="isTyping ? 'opacity-20 hover:opacity-100' : 'opacity-45 hover:opacity-100'"
                class="w-6 h-6 rounded-md bg-[#181818]/90 hover:bg-purple-600 border border-purple-500/40 text-purple-300 hover:text-white shadow-sm copilot-attach-btn flex items-center justify-center transition-all cursor-pointer"
                title="{{ __('admin.ai.open_tooltip') }}">
            <i class="fa-solid fa-wand-magic-sparkles text-[10px]"></i>
        </button>

        <span class="pointer-events-none absolute bottom-full right-0 mb-1.5 hidden group-hover:flex flex-col items-end z-50 transition-opacity">
            <span class="px-2 py-0.5 text-[9px] font-bold rounded shadow-xl whitespace-nowrap bg-[#181818] border border-gray-700 text-gray-200 font-mono admin-tooltip-content">
                Copiloto IA (Alt+A)
            </span>
            <span class="w-1.5 h-1.5 rotate-45 mr-2 -mt-0.5 bg-[#181818] border-r border-b border-gray-700 admin-tooltip-arrow"></span>
        </span>
    </div>

    <!-- Floating quick launch -->
    <div class="fixed bottom-6 right-6 z-40">
        <button type="button"
                @click="openCopilotModal()"
                class="w-10 h-10 rounded-full bg-gradient-to-tr from-purple-700 to-indigo-600 hover:from-purple-600 hover:to-indigo-500 shadow-[0_4px_20px_rgba(147,51,234,0.4)] copilot-floating-btn text-white flex items-center justify-center text-sm transition-all transform hover:scale-105"
                title="{{ __('admin.ai.open_tooltip_short') }}">
            <i class="fa-solid fa-wand-magic-sparkles"></i>
        </button>
    </div>

    <!-- Contextual modal -->
    <div x-show="openModal"
         @click.self="closeModal()"
         @keydown.escape.window="closeModal()"
         x-transition
         x-cloak
         class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         style="display: none;">

        <div class="bg-[#141414] border border-purple-500/40 text-white font-mono shadow-[0_0_50px_rgba(0,0,0,0.9)] copilot-modal-card w-full max-w-2xl rounded-2xl p-6 space-y-4 max-h-[92vh] flex flex-col"
             @click.stop>

            <!-- Header -->
            <div class="flex items-center justify-between pb-3 border-b border-gray-800 admin-card-header">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-purple-500/15 border border-purple-500/40 text-purple-400 flex items-center justify-center text-base admin-icon-box">
                        <i class="fa-solid" :class="mode === 'block' ? 'fa-cubes' : 'fa-wand-magic-sparkles'"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-sm font-bold text-white admin-modal-title" x-text="modalTitle()"></h3>
                            <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30 text-[9px] font-bold admin-badge-role"
                                  x-text="mode === 'block' ? @js(__('admin.ai.block')) : (textAction === 'draft' ? @js(__('admin.ai.write')) : @js(__('admin.ai.improve')))"></span>
                            <span x-show="contextLabel" class="text-[10px] text-gray-400 font-normal truncate max-w-[200px] admin-text-muted" x-text="contextLabel"></span>
                        </div>
                        <p class="text-[11px] text-gray-400 mt-0.5 admin-text-muted" x-text="modalSubtitle()"></p>
                    </div>
                </div>
                <button type="button" @click="closeModal()" class="text-gray-400 hover:text-white admin-btn-close text-xl p-1">&times;</button>
            </div>

            <!-- Mode Switcher Tabs -->
            <div class="flex items-center gap-1 p-1 bg-[#1A1A1A] rounded-xl border border-gray-800 text-xs shrink-0">
                <button type="button" @click="switchMode('text')"
                        :class="mode === 'text' ? 'bg-purple-600 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white'"
                        class="flex-1 py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-align-left text-[11px]"></i>
                    <span>Texto</span>
                </button>
                <button type="button" @click="switchMode('block')"
                        :class="mode === 'block' ? 'bg-purple-600 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white'"
                        class="flex-1 py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-cubes text-[11px]"></i>
                    <span>Bloques</span>
                </button>
                <button type="button" @click="switchMode('image')"
                        :class="mode === 'image' ? 'bg-emerald-600 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white'"
                        class="flex-1 py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-image text-[11px]"></i>
                    <span>Imagen IA</span>
                </button>
                <button type="button" @click="switchMode('video')"
                        :class="mode === 'video' ? 'bg-purple-700 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white'"
                        class="flex-1 py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-video text-[11px]"></i>
                    <span>Vídeo IA</span>
                </button>
                <button type="button" @click="switchMode('seo')"
                        :class="mode === 'seo' ? 'bg-blue-600 text-white font-bold shadow-sm' : 'text-gray-400 hover:text-white'"
                        class="flex-1 py-1.5 px-2 rounded-lg transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-magnifying-glass text-[11px]"></i>
                    <span>SEO</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto space-y-3 pr-1">
                <!-- Block type selector (block mode only) — fed by the active block registry -->
                <div x-show="mode === 'block'" class="space-y-1">
                    <label class="block text-xs font-semibold text-gray-300">{{ __('admin.ai.block_type') }}</label>
                    <select x-model="blockType" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-xl px-3 py-2 text-xs text-white focus:border-purple-500 focus:outline-none">
                        <option value="auto">{{ __('admin.ai.block_auto') }}</option>
                        @foreach($aiBlocks as $aiBlock)
                            <option value="{{ $aiBlock['type'] }}">{{ $aiBlock['name'] }}</option>
                        @endforeach
                    </select>
                    <p x-show="selectedBlockDescription()" class="text-[10px] text-gray-500" x-text="selectedBlockDescription()"></p>
                </div>

                <!-- Prompt -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-semibold text-gray-300">
                            <span x-show="mode === 'block'">{{ __('admin.ai.block_instruction') }}</span>
                            <span x-show="mode === 'text'" x-text="textAction === 'draft' ? @js(__('admin.ai.prompt_draft')) : @js(__('admin.ai.prompt_improve'))"></span>
                            <span x-show="mode === 'image'">Prompt Visual para Imagen</span>
                            <span x-show="mode === 'video'">Prompt Visual para Vídeo Cinemático</span>
                            <span x-show="mode === 'seo'">{{ __('admin.ai.prompt_seo') }}</span>
                        </label>
                        <button type="button"
                                x-show="mode === 'image' || mode === 'video'"
                                @click="inferPromptFromForm()"
                                class="text-[10px] text-purple-400 hover:text-purple-300 flex items-center gap-1 underline transition-colors">
                            <i class="fa-solid fa-wand-magic-sparkles text-[9px]"></i>
                            <span>Inspirar del contenido</span>
                        </button>
                    </div>
                    <textarea x-model="promptText"
                              rows="4"
                              :placeholder="getPlaceholder()"
                              class="w-full bg-[#1A1A1A] border border-gray-700 rounded-xl p-3 text-xs text-white focus:border-purple-500 focus:outline-none resize-none placeholder-gray-600 leading-relaxed"></textarea>
                </div>

                <!-- Image controls -->
                <div x-show="mode === 'image'" class="flex items-center gap-3">
                    <div class="flex-1">
                        <label class="block text-[10px] text-gray-400 uppercase font-bold mb-1">Formato / Relación de Aspecto</label>
                        <select x-model="imageAspectRatio" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-emerald-500 focus:outline-none">
                            <option value="16:9">16:9 Panorámica (Web / Hero)</option>
                            <option value="1:1">1:1 Cuadrada (Post / Tarjeta)</option>
                            <option value="9:16">9:16 Vertical (Stories / Mobile)</option>
                            <option value="4:3">4:3 Estándar</option>
                        </select>
                    </div>
                </div>

                <!-- Video controls -->
                <div x-show="mode === 'video'" class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase font-bold mb-1">Formato</label>
                        <select x-model="videoAspectRatio" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-purple-500 focus:outline-none">
                            <option value="16:9">16:9 Panorámica</option>
                            <option value="9:16">9:16 Vertical</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-[10px] text-gray-400 uppercase font-bold mb-1">Duración</label>
                        <select x-model="videoDuration" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-2.5 py-1.5 text-xs text-white focus:border-purple-500 focus:outline-none">
                            <option value="5">5 Segundos</option>
                            <option value="10">10 Segundos</option>
                        </select>
                    </div>
                </div>

                <button type="button"
                        @click="generateContent()"
                        :disabled="isGenerating || ((mode === 'text' && textAction === 'draft' || mode === 'image' || mode === 'video') && !promptText.trim())"
                        class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-bold transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid" :class="isGenerating ? 'fa-spinner fa-spin' : (mode === 'image' ? 'fa-image' : (mode === 'video' ? 'fa-video' : 'fa-wand-magic-sparkles'))"></i>
                    <span x-text="isGenerating ? (progressNote || @js(__('admin.ai.generating'))) : @js(__('admin.ai.generate'))"></span>
                </button>

                <!-- Error -->
                <div x-show="errorMessage" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs" x-text="errorMessage"></div>

                <!-- Result for Image -->
                <template x-if="mode === 'image' && generatedResult && generatedResult.url">
                    <div class="space-y-3 p-3 rounded-xl bg-[#1A1A1A] border border-emerald-500/30">
                        <div class="relative rounded-lg overflow-hidden border border-gray-800 bg-black/60 flex items-center justify-center max-h-64">
                            <img :src="generatedResult.url" :alt="generatedResult.title || 'Generada'" class="max-h-64 w-auto object-contain">
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-gray-400">
                            <span class="font-mono text-emerald-400 font-bold uppercase" x-text="generatedResult.provider"></span>
                            <span x-text="generatedResult.dimensions ? (generatedResult.dimensions[0] + ' × ' + generatedResult.dimensions[1] + ' px') : ''"></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" x-show="mediaTarget || mediaInputTarget" @click="applyMediaTarget()"
                                    class="w-full py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold">
                                {{ __('admin.ai.media_apply') }}
                            </button>
                            <button type="button" @click="insertImageBlock(generatedResult.url, promptText)"
                                    x-show="entitySectionable && !mediaTarget && !mediaInputTarget"
                                    class="flex-1 py-2 px-3 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-cube"></i>
                                <span>Insertar como Bloque Imagen</span>
                            </button>
                            <button type="button" @click="setAsFeaturedImage(generatedResult.url)"
                                    x-show="!mediaTarget && !mediaInputTarget && hasFeaturedTarget()"
                                    class="flex-1 py-2 px-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-star"></i>
                                <span>Imagen Destacada</span>
                            </button>
                            <button type="button" @click="copyToClipboard(generatedResult.url)"
                                    class="py-2 px-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white text-xs font-bold transition-all"
                                    title="Copiar URL">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Result for Video -->
                <template x-if="mode === 'video' && generatedResult && generatedResult.url">
                    <div class="space-y-3 p-3 rounded-xl bg-[#1A1A1A] border border-purple-500/30">
                        <div class="relative rounded-lg overflow-hidden border border-gray-800 bg-black/60 flex items-center justify-center max-h-64">
                            <video :src="generatedResult.url" controls class="max-h-64 w-full object-contain"></video>
                        </div>
                        <div class="flex items-center justify-between text-[11px] text-gray-400">
                            <span class="font-mono text-purple-400 font-bold uppercase" x-text="generatedResult.provider"></span>
                            <span x-text="generatedResult.duration ? (generatedResult.duration + ' segundos') : ''"></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button type="button" x-show="mediaTarget || mediaInputTarget" @click="applyMediaTarget()"
                                    class="w-full py-2 px-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold">
                                {{ __('admin.ai.media_apply') }}
                            </button>
                            <button type="button" @click="insertVideoBlock(generatedResult.url, promptText)"
                                    x-show="entitySectionable && !mediaTarget && !mediaInputTarget"
                                    class="flex-1 py-2 px-3 rounded-xl bg-purple-600 hover:bg-purple-500 text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-cube"></i>
                                <span>Insertar como Bloque Vídeo</span>
                            </button>
                            <button type="button" @click="insertHeroVideo(generatedResult.url, promptText)"
                                    class="flex-1 py-2 px-3 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                                <i class="fa-solid fa-film"></i>
                                <span>Usar en Bloque Hero</span>
                            </button>
                            <button type="button" @click="copyToClipboard(generatedResult.url)"
                                    class="py-2 px-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white text-xs font-bold transition-all"
                                    title="Copiar URL">
                                <i class="fa-solid fa-copy"></i>
                            </button>
                        </div>
                    </div>
                </template>

                <!-- Result for Text, Block, SEO -->
                <template x-if="generatedResult && mode !== 'image' && mode !== 'video'">
                    <div class="space-y-3">
                        <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400 flex items-center justify-between">
                            <span x-show="antiAiScore !== null">{{ __('admin.ai.anti_ai_quality') }} <strong x-text="antiAiScore"></strong>/100</span>
                            <span class="text-[11px] text-gray-400" x-text="resultSummary()"></span>
                        </div>
                        <div class="bg-[#1A1A1A] p-3 rounded-xl border border-gray-800 text-xs text-gray-200 leading-relaxed whitespace-pre-line max-h-56 overflow-y-auto"
                             x-text="getFormattedResultText()"></div>

                        <div class="flex items-center gap-2">
                            <template x-if="mode === 'block'">
                                <div class="flex-1 flex items-center gap-2">
                                    <button type="button" @click="applyBlocks('replace')"
                                            class="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all">
                                        {{ __('admin.ai.replace_blocks') }}
                                    </button>
                                    <button type="button" @click="applyBlocks('append')"
                                            class="flex-1 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold transition-all">
                                        {{ __('admin.ai.append_blocks') }}
                                    </button>
                                </div>
                            </template>
                            <template x-if="mode === 'text'">
                                <div class="flex-1 space-y-2">
                                    <div x-show="!hasTarget()" class="text-[10px] text-amber-300 flex items-center gap-1">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        <span>{{ __('admin.ai.no_target_hint') }}</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button type="button" @click="applyResult('replace')" :disabled="!hasTarget()"
                                                class="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-40 text-white text-xs font-bold transition-all">
                                            {{ __('admin.ai.replace') }}
                                        </button>
                                        <button type="button" @click="applyResult('append')" :disabled="!hasTarget()"
                                                class="flex-1 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 disabled:opacity-40 text-white text-xs font-bold transition-all">
                                            {{ __('admin.ai.append') }}
                                        </button>
                                    </div>
                                </div>
                            </template>
                            <template x-if="mode === 'seo'">
                                <button type="button" @click="applySeo()"
                                        class="flex-1 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all flex items-center justify-center gap-1.5">
                                    <i class="fa-solid fa-magnifying-glass"></i> {{ __('admin.ai.apply_seo') }}
                                </button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function aiCopilotBridge(config = {}) {
    return {
        // Entity context (resolved from the current view) — the modal knows
        // what it is editing and how to behave.
        entity: config.entity || null,
        entityLabel: config.label || null,
        entityFields: config.fields || {},
        entitySectionable: !!config.sectionable,
        handlesOwnResume: !!config.handlesOwnResume,
        availableBlocks: config.blocks || [],

        openModal: false,
        showTrigger: false,
        triggerStyle: '',
        activeTarget: null,
        lastTarget: null,
        contextLabel: '',
        currentLocale: null,
        mode: 'text',
        imageAspectRatio: '16:9',
        videoAspectRatio: '16:9',
        videoDuration: 5,
        mediaTarget: null,
        mediaInputTarget: null,
        textAction: 'draft',
        blockType: 'auto',
        promptText: '',
        isGenerating: false,
        progressNote: '',
        errorMessage: null,
        generatedResult: null,
        antiAiScore: null,
        isTyping: false,
        typingTimeout: null,

        initBridge() {
            window.addEventListener('keydown', (e) => {
                if ((e.altKey && (e.key === 'a' || e.key === 'A')) || (e.ctrlKey && e.shiftKey && (e.key === 'a' || e.key === 'A'))) {
                    e.preventDefault();
                    this.openCopilotModal();
                }
            });

            document.addEventListener('focusin', (e) => {
                if (this.isAssistableElement(e.target)) {
                    this.attachTriggerTo(e.target);
                }
            });

            document.addEventListener('input', (e) => {
                if (this.activeTarget && e.target === this.activeTarget) {
                    this.isTyping = true;
                    if (this.typingTimeout) clearTimeout(this.typingTimeout);
                    this.typingTimeout = setTimeout(() => { this.isTyping = false; }, 1200);
                }
            });

            document.addEventListener('focusout', () => {
                setTimeout(() => {
                    if (!this.openModal && document.activeElement !== this.activeTarget) {
                        this.showTrigger = false;
                    }
                }, 200);
            });

            window.addEventListener('scroll', () => {
                if (this.activeTarget && this.showTrigger) this.updateTriggerPosition(this.activeTarget);
            }, { passive: true });

            window.addEventListener('resize', () => {
                if (this.activeTarget && this.showTrigger) this.updateTriggerPosition(this.activeTarget);
            }, { passive: true });

            // Opened from the block manager
            window.addEventListener('cms-open-ai-blocks', (e) => {
                this.openBlockCopilot(e.detail?.label || '');
            });

            // Programmatic API: any module can open the unified assistant bound
            // to a concrete field (string selector or element).
            window.CmsAssistant = {
                open: (options = {}) => {
                    if (options.target) {
                        const el = typeof options.target === 'string'
                            ? document.querySelector(options.target)
                            : options.target;
                        if (el) {
                            this.attachTriggerTo(el);
                        }
                    }

                    if (options.mode === 'image' || options.mode === 'video') {
                        this.openMediaCopilot(options.mode, options);
                    } else if (options.mode === 'block') {
                        this.openBlockCopilot(options.label || '');
                    } else if (options.mode === 'seo') {
                        this.openSeoModal();
                    } else {
                        this.openCopilotModal();
                    }
                },
            };
            window.dispatchEvent(new CustomEvent('cms-assistant-ready'));

            // Resume a generation started before navigating away.
            this.resumePendingTask();
        },

        isAssistableElement(el) {
            if (!el || !el.tagName) return false;
            if (el.disabled || el.readOnly || el.closest?.('.admin-copilot-root')) return false;
            const tag = el.tagName.toLowerCase();
            if (tag === 'textarea') return true;
            if (tag === 'input') {
                const type = (el.getAttribute('type') || 'text').toLowerCase();
                const ignored = ['password', 'hidden', 'checkbox', 'radio', 'file', 'submit', 'button', 'reset', 'color', 'date', 'number', 'email', 'url', 'tel'];
                const binding = [el.getAttribute('name'), el.getAttribute('x-model')].filter(Boolean).join(' ');
                if (/image|thumbnail|avatar|video_url|cta_url|url\b/i.test(binding)) return false;
                return !ignored.includes(type);
            }
            return false;
        },

        isMetadataField(el) {
            const name = (el.getAttribute('name') || el.id || '').toLowerCase();
            return name.includes('meta_title') || name.includes('meta_description');
        },

        attachTriggerTo(el) {
            this.activeTarget = el;
            this.lastTarget = el;
            this.contextLabel = this.extractFieldLabel(el);
            this.currentLocale = this.detectLocale();
            this.updateTriggerPosition(el);
            this.showTrigger = true;
        },

        updateTriggerPosition(el) {
            const rect = el.getBoundingClientRect();
            if (rect.width === 0 && rect.height === 0) {
                this.showTrigger = false;
                return;
            }
            const isTextarea = el.tagName.toLowerCase() === 'textarea';
            const btnSize = 24;
            const top = isTextarea ? (rect.top + 6) : (rect.top + Math.max(2, (rect.height - btnSize) / 2));
            const right = Math.max(8, window.innerWidth - rect.right + 6);
            this.triggerStyle = `top: ${Math.round(top)}px; right: ${Math.round(right)}px;`;
        },

        extractFieldLabel(el) {
            if (el.id) {
                const label = document.querySelector(`label[for="${el.id}"]`);
                if (label && label.innerText) return label.innerText.trim();
            }
            const parentLabel = el.closest('label');
            if (parentLabel && parentLabel.innerText) return parentLabel.innerText.trim();
            return el.getAttribute('placeholder') || el.name || el.id || 'Campo de texto';
        },

        detectLocale() {
            if (window.cmsContext?.locale) return window.cmsContext.locale;
            const urlMatch = window.location.href.match(/locale=([a-z]{2})/i);
            if (urlMatch) return urlMatch[1];
            return document.documentElement.lang || null;
        },

        editorContext() {
            const context = window.cmsContext || {};
            return {
                context_source: 'editor',
                locale: context.locale || this.detectLocale(),
                editor_market_ids: Array.isArray(context.marketIds)
                    ? context.marketIds.map(Number).filter((id) => Number.isInteger(id) && id > 0)
                    : []
            };
        },

        canApplyContext() {
            const resolved = this.generatedResult?.resolved_context;
            if (!resolved) return true;
            const current = this.editorContext();
            const markets = [...current.editor_market_ids].sort((a, b) => a - b);
            const generatedMarkets = [...(resolved.market_ids || [])].sort((a, b) => a - b);
            if (resolved.locale !== current.locale || JSON.stringify(markets) !== JSON.stringify(generatedMarkets)) {
                this.errorMessage = @js(__('admin.ai.editor_context_changed'));
                return false;
            }
            return true;
        },

        // Contextual entry: text field (auto draft/improve) or metadata.
        openCopilotModal() {
            this.mode = 'text';
            this.mediaTarget = null;
            this.mediaInputTarget = null;
            // Fall back to the last focused assistable field so the floating
            // button / Alt+A still knows where to write the result.
            const el = this.activeTarget || this.lastTarget;
            this.activeTarget = el;
            const value = el ? (el.value || '').trim() : '';

            if (el && this.isMetadataField(el)) {
                this.textAction = 'improve';
                this.promptText = value;
            } else if (value !== '') {
                this.textAction = 'improve';
                this.promptText = value;
            } else {
                this.textAction = 'draft';
                this.promptText = '';
            }

            this.errorMessage = null;
            this.generatedResult = null;
            this.antiAiScore = null;
            this.openModal = true;
        },

        // Contextual entry from the block manager.
        openBlockCopilot(label = '') {
            this.mode = 'block';
            this.activeTarget = null;
            this.contextLabel = label || '{{ __('admin.ai.block_manager_context') }}';
            this.currentLocale = this.detectLocale();
            this.errorMessage = null;
            this.generatedResult = null;
            this.antiAiScore = null;
            this.openModal = true;
        },

        // Contextual entry from the SEO/GEO block: analyzes the whole entity.
        openSeoModal() {
            this.mode = 'seo';
            this.activeTarget = null;
            this.contextLabel = this.entityLabel || '{{ __('admin.ai.seo_block_context') }}';
            this.currentLocale = this.detectLocale();
            this.errorMessage = null;
            this.generatedResult = null;
            this.antiAiScore = null;
            this.promptText = '';
            this.openModal = true;
        },

        closeModal() {
            this.openModal = false;
        },

        modalTitle() {
            if (this.mode === 'block') return @js(__('admin.ai.generate_blocks_title'));
            if (this.mode === 'seo') return @js(__('admin.ai.generate_seo_title'));
            if (this.mode === 'image') return @js(__('ai.image_title'));
            if (this.mode === 'video') return @js(__('ai.video_title'));
            return @js(__('ai.editor_text_title'));
        },

        modalSubtitle() {
            if (this.mode === 'block') return @js(__('admin.ai.blocks_subtitle'));
            if (this.mode === 'seo') return @js(__('admin.ai.seo_subtitle'));
            if (this.mode === 'image') return @js(__('ai.image_help'));
            if (this.mode === 'video') return @js(__('ai.video_help'));
            return this.textAction === 'draft' ? @js(__('admin.ai.draft_subtitle')) : @js(__('admin.ai.improve_subtitle'));
        },

        getPlaceholder() {
            if (this.mode === 'block') return @js(__('admin.ai.ph_block_instruction'));
            if (this.mode === 'seo') return @js(__('admin.ai.ph_seo'));
            if (this.mode === 'image') return 'Describe la imagen que deseas: escenario, estilo fotográfico, iluminación, detalles (ej. "Un interiorismo nórdico luminoso con plantas y luz natural de tarde")...';
            if (this.mode === 'video') return 'Describe el vídeo cinemático: movimiento de cámara, acción y atmósfera (ej. "Toma con dron en travelling frontal sobre un paisaje costero al amanecer")...';
            return this.textAction === 'draft' ? @js(__('admin.ai.ph_topic')) : @js(__('admin.ai.ph_original_text'));
        },

        switchMode(m) {
            this.mode = m;
            this.errorMessage = null;
            this.generatedResult = null;
            if ((m === 'image' || m === 'video') && !this.promptText.trim()) {
                const title = this.entityTitle();
                if (title) {
                    this.promptText = title;
                }
            }
        },

        openMediaCopilot(mode, options = {}) {
            this.openCopilotModal();
            this.activeTarget = null;
            this.lastTarget = null;
            this.mediaTarget = options.mediaTarget || null;
            this.mediaInputTarget = typeof options.target === 'string'
                ? document.querySelector(options.target)
                : (options.target || null);
            this.contextLabel = options.label || (options.featured ? @js(__('admin.entity_form.cover_image')) : '');
            this.promptText = '';
            this.switchMode(mode);
        },

        applyMediaTarget() {
            if (!this.generatedResult?.url || !this.canApplyContext()) return;
            if (this.mediaInputTarget) {
                const input = this.mediaInputTarget;
                if (input.isConnected === false || !('value' in input)) {
                    this.errorMessage = @js(__('admin.ai.media_no_target'));
                    return;
                }
                input.value = this.generatedResult.url;
                input.dispatchEvent(new Event('input', { bubbles: true }));
                input.dispatchEvent(new Event('change', { bubbles: true }));
                this.closeModal();
                return;
            }
            if (!this.mediaTarget) return;
            window.dispatchEvent(new CustomEvent('file-selected', {
                detail: {
                    target: this.mediaTarget,
                    file: {
                        url: this.generatedResult.url,
                        name: this.generatedResult.title || this.promptText,
                        is_image: this.mode === 'image',
                        is_video: this.mode === 'video'
                    }
                }
            }));
            this.closeModal();
        },

        inferPromptFromForm() {
            const title = this.entityTitle() || '';
            const content = this.entityContent() || '';
            if (title || content) {
                this.promptText = (title ? title + '. ' : '') + (content ? content.slice(0, 150) : '');
            }
        },

        insertImageBlock(url, caption = '') {
            if (!this.canApplyContext()) return;
            window.dispatchEvent(new CustomEvent('cms-insert-block', {
                detail: {
                    block: {
                        type: 'image',
                        image_url: url,
                        caption: caption || '',
                        alt: caption || ''
                    }
                }
            }));
            this.closeModal();
        },

        insertVideoBlock(url, title = '') {
            if (!this.canApplyContext()) return;
            window.dispatchEvent(new CustomEvent('cms-insert-block', {
                detail: {
                    block: {
                        type: 'video',
                        video_url: url,
                        title: title || '',
                        autoplay: false,
                        loop: false,
                        muted: false
                    }
                }
            }));
            this.closeModal();
        },

        insertHeroVideo(url, headline = '') {
            if (!this.canApplyContext()) return;
            window.dispatchEvent(new CustomEvent('cms-insert-block', {
                detail: {
                    block: {
                        type: 'hero',
                        headline: headline || this.entityTitle() || '',
                        video_url: url,
                        video_background: true
                    }
                }
            }));
            this.closeModal();
        },

        setAsFeaturedImage(url) {
            if (!this.canApplyContext()) return;
            const input = document.querySelector('input[name="featured_image"], input[name="image"], input[name="thumbnail"]');
            if (!input) {
                this.errorMessage = @js(__('admin.ai.media_no_target'));
                return;
            }
            input.value = url;
            input.dispatchEvent(new Event('input', { bubbles: true }));
            input.dispatchEvent(new Event('change', { bubbles: true }));
            window.dispatchEvent(new CustomEvent('cms-set-featured-image', { detail: { url } }));
            this.closeModal();
        },

        hasFeaturedTarget() {
            return !!document.querySelector('input[name="featured_image"], input[name="image"], input[name="thumbnail"]');
        },

        copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text);
            }
        },

        selectedBlockDescription() {
            const block = this.availableBlocks.find((b) => b.type === this.blockType);

            return block ? block.description : '';
        },

        // Enqueue the action and poll until the background worker finishes.
        // Returns the same {success, data, error} shape as the sync endpoint.
        async postGenerate(payload) {
            const start = await fetch('{{ route("admin.ai_assistant.generate_async") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ...payload,
                    ...this.editorContext(),
                    return_url: window.location.href
                })
            });

            const started = await start.json();
            if (!started.success) {
                return { success: false, error: started.question || started.message || started.error || 'No se pudo iniciar la generación.' };
            }

            // Persist so the result can be resumed after navigating away.
            this.rememberTask(started.task_id);

            try {
                return await this.pollTask(started.task_id);
            } finally {
                this.forgetTask();
            }
        },

        async pollTask(taskId) {
            const statusUrl = @js(route('admin.ai_assistant.generate_status', ['task' => '__TASK__'])).replace('__TASK__', encodeURIComponent(taskId));
            const deadline = Date.now() + 30 * 60 * 1000;
            let elapsed = 0;

            while (Date.now() < deadline) {
                await new Promise((resolve) => setTimeout(resolve, 2000));
                elapsed += 2;

                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                const state = await res.json();

                if (state.status === 'needs_input') {
                    return { success: false, error: state.question || state.message || state.data?.question || @js(__('admin.ai.context_clarification')) };
                }
                if (!state.success) {
                    return { success: false, error: state.error || 'Tarea de generación no encontrada.' };
                }
                if (state.status === 'completed') {
                    return { success: true, data: state.data };
                }
                if (['uncertain', 'approval_required'].includes(state.status)) {
                    const message = state.error || (state.status === 'uncertain'
                        ? 'El resultado es incierto. Revisa la operación antes de volver a ejecutarla.'
                        : 'Se requiere aprobación humana del cambio concreto.');
                    return { success: false, error: message, approvalRequired: state.status === 'approval_required', uncertain: state.status === 'uncertain' };
                }
                if (state.status === 'failed') {
                    return { success: false, error: state.error || 'La generación falló.' };
                }

                this.progressNote = `Procesando en segundo plano… ${elapsed}s`;
            }

            return { success: false, error: 'La generación superó el tiempo máximo de espera.' };
        },

        rememberTask(taskId) {
            const target = this.activeTarget;
            const selector = target && target.name ? `[name="${target.name}"]` : null;

            try {
                localStorage.setItem('ai.modal.task', JSON.stringify({
                    id: taskId,
                    mode: this.mode,
                    selector,
                    path: window.location.pathname,
                }));
            } catch (e) {
                // localStorage unavailable: ignore.
            }
        },

        forgetTask() {
            try {
                localStorage.removeItem('ai.modal.task');
            } catch (e) {
                // ignore
            }
        },

        // Resume a task started from this modal before navigating away. The
        // result is shown for review (never applied silently).
        resumePendingTask() {
            const params = new URLSearchParams(window.location.search);

            // Deep link from a notification always resumes.
            if (params.get('task')) {
                // The workbench and the create-form assistant handle their own
                // deep links; the contextual modal only claims tasks started
                // from a field on an entity page.
                if (!this.handlesOwnResume) {
                    this.resumeFromContext({ id: params.get('task'), mode: 'text', selector: null });
                }

                return;
            }

            let saved = null;
            try {
                saved = JSON.parse(localStorage.getItem('ai.modal.task') || 'null');
            } catch (e) {
                saved = null;
            }

            // Only resume on the page where the generation was started: never
            // pop the modal open while browsing other backoffice pages.
            if (!saved || !saved.id || saved.path !== window.location.pathname) {
                return;
            }

            this.resumeFromContext(saved);
        },

        resumeFromContext(saved) {
            if (saved.selector) {
                const el = document.querySelector(saved.selector);
                if (el) {
                    this.attachTriggerTo(el);
                }
            }

            this.mode = saved.mode || 'text';
            this.openModal = true;
            this.isGenerating = true;
            this.progressNote = @js(__('admin.ai.resuming'));

            this.pollTask(saved.id).then((result) => {
                if (result.success) {
                    this.generatedResult = result.data;
                    this.antiAiScore = this.auditScore(result.data);
                } else {
                    this.errorMessage = result.error;
                }
            }).finally(() => {
                this.isGenerating = false;
                this.progressNote = '';
                this.forgetTask();
            });
        },

        async generateContent() {
            this.currentLocale = this.detectLocale();
            this.isGenerating = true;
            this.errorMessage = null;
            this.generatedResult = null;
            this.antiAiScore = null;

            try {
                let data;

                if (this.mode === 'seo') {
                    // Analyze the whole entity (title, summary, body and the
                    // text of its sections) to draft its SEO/GEO metadata.
                    data = await this.postGenerate({
                        action: 'seo',
                        title: this.entityTitle(),
                        content: this.entityContent(),
                        prompt: this.promptText,
                        locale: this.currentLocale
                    });
                } else if (this.mode === 'block') {
                    if (this.blockType && this.blockType !== 'auto') {
                        // Generate ONE concrete block, content-filled.
                        const single = await this.postGenerate({
                            action: 'block',
                            block_type: this.blockType,
                            prompt: this.promptText,
                            locale: this.currentLocale
                        });

                        if (!single.success) {
                            data = single;
                        } else {
                            const block = single.data || {};
                            data = {
                                success: true,
                                data: { sections: [Object.assign({ type: block.type || this.blockType }, block.data || {})], resolved_context: block.resolved_context }
                            };
                        }
                    } else {
                        // Generate a REAL, content-filled block sequence (same engine
                        // as the entity creator), ready to insert into the block manager.
                        data = await this.postGenerate({
                            action: 'compose_blocks',
                            prompt: this.promptText,
                            locale: this.currentLocale
                        });
                    }
                } else if (this.mode === 'image') {
                    data = await this.postGenerate({
                        action: 'image',
                        prompt: this.promptText,
                        aspect_ratio: this.imageAspectRatio || '16:9',
                        title: this.entityTitle() || 'Imagen generada por IA'
                    });
                } else if (this.mode === 'video') {
                    data = await this.postGenerate({
                        action: 'video',
                        prompt: this.promptText,
                        aspect_ratio: this.videoAspectRatio || '16:9',
                        duration: parseInt(this.videoDuration, 10) || 5,
                        title: this.entityTitle() || 'Vídeo generado por IA'
                    });
                } else if (this.textAction === 'draft') {
                    data = await this.postGenerate({
                        action: 'draft',
                        topic: this.promptText,
                        locale: this.currentLocale,
                        style: 'periodistico',
                        include_blocks: false
                    });
                } else {
                    // Improve existing text, then humanize the result.
                    const improved = await this.postGenerate({
                        action: 'proofread',
                        content: this.promptText,
                        proofread_action: 'improve',
                        locale: this.currentLocale
                    });

                    if (!improved.success) {
                        throw new Error(improved.error || 'No se pudo mejorar el texto.');
                    }

                    const humanized = await this.postGenerate({
                        action: 'humanize',
                        content: improved.data.improved || this.promptText,
                        locale: this.currentLocale,
                        style: 'periodistico'
                    });

                    data = humanized.success ? humanized : improved;
                }

                if (!data.success) {
                    throw new Error(data.error || 'No se pudo generar contenido con IA.');
                }

                this.generatedResult = data.data;
                this.antiAiScore = this.auditScore(data.data);
            } catch (err) {
                this.errorMessage = err.message || 'Error de comunicación con el servidor.';
            } finally {
                this.isGenerating = false;
            }
        },

        auditScore(result) {
            const score = result?.anti_ai_audit?.anti_ai_score ?? result?.anti_ai_score ?? null;
            return typeof score === 'number' && Number.isFinite(score) && score >= 0 && score <= 100
                ? score
                : null;
        },

        getFormattedResultText() {
            if (!this.generatedResult) return '';
            const r = this.generatedResult;
            if (typeof r === 'string') return r;
            if (Array.isArray(r.sections)) {
                return r.sections.map((b) => '• ' + (b.type || '')).join('\n');
            }
            if (Array.isArray(r.recommended_blocks)) {
                return r.recommended_blocks.map((b) => '• ' + (b.name || b.type) + (b.rationale ? ' — ' + b.rationale : '')).join('\n');
            }
            if (r.meta_title || r.meta_description) {
                return '• ' + (r.meta_title || '') + '\n\n' + (r.meta_description || '');
            }
            return r.content || r.humanized || r.improved || r.text || JSON.stringify(r);
        },

        resultSummary() {
            const r = this.generatedResult;
            if (r && Array.isArray(r.sections)) return r.sections.length + ' ' + @js(__('admin.ai.blocks'));
            if (r && Array.isArray(r.recommended_blocks)) return r.recommended_blocks.length + ' ' + @js(__('admin.ai.blocks'));
            if (r && (r.meta_title || r.meta_description)) return @js(__('admin.ai.seo_ready'));
            if (r && r.cliches_removed) return r.cliches_removed.length + ' ' + @js(__('admin.ai.cliches_removed'));
            return '';
        },

        hasTarget() {
            return !!(this.activeTarget || this.lastTarget);
        },

        applyResult(mode = 'replace') {
            if (!this.generatedResult || !this.canApplyContext()) {
                return;
            }

            const target = this.activeTarget || this.lastTarget;

            if (!target) {
                this.errorMessage = @js(__('admin.ai.no_target_hint'));
                return;
            }

            this.activeTarget = target;
            const text = this.getFormattedResultText();

            // Rich editors (contenteditable / WYSIWYG) opt in through a generic
            // event instead of a plain `.value` assignment.
            if (!('value' in target)) {
                window.dispatchEvent(new CustomEvent('cms-ai-insert', { detail: { target, text, mode } }));
                this.closeModal();
                return;
            }

            if (mode === 'replace') {
                target.value = text;
            } else {
                target.value = (target.value ? target.value + '\n\n' : '') + text;
            }

            target.dispatchEvent(new Event('input', { bubbles: true }));
            target.dispatchEvent(new Event('change', { bubbles: true }));

            target.classList.add('ring-2', 'ring-[#00FF99]');
            setTimeout(() => target.classList.remove('ring-2', 'ring-[#00FF99]'), 1200);

            this.closeModal();
        },

        // Fill the entity's SEO/GEO block with the generated metadata.
        applySeo() {
            const r = this.generatedResult;
            if (!r || !this.canApplyContext()) return;

            this.fillBoundField('activeMetaTitle', r.meta_title);
            this.fillBoundField('activeMetaDesc', r.meta_description);

            this.highlightAiTarget('[x-model="activeMetaTitle"]');
            this.closeModal();
        },

        fillBoundField(model, value) {
            if (!value) return;
            const el = document.querySelector(`[x-model="${model}"]`);
            if (!el) return;
            el.value = value;
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
        },

        highlightAiTarget(selector) {
            const el = document.querySelector(selector);
            if (!el) return;
            const box = el.closest('.cyber-card') || el.closest('[x-data]') || el;
            box.scrollIntoView({ behavior: 'smooth', block: 'center' });
            box.classList.add('ring-2', 'ring-[#00FF99]');
            setTimeout(() => box.classList.remove('ring-2', 'ring-[#00FF99]'), 2200);
        },

        entityTitle() {
            const field = this.entityFields.title?.input;
            if (field) {
                const name = String(field).replace('{locale}', this.detectLocale());
                const active = document.getElementsByName(name)[0];
                if (active) return active.value || '';
            }
            const el = document.querySelector('[name*="[title]"], [name*="[name]"], [name^="title_"], [name^="name_"]');
            return el && el.value ? el.value : (this.entityLabel || '');
        },

        // Assemble everything written in the entity: summary/body fields plus
        // the text of its sections (block manager payload).
        entityContent() {
            const parts = [];
            const locale = this.detectLocale();

            document.querySelectorAll('[name*="[summary]"], [name*="[content]"], [name*="[description]"]').forEach((el) => {
                const translation = el.name.match(/^translations\[([^\]]+)\]/);
                if (translation && translation[1] !== locale) return;
                if (el.value) parts.push(el.value);
            });

            const sections = document.querySelector('[name="sections_json"]');
            if (sections && sections.value) {
                try {
                    const parsed = JSON.parse(sections.value);
                    const localized = Array.isArray(parsed) ? parsed : parsed[locale];
                    const collect = (block) => {
                        if (!block || typeof block !== 'object') return;
                        ['title', 'content', 'headline', 'subtitle', 'description', 'question', 'answer', 'label', 'value', 'caption', 'alt'].forEach((key) => {
                            if (typeof block[key] === 'string' && block[key]) parts.push(block[key]);
                        });
                        ['items', 'blocks', 'columns', 'rows', 'slides'].forEach((key) => {
                            if (Array.isArray(block[key])) block[key].forEach(collect);
                        });
                    };
                    if (Array.isArray(localized)) localized.forEach(collect);
                } catch (e) {
                    // Ignore malformed section payloads.
                }
            }

            return parts.join('\n\n').slice(0, 6000);
        },

        applyBlocks(mode = 'replace') {
            const r = this.generatedResult;
            if (!r || !this.canApplyContext()) return;

            const blocks = Array.isArray(r.sections)
                ? r.sections
                : (Array.isArray(r.recommended_blocks) ? r.recommended_blocks : [r]);

            if (mode === 'replace') {
                window.dispatchEvent(new CustomEvent('cms-replace-blocks', { detail: { blocks } }));
            } else {
                blocks.forEach((block) => {
                    window.dispatchEvent(new CustomEvent('cms-insert-block', {
                        detail: { block: block, index: null }
                    }));
                });
            }

            this.closeModal();
        }
    };
}
</script>
