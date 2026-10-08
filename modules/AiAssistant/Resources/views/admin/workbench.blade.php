@extends('admin.layout')

@section('title', __('admin.page_titles.ai_workbench'))

@section('content')
@include('ai_assistant::admin.partials.orchestration')
<div class="space-y-6" x-data="aiWorkbench()">
    <!-- Header & Provider Status Bar -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-[#181818] p-6 rounded-2xl border border-gray-800 shadow-xl">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-purple-500/10 border border-purple-500/20 flex items-center justify-center text-purple-400 text-2xl shadow-inner">
                <i class="fa-solid fa-wand-magic-sparkles"></i>
            </div>
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-xl font-bold text-white">{{ __('admin.ai.page_assistant_title') }}</h1>
                    <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $isConfigured ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/30' : 'bg-amber-500/10 text-amber-400 border border-amber-500/30' }}">
                        {{ $isConfigured ? 'Configurado: ' . strtoupper($activeProvider) : 'Configura un proveedor' }}
                    </span>
                </div>
                <p class="text-xs text-gray-400 mt-1">
                    {{ __('admin.ai.page_assistant_desc') }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <div class="text-right hidden sm:block">
                <span class="text-[11px] text-gray-400 block">{{ __('admin.ai.active_model') }}</span>
                <span class="text-xs font-mono font-bold text-purple-300">{{ $configuredModel }}</span>
            </div>
            <button type="button"
                    @click="testActiveConnection()"
                    :disabled="testing"
                    class="px-3.5 py-2 rounded-xl bg-gray-800 hover:bg-gray-700 text-gray-300 hover:text-white border border-gray-700 text-xs font-medium transition-all flex items-center gap-2">
                <i class="fa-solid" :class="testing ? 'fa-spinner fa-spin' : 'fa-plug-circle-bolt'"></i>
                <span x-text="testing ? 'Probando...' : 'Test de Conexión'"></span>
            </button>
            <a href="{{ route('admin.modules.settings', ['identifier' => 'ai_assistant']) }}"
               class="px-3.5 py-2 rounded-xl bg-purple-600/20 hover:bg-purple-600/30 text-purple-300 hover:text-white border border-purple-500/40 text-xs font-semibold transition-all flex items-center gap-2">
                <i class="fa-solid fa-sliders"></i>
                <span>{{ __('admin.ai.configure_providers') }}</span>
            </a>
        </div>
    </div>

    <!-- Feedback Banner -->
    <template x-if="toastMessage">
        <div class="p-4 rounded-xl border flex items-center justify-between transition-all"
             :class="toastType === 'success' ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/10 border-rose-500/30 text-rose-300'">
            <div class="flex items-center gap-3">
                <i class="fa-solid" :class="toastType === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation'"></i>
                <span class="text-xs font-medium" x-text="toastMessage"></span>
            </div>
            <button type="button" @click="toastMessage = null" class="text-xs opacity-70 hover:opacity-100">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </template>

    <!-- Tabs -->
    <div class="flex border-b border-gray-800 space-x-2 flex-wrap">
        <button type="button" @click="currentTab = 'page'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'page' ? 'border-purple-500 text-purple-400 bg-purple-500/5' : 'border-transparent text-gray-400 hover:text-gray-200'">
            <i class="fa-solid fa-file-circle-plus"></i>
            <span>{{ __('admin.ai.tab_draft') }}</span>
        </button>
        <button type="button" @click="currentTab = 'orchestrator'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'orchestrator' ? 'border-purple-500 text-purple-400 bg-purple-500/5' : 'border-transparent text-gray-400 hover:text-gray-200'">
            <i class="fa-solid fa-brain text-purple-400"></i>
            <span>Orquestador Autónomo</span>
        </button>
        <button type="button" @click="currentTab = 'media'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'media' ? 'border-emerald-500 text-emerald-400 bg-emerald-500/5' : 'border-transparent text-gray-400 hover:text-gray-200'">
            <i class="fa-solid fa-film text-emerald-400"></i>
            <span>Estudio Imagen y Vídeo</span>
        </button>
        <button type="button" @click="currentTab = 'refine'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'refine' ? 'border-purple-500 text-purple-400 bg-purple-500/5' : 'border-transparent text-gray-400 hover:text-gray-200'">
            <i class="fa-solid fa-wand-magic"></i>
            <span>{{ __('admin.ai.humanize_seo') }}</span>
        </button>
        <button type="button" @click="currentTab = 'logs'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'logs' ? 'border-purple-500 text-purple-400 bg-purple-500/5' : 'border-transparent text-gray-400 hover:text-gray-200'">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>{{ __('admin.ai.tab_history') }}</span>
        </button>
        <a href="{{ route('admin.modules.settings', ['identifier' => 'ai_assistant']) }}"
           class="px-4 py-3 text-xs font-bold transition-all border-b-2 border-transparent text-gray-400 hover:text-[#00FF99] flex items-center gap-2 ml-auto">
            <i class="fa-solid fa-server text-purple-400"></i>
            <span>{{ __('admin.ai.tab_models') }}</span>
        </a>
    </div>

    <!-- TAB 1: PAGE ASSISTANT (create a complete element as a draft) -->
    <div x-show="currentTab === 'page'" class="grid grid-cols-1 lg:grid-cols-12 gap-6" style="display: none;">
        <div class="lg:col-span-5 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-5">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-file-circle-plus text-purple-400"></i>
                <span>{{ __('admin.ai.create_element') }}</span>
            </h2>

            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">{{ __('admin.ai.element_type') }}</label>
                <select x-model="pageForm.type" class="w-full bg-[#121212] border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:border-purple-500 focus:outline-none">
                    @foreach($aiEntities as $entityType => $entityLabel)
                        <option value="{{ $entityType }}">{{ $entityLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">{{ __('admin.ai.brief') }}</label>
                <textarea x-model="pageForm.prompt"
                          rows="7"
                          class="w-full bg-[#121212] border border-gray-800 rounded-xl p-3 text-xs text-white focus:border-purple-500 focus:outline-none resize-none placeholder-gray-600 font-sans leading-relaxed"
                          :placeholder="briefPlaceholder()"></textarea>
                <p class="mt-2 text-[11px] text-gray-400">{{ __('admin.ai.prompt_context_hint') }}</p>

                <!-- Example briefs per entity type (helps to start writing) -->
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <template x-for="(example, exIdx) in briefExamples()" :key="exIdx">
                        <button type="button"
                                @click="pageForm.prompt = example"
                                class="px-2 py-1 rounded-lg bg-[#1A1A1A] hover:bg-purple-600/20 border border-gray-800 hover:border-purple-500/40 text-gray-400 hover:text-purple-300 text-[10px] transition-all text-left"
                                x-text="example"></button>
                    </template>
                </div>
            </div>

            <button type="button"
                    @click="generatePageElement()"
                    :disabled="generating || !pageForm.prompt.trim()"
                    class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-bold transition-all shadow-lg shadow-purple-600/20 flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                <span x-text="generating ? (progressNote || 'Generando...') : @js(__('admin.ai.generate_element'))"></span>
            </button>
        </div>

        <div class="lg:col-span-7 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-eye text-emerald-400"></i>
                <span>{{ __('admin.ai.preview') }}</span>
            </h3>

            <div x-show="!pageResult" class="py-24 text-center text-gray-600">
                <i class="fa-solid fa-file-circle-plus text-3xl mb-3 block opacity-40"></i>
                <p class="text-xs" x-text="generating ? (progressNote || 'Generando...') : @js(__('admin.ai.page_assistant_hint'))"></p>
            </div>

            <template x-if="pageResult">
                <div class="space-y-4">
                    <p x-show="pageResult.resolved_context" class="text-[11px] text-gray-400">
                        {{ __('admin.ai.language') }}: <span x-text="pageResult.resolved_context?.locale"></span>
                        · {{ __('admin.ai.market') }}: <span x-text="pageResult.resolved_context?.market"></span>
                    </p>
                    <div class="p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400 flex items-center justify-between">
                        <span x-show="Number.isFinite(pageResult.anti_ai_audit?.anti_ai_score)">{{ __('admin.ai.anti_ai_quality') }} <strong x-text="pageResult.anti_ai_audit?.anti_ai_score"></strong>/100</span>
                        <span class="text-[11px] text-amber-300">{{ __('admin.ai.draft_notice') }}</span>
                    </div>

                    <div class="bg-[#121212] p-4 rounded-xl border border-gray-800 space-y-3">
                        <div x-show="pageResult.fields?.title">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.field_title') }}</span>
                            <p class="text-sm text-white font-semibold" x-text="pageResult.fields?.title"></p>
                        </div>
                        <div x-show="pageResult.fields?.summary">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.field_summary') }}</span>
                            <p class="text-xs text-gray-300" x-text="pageResult.fields?.summary"></p>
                        </div>
                        <div x-show="pageResult.fields?.body">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.field_content') }}</span>
                            <div class="text-xs text-gray-200 leading-relaxed whitespace-pre-line max-h-56 overflow-y-auto" x-text="pageResult.fields?.body"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="bg-[#121212] p-3 rounded-xl border border-gray-800" x-show="pageResult.fields?.meta_title">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.seo_title') }}</span>
                            <p class="text-xs text-white" x-text="pageResult.fields?.meta_title"></p>
                        </div>
                        <div class="bg-[#121212] p-3 rounded-xl border border-gray-800" x-show="pageResult.fields?.meta_description">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.meta_description_label') }}</span>
                            <p class="text-xs text-gray-300" x-text="pageResult.fields?.meta_description"></p>
                        </div>
                    </div>

                    <template x-if="pageResult.sections && pageResult.sections.length">
                        <div class="bg-[#121212] p-3 rounded-xl border border-gray-800">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-2">
                                {{ __('admin.ai.composed_blocks') }} (<span x-text="pageResult.sections.length"></span>)
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(section, sIdx) in pageResult.sections" :key="sIdx">
                                    <span class="px-2 py-0.5 rounded bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-[10px] font-mono" x-text="section.type"></span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button type="button"
                            @click="createDraft()"
                            :disabled="creating"
                            class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white text-xs font-bold transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid" :class="creating ? 'fa-spinner fa-spin' : 'fa-file-arrow-down'"></i>
                        <span x-text="creating ? 'Creando...' : @js(__('admin.ai.create_draft'))"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- TAB 2: REFINE (humanize / proofread / SEO) -->
    <div x-show="currentTab === 'refine'" class="grid grid-cols-1 lg:grid-cols-12 gap-6" style="display: none;">
        <div class="lg:col-span-6 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-4">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-align-left text-purple-400"></i>
                <span>{{ __('admin.ai.original_text') }}</span>
            </h2>

            <div>
                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1">{{ __('admin.ai.desired_action') }}</label>
                    <select x-model="refineForm.action" class="w-full bg-[#121212] border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:border-purple-500 focus:outline-none">
                        <option value="humanize">{{ __('admin.ai.action_humanize') }}</option>
                        <option value="proofread">{{ __('admin.ai.action_grammar') }}</option>
                        <option value="seo">{{ __('admin.ai.gen_seo') }}</option>
                    </select>
                </div>
            </div>

            <textarea x-model="refineForm.content"
                      rows="10"
                      class="w-full bg-[#121212] border border-gray-800 rounded-xl p-3 text-xs text-white focus:border-purple-500 focus:outline-none resize-none placeholder-gray-600 font-sans leading-relaxed"
                      placeholder="{{ __('admin.ai.ph_original_text') }}"></textarea>

            <div>
                <label for="ai-refine-instruction" class="block text-xs font-semibold text-gray-300 mb-1">{{ __('admin.ai.optional_instruction') }}</label>
                <textarea id="ai-refine-instruction" x-model="refineForm.prompt" rows="2" class="w-full bg-[#121212] border border-gray-800 rounded-xl p-3 text-xs text-white focus:border-purple-500 focus:outline-none resize-none"></textarea>
                <p class="mt-2 text-[11px] text-gray-400">{{ __('admin.ai.prompt_context_hint') }}</p>
            </div>

            <button type="button"
                    @click="executeRefine()"
                    :disabled="generating || !refineForm.content.trim()"
                    class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-bold transition-all shadow-lg shadow-purple-600/20 flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                <span x-text="generating ? (progressNote || 'Procesando...') : 'Ejecutar Mejora Inteligente'"></span>
            </button>
        </div>

        <div class="lg:col-span-6 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-400"></i>
                <span>{{ __('admin.ai.optimized') }}</span>
            </h3>

            <div x-show="!refineResult" class="py-24 text-center text-gray-600">
                <i class="fa-solid fa-sliders text-3xl mb-3 block opacity-40"></i>
                <p class="text-xs" x-text="generating ? (progressNote || 'Procesando...') : @js(__('admin.ai.action_hint'))"></p>
            </div>

            <div x-show="refineResult" class="space-y-4">
                <template x-if="refineResult && refineResult.humanized">
                    <div>
                        <div class="p-3 mb-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-xs text-emerald-400 flex items-center justify-between">
                            <span x-show="Number.isFinite(refineResult.anti_ai_score)">{{ __('admin.ai.anti_ai_quality') }} <strong x-text="refineResult.anti_ai_score"></strong>/100</span>
                            <span class="text-[11px] text-gray-400" x-text="refineResult.cliches_removed.length + ' clichés eliminados'"></span>
                        </div>
                        <div class="bg-[#121212] p-4 rounded-xl border border-gray-800 text-xs text-gray-200 leading-relaxed whitespace-pre-line"
                             x-text="refineResult.humanized"></div>
                    </div>
                </template>

                <template x-if="refineResult && refineResult.improved">
                    <div class="bg-[#121212] p-4 rounded-xl border border-gray-800 text-xs text-gray-200 leading-relaxed whitespace-pre-line"
                         x-text="refineResult.improved"></div>
                </template>

                <template x-if="refineResult && refineResult.meta_title">
                    <div class="space-y-3">
                        <div class="bg-[#121212] p-3 rounded-xl border border-gray-800">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.seo_title') }}</span>
                            <p class="text-xs text-white font-semibold" x-text="refineResult.meta_title"></p>
                        </div>
                        <div class="bg-[#121212] p-3 rounded-xl border border-gray-800">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.meta_description_label') }}</span>
                            <p class="text-xs text-gray-300" x-text="refineResult.meta_description"></p>
                        </div>
                        <div class="bg-[#121212] p-3 rounded-xl border border-gray-800">
                            <span class="text-[10px] text-gray-500 uppercase font-bold block mb-1">{{ __('admin.ai.keywords') }}</span>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                <template x-for="kw in refineResult.keywords" :key="kw">
                                    <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 text-[10px]" x-text="kw"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- TAB 3: HISTORY -->
    <div x-show="currentTab === 'logs'" class="bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-4" style="display: none;">
        <h2 class="text-sm font-bold text-white flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-purple-400"></i>
            <span>{{ __('admin.ai.history_title') }}</span>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-gray-400">
                <thead class="text-[10px] uppercase bg-[#121212] text-gray-500 border-b border-gray-800">
                    <tr>
                        <th class="py-3 px-4">{{ __('admin.ai.action') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.provider_model') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.tokens') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.latency') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.language') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-800/60">
                    @forelse($recentLogs as $log)
                        <tr class="hover:bg-gray-800/30 transition-colors">
                            <td class="py-3 px-4 font-semibold text-white">
                                <span class="px-2 py-0.5 rounded bg-purple-500/10 text-purple-400 border border-purple-500/20 text-[10px] uppercase">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-gray-200">{{ strtoupper($log->provider) }}</span>
                                <span class="text-gray-500 text-[11px] block">{{ $log->model }}</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px]">
                                {{ number_format($log->total_tokens) }}
                                <span class="text-gray-600 text-[10px]">({{ $log->prompt_tokens }} / {{ $log->completion_tokens }})</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-gray-300">
                                {{ $log->latency_ms }} ms
                            </td>
                            <td class="py-3 px-4 uppercase text-[11px] font-bold text-gray-400">
                                {{ $log->locale }} ({{ $log->market }})
                            </td>
                            <td class="py-3 px-4 text-[11px] text-gray-500">
                                {{ $log->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-gray-600">
                                No se han registrado generaciones todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: ORCHESTRATOR (Autonomous End-to-End Post/Page Generation with Deep Research) -->
    <div x-show="currentTab === 'orchestrator'" class="grid grid-cols-1 lg:grid-cols-12 gap-6" style="display: none;">
        <div class="lg:col-span-5 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-5">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-brain text-purple-400"></i>
                <span>Orquestación de Entidad Multimodal</span>
            </h2>
            <p class="text-xs text-gray-400">
                La IA investiga fuentes reales en internet, redacta contenido natural anti-IA, sintetiza imágenes o vídeos y compone la estructura completa de bloques para dbCMS.
            </p>

            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">Tipo de Entidad</label>
                <select x-model="orchestratorForm.entity_type" class="w-full bg-[#121212] border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:border-purple-500 focus:outline-none">
                    <option value="post">Artículo de Blog (con imagen destacada, bloques y SEO)</option>
                    <option value="page">Página Modular / Landing (con Hero, bloques interactivos y CTA)</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">Tema, Enfoque o Instrucción Natural</label>
                <textarea x-model="orchestratorForm.topic"
                          rows="4"
                          placeholder="Ej: Investiga las novedades de Laravel 11 y crea un post detallado explicando sus ventajas para empresas con ejemplos de código y bloques destacados..."
                          class="w-full bg-[#121212] border border-gray-800 rounded-xl p-3 text-xs text-white focus:border-purple-500 focus:outline-none resize-none leading-relaxed"></textarea>
            </div>

            <p class="text-[11px] text-gray-400">{{ __('admin.ai.prompt_context_hint') }}</p>

            <div class="space-y-2 pt-2 border-t border-gray-800">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="orchestratorForm.generate_images" class="rounded border-gray-700 text-purple-600 focus:ring-0">
                    <span class="text-xs text-gray-300">Generar Imagen con IA (Magnific / DALL-E / Imagen)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="orchestratorForm.generate_video" class="rounded border-gray-700 text-purple-600 focus:ring-0">
                    <span class="text-xs text-gray-300">Generar Vídeo Cinemático con IA (Higgsfield / Magnific)</span>
                </label>
            </div>

            <button type="button"
                    @click="orchestrateEntity()"
                    :disabled="generating || !orchestratorForm.topic.trim()"
                    class="w-full py-3 rounded-xl bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-brain'"></i>
                <span x-text="generating ? (progressNote || 'Orquestando…') : 'Iniciar Orquestación Autónoma'"></span>
            </button>
        </div>

        <!-- Orchestrator Live Result Preview -->
        <div class="lg:col-span-7 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-4">
            <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                <h3 class="text-sm font-bold text-white flex items-center gap-2">
                    <i class="fa-solid fa-eye text-purple-400"></i>
                    <span>Resultado de la Orquestación</span>
                </h3>
                <template x-if="orchestratorResult">
                    <button type="button"
                            @click="saveOrchestratedEntity()"
                            :disabled="creating"
                            class="px-3.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition-all flex items-center gap-1.5">
                        <i class="fa-solid" :class="creating ? 'fa-spinner fa-spin' : 'fa-circle-check'"></i>
                        <span x-text="creating ? 'Guardando...' : 'Crear en dbCMS'"></span>
                    </button>
                </template>
            </div>

            <template x-if="!orchestratorResult && !generating">
                <div class="py-16 text-center text-gray-600 space-y-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-3xl opacity-30"></i>
                    <p class="text-xs">Introduce una instrucción y pulsa "Iniciar Orquestación Autónoma".</p>
                </div>
            </template>

            <template x-if="generating">
                <div class="py-16 text-center text-purple-400 space-y-3">
                    <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
                    <p class="text-xs font-bold" x-text="progressNote"></p>
                    <p class="text-[11px] text-gray-500">Investigando fuentes, redactando contenido anti-IA y creando medios...</p>
                </div>
            </template>

            <template x-if="orchestratorResult">
                <div class="space-y-4">
                    <p x-show="orchestratorResult.resolved_context" class="text-[11px] text-gray-400">
                        {{ __('admin.ai.language') }}: <span x-text="orchestratorResult.resolved_context?.locale"></span>
                        · {{ __('admin.ai.market') }}: <span x-text="orchestratorResult.resolved_context?.market"></span>
                    </p>
                    <!-- Title & Summary -->
                    <div class="space-y-2">
                        <h4 class="text-base font-bold text-white" x-text="orchestratorResult.title"></h4>
                        <p class="text-xs text-gray-400 italic" x-text="orchestratorResult.summary"></p>
                    </div>

                    <!-- Media item if generated -->
                    <template x-if="orchestratorResult.featured_image">
                        <div class="rounded-xl overflow-hidden border border-emerald-500/30 bg-black/60 max-h-56 flex items-center justify-center">
                            <img :src="orchestratorResult.featured_image" alt="Imagen destacada" class="max-h-56 w-auto object-contain">
                        </div>
                    </template>
                    <template x-if="orchestratorResult.video_url">
                        <div class="rounded-xl overflow-hidden border border-purple-500/30 bg-black/60 max-h-56 flex items-center justify-center">
                            <video :src="orchestratorResult.video_url" controls class="max-h-56 w-full object-contain"></video>
                        </div>
                    </template>

                    <!-- Content excerpt -->
                    <div class="p-3 bg-[#121212] rounded-xl border border-gray-800 text-xs text-gray-300 max-h-48 overflow-y-auto whitespace-pre-line leading-relaxed"
                         x-text="orchestratorResult.content"></div>

                    <!-- Synthesized Blocks -->
                    <template x-if="Array.isArray(orchestratorResult.blocks) && orchestratorResult.blocks.length > 0">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold text-gray-400">
                                <span>Bloques Modulares Creados (<span x-text="orchestratorResult.blocks.length"></span>)</span>
                            </div>
                            <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                                <template x-for="(b, idx) in orchestratorResult.blocks" :key="idx">
                                    <div class="p-2 rounded-lg bg-[#121212] border border-gray-800 flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 h-5 rounded bg-purple-500/20 text-purple-400 flex items-center justify-center font-mono text-[10px]" x-text="idx + 1"></span>
                                            <span class="font-bold text-white uppercase text-[11px]" x-text="b.type"></span>
                                            <span class="text-gray-400 text-[11px] truncate max-w-xs" x-text="b.title || b.headline || ''"></span>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- TAB: MULTIMODAL MEDIA STUDIO (Direct Image & Video Generation) -->
    <div x-show="currentTab === 'media'" class="grid grid-cols-1 lg:grid-cols-12 gap-6" style="display: none;">
        <div class="lg:col-span-5 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-5">
            <h2 class="text-sm font-bold text-white flex items-center gap-2">
                <i class="fa-solid fa-film text-emerald-400"></i>
                <span>Estudio de Creación de Medios IA</span>
            </h2>
            <p class="text-xs text-gray-400">
                Genera imágenes fotorrealistas con Magnific AI / DALL-E / Imagen o vídeos cinemáticos con Higgsfield AI y guárdalos automáticamente en la mediateca de dbCMS.
            </p>

            <!-- Mode selector: Image vs Video -->
            <div class="flex items-center gap-2 p-1 bg-[#121212] rounded-xl border border-gray-800">
                <button type="button" @click="mediaForm.mode = 'image'"
                        :class="mediaForm.mode === 'image' ? 'bg-emerald-600 text-white font-bold' : 'text-gray-400 hover:text-white'"
                        class="flex-1 py-1.5 rounded-lg text-xs transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-image"></i>
                    <span>Imagen (Magnific / DALL-E)</span>
                </button>
                <button type="button" @click="mediaForm.mode = 'video'"
                        :class="mediaForm.mode === 'video' ? 'bg-purple-700 text-white font-bold' : 'text-gray-400 hover:text-white'"
                        class="flex-1 py-1.5 rounded-lg text-xs transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-video"></i>
                    <span>Vídeo (Higgsfield / Magnific)</span>
                </button>
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">Título en la Mediateca</label>
                <input type="text" x-model="mediaForm.title" placeholder="Ej: Paisaje ciberpunk de noche..."
                       class="w-full bg-[#121212] border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:border-emerald-500 focus:outline-none">
            </div>

            <div>
                <label class="block text-xs font-semibold text-gray-300 mb-1">Prompt Detallado</label>
                <textarea x-model="mediaForm.prompt"
                          rows="4"
                          :placeholder="mediaForm.mode === 'image' ? 'Describe la imagen con iluminación, atmósfera, estilo de cámara...' : 'Describe el movimiento de cámara (travelling, paneo), acción, ángulo...'"
                          class="w-full bg-[#121212] border border-gray-800 rounded-xl p-3 text-xs text-white focus:border-emerald-500 focus:outline-none resize-none leading-relaxed"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-gray-300 mb-1">Formato / Aspecto</label>
                    <select x-model="mediaForm.aspect_ratio" class="w-full bg-[#121212] border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:border-emerald-500 focus:outline-none">
                        <option value="16:9">16:9 Panorámica</option>
                        <option value="1:1">1:1 Cuadrada</option>
                        <option value="9:16">9:16 Vertical</option>
                        <option value="4:3">4:3 Estándar</option>
                    </select>
                </div>
                <div x-show="mediaForm.mode === 'video'">
                    <label class="block text-xs font-semibold text-gray-300 mb-1">Duración</label>
                    <select x-model="mediaForm.duration" class="w-full bg-[#121212] border border-gray-800 rounded-xl px-3 py-2 text-xs text-white focus:border-purple-500 focus:outline-none">
                        <option value="5">5 Segundos</option>
                        <option value="10">10 Segundos</option>
                    </select>
                </div>
            </div>

            <button type="button"
                    @click="generateMedia()"
                    :disabled="generating || !mediaForm.prompt.trim()"
                    :class="mediaForm.mode === 'image' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-purple-700 hover:bg-purple-600'"
                    class="w-full py-3 rounded-xl disabled:opacity-50 text-white text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : (mediaForm.mode === 'image' ? 'fa-wand-magic-sparkles' : 'fa-film')"></i>
                <span x-text="generating ? (progressNote || 'Generando...') : (mediaForm.mode === 'image' ? 'Generar Imagen y Guardar' : 'Generar Vídeo y Guardar')"></span>
            </button>
        </div>

        <!-- Media Live Preview -->
        <div class="lg:col-span-7 bg-[#181818] p-6 rounded-2xl border border-gray-800 space-y-4">
            <h3 class="text-sm font-bold text-white flex items-center gap-2 border-b border-gray-800 pb-3">
                <i class="fa-solid fa-film text-emerald-400"></i>
                <span>Vista Previa del Archivo Generado</span>
            </h3>

            <template x-if="!mediaResult && !generating">
                <div class="py-16 text-center text-gray-600 space-y-2">
                    <i class="fa-solid fa-film text-3xl opacity-30"></i>
                    <p class="text-xs">Escribe un prompt y pulsa el botón para generar el archivo multimedia.</p>
                </div>
            </template>

            <template x-if="generating">
                <div class="py-16 text-center text-emerald-400 space-y-3">
                    <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
                    <p class="text-xs font-bold" x-text="progressNote"></p>
                    <p class="text-[11px] text-gray-500">Contactando con el motor de IA y procesando el archivo en la mediateca...</p>
                </div>
            </template>

            <template x-if="mediaResult && mediaResult.url">
                <div class="space-y-4">
                    <template x-if="mediaForm.mode === 'image'">
                        <div class="rounded-xl overflow-hidden border border-emerald-500/30 bg-black/60 flex items-center justify-center max-h-96">
                            <img :src="mediaResult.url" :alt="mediaResult.title || 'Generada'" class="max-h-96 w-auto object-contain">
                        </div>
                    </template>
                    <template x-if="mediaForm.mode === 'video'">
                        <div class="rounded-xl overflow-hidden border border-purple-500/30 bg-black/60 flex items-center justify-center max-h-96">
                            <video :src="mediaResult.url" controls class="max-h-96 w-full object-contain"></video>
                        </div>
                    </template>

                    <div class="p-3 bg-[#121212] rounded-xl border border-gray-800 space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400">Proveedor:</span>
                            <span class="font-mono text-[#00FF99] uppercase font-bold" x-text="mediaResult.provider"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-gray-400">URL Permanente:</span>
                            <span class="font-mono text-gray-300 text-[11px] truncate max-w-sm" x-text="mediaResult.url"></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a :href="mediaResult.url" target="_blank"
                           class="flex-1 py-2 px-3 rounded-xl bg-gray-800 hover:bg-gray-700 text-white text-xs font-bold transition-all text-center">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Abrir en Pestaña Nueva
                        </a>
                        <button type="button" @click="navigator.clipboard.writeText(mediaResult.url); toastMessage = 'URL copiada al portapapeles'; toastType = 'success';"
                                class="py-2 px-4 rounded-xl bg-[#00FF99]/20 hover:bg-[#00FF99]/30 text-[#00FF99] border border-[#00FF99]/40 text-xs font-bold transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-copy"></i>
                            <span>Copiar URL</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('aiWorkbench', () => ({
        currentTab: 'page',
        generating: false,
        creating: false,
        testing: false,
        progressNote: '',
        toastMessage: null,
        toastType: 'success',

        init() {
            this.resumePending();
        },

        // Resume a queued generation started before navigating away.
        resumePending() {
            const params = new URLSearchParams(window.location.search);
            const taskId = params.get('task') || localStorage.getItem('ai.workbench.task');

            if (taskId) {
                this.resumeTask(taskId);
            }
        },

        async resumeTask(taskId) {
            this.currentTab = 'page';
            this.generating = true;
            this.progressNote = @js(__('admin.ai.resuming'));

            try {
                this.pageResult = await this.pollGeneration(taskId);
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
                localStorage.removeItem('ai.workbench.task');
            }
        },

        pageForm: {
            type: @js(array_key_first($aiEntities) ?? 'page'),
            prompt: ''
        },
        pageResult: null,
        pageExamples: @js($aiExamples),
        pagePlaceholders: @js($aiPlaceholders),

        briefPlaceholder() {
            return this.pagePlaceholders[this.pageForm.type]
                || (this.pageExamples[this.pageForm.type] || [])[0]
                || @js(__('admin.ai.ph_brief'));
        },

        briefExamples() {
            return this.pageExamples[this.pageForm.type] || [];
        },

        refineForm: {
            action: 'humanize',
            content: '',
            prompt: ''
        },
        refineResult: null,

        orchestratorForm: {
            entity_type: 'post',
            topic: '',
            generate_images: true,
            generate_video: false
        },
        orchestratorResult: null,

        mediaForm: {
            mode: 'image',
            title: '',
            prompt: '',
            aspect_ratio: '16:9',
            duration: 5
        },
        mediaResult: null,

        async testActiveConnection() {
            this.testing = true;
            try {
                const res = await fetch('{{ route("admin.ai_assistant.test_connection") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ provider: '{{ $activeProvider }}' })
                });
                const data = await res.json();
                this.toastType = data.success ? 'success' : 'error';
                this.toastMessage = `[${data.provider.toUpperCase()}] ${data.message} (${data.latency_ms}ms)`;
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = 'Error al probar conexión: ' + e.message;
            } finally {
                this.testing = false;
            }
        },

        // Queue the action and poll until the background worker finishes.
        async requestGeneration(payload) {
            const start = await fetch('{{ route("admin.ai_assistant.generate_async") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    ...payload,
                    return_url: window.location.href
                })
            });
            const started = await start.json();
            if (!started.success) {
                throw new Error(started.question || started.message || started.error || 'No se pudo iniciar la generación.');
            }

            // Persist the task so the result can be resumed after navigating away.
            localStorage.setItem('ai.workbench.task', started.task_id);

            try {
                return await this.pollGeneration(started.task_id);
            } finally {
                localStorage.removeItem('ai.workbench.task');
            }
        },

        async pollGeneration(taskId) {
            const statusUrl = @js(route('admin.ai_assistant.generate_status', ['task' => '__TASK__'])).replace('__TASK__', encodeURIComponent(taskId));
            const deadline = Date.now() + 30 * 60 * 1000;
            let elapsed = 0;

            while (Date.now() < deadline) {
                await new Promise((resolve) => setTimeout(resolve, 2000));
                elapsed += 2;

                const res = await fetch(statusUrl, { headers: { 'Accept': 'application/json' } });
                const state = await res.json();

                if (state.status === 'needs_input') {
                    throw new Error(state.question || state.message || state.data?.question || @js(__('admin.ai.context_clarification')));
                }
                if (!state.success) {
                    throw new Error(state.error || 'Tarea de generación no encontrada.');
                }
                if (state.status === 'completed') {
                    return state.data;
                }
                if (['uncertain', 'approval_required'].includes(state.status)) {
                    const message = state.error || (state.status === 'uncertain'
                        ? 'El resultado es incierto. Revisa la operación antes de volver a ejecutarla.'
                        : 'Se requiere aprobación humana del cambio concreto.');
                    throw new Error(message);
                }
                if (state.status === 'failed') {
                    throw new Error(state.error || 'La generación falló.');
                }

                this.progressNote = `Procesando en segundo plano… ${elapsed}s`;
            }

            throw new Error('La generación superó el tiempo máximo de espera.');
        },

        async generatePageElement() {
            this.generating = true;
            this.progressNote = 'Enviando a la cola…';
            this.pageResult = null;
            try {
                this.pageResult = await this.requestGeneration({
                    action: 'page_element',
                    element_type: this.pageForm.type,
                    prompt: this.pageForm.prompt,
                    context_source: 'prompt'
                });
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async createDraft() {
            if (!this.pageResult) return;
            if (!this.pageResult.resolved_context) {
                this.toastType = 'error';
                this.toastMessage = @js(__('admin.ai.context_clarification'));
                return;
            }
            this.creating = true;
            try {
                const res = await fetch('{{ route("admin.ai_assistant.create_element") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        type: this.pageResult.type,
                        fields: this.pageResult.fields || {},
                        sections: this.pageResult.sections || [],
                        locale: this.pageResult.resolved_context.locale,
                        market: this.pageResult.resolved_context.market
                    })
                });
                const response = await res.json();
                if (response.success) {
                    this.toastType = 'success';
                    this.toastMessage = response.message + ' ' + response.edit_url;
                    window.location.href = response.edit_url;
                } else {
                    this.toastType = 'error';
                    this.toastMessage = response.error || 'No se pudo crear el borrador.';
                }
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.creating = false;
            }
        },

        async executeRefine() {
            this.generating = true;
            this.progressNote = 'Enviando a la cola…';
            this.refineResult = null;
            try {
                this.refineResult = await this.requestGeneration({
                    action: this.refineForm.action,
                    content: this.refineForm.content,
                    prompt: this.refineForm.prompt,
                    context_source: 'prompt'
                });
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async generateMedia() {
            this.generating = true;
            this.progressNote = this.mediaForm.mode === 'image' ? 'Generando imagen fotorrealista…' : 'Sintetizando vídeo cinemático…';
            this.mediaResult = null;
            try {
                this.mediaResult = await this.requestGeneration({
                    action: this.mediaForm.mode === 'image' ? 'image' : 'video',
                    prompt: this.mediaForm.prompt,
                    aspect_ratio: this.mediaForm.aspect_ratio,
                    duration: parseInt(this.mediaForm.duration, 10) || 5,
                    title: this.mediaForm.title || (this.mediaForm.mode === 'image' ? 'Imagen IA' : 'Vídeo IA')
                });
                this.toastType = 'success';
                this.toastMessage = 'Medio generado y guardado en la mediateca.';
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async orchestrateEntity() {
            this.generating = true;
            this.progressNote = 'Investigando fuentes, redactando contenido anti-IA y orquestando bloques…';
            this.orchestratorResult = null;
            try {
                this.orchestratorResult = await this.requestGeneration({
                    action: 'orchestrate_multimodal',
                    entity_type: this.orchestratorForm.entity_type,
                    topic: this.orchestratorForm.topic,
                    context_source: 'prompt',
                    generate_images: this.orchestratorForm.generate_images,
                    generate_video: this.orchestratorForm.generate_video
                });
                this.toastType = 'success';
                this.toastMessage = 'Contenido orquestado exitosamente.';
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.generating = false;
                this.progressNote = '';
            }
        },

        async saveOrchestratedEntity() {
            if (!this.orchestratorResult) return;
            if (!this.orchestratorResult.resolved_context) {
                this.toastType = 'error';
                this.toastMessage = @js(__('admin.ai.context_clarification'));
                return;
            }
            this.creating = true;
            try {
                const res = await fetch('{{ route("admin.ai_assistant.create_element") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        type: this.orchestratorResult.entity_type || this.orchestratorForm.entity_type,
                        fields: {
                            title: this.orchestratorResult.title,
                            summary: this.orchestratorResult.summary,
                            content: this.orchestratorResult.content,
                            featured_image: this.orchestratorResult.featured_image
                        },
                        sections: this.orchestratorResult.blocks || [],
                        locale: this.orchestratorResult.resolved_context.locale,
                        market: this.orchestratorResult.resolved_context.market
                    })
                });
                const response = await res.json();
                if (response.success) {
                    this.toastType = 'success';
                    this.toastMessage = response.message;
                    window.location.href = response.edit_url;
                } else {
                    this.toastType = 'error';
                    this.toastMessage = response.error || 'No se pudo crear la entidad.';
                }
            } catch (e) {
                this.toastType = 'error';
                this.toastMessage = e.message;
            } finally {
                this.creating = false;
            }
        }
    }));
});
</script>
@endsection
