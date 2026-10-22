@extends('admin.layout')

@section('title', __('admin.page_titles.ai_workbench'))

@section('content')
@include('ai_assistant::admin.partials.orchestration')
<div class="admin-page space-y-6" x-data="aiWorkbench({
        initialType: @js(array_key_first($aiEntities) ?? 'page'),
        examples: @js($aiExamples), placeholders: @js($aiPlaceholders),
        resuming: @js(__('admin.ai.resuming')), briefPlaceholder: @js(__('admin.ai.ph_brief')),
        clarification: @js(__('admin.ai.context_clarification')),
        connectionUrl: @js(route('admin.ai_assistant.test_connection')),
        generateUrl: @js(route('admin.ai_assistant.generate_async')),
        statusUrl: @js(route('admin.ai_assistant.generate_status', ['task' => '__TASK__'])),
        createUrl: @js(route('admin.ai_assistant.create_element')),
        csrfToken: @js(csrf_token()), activeProvider: @js($activeProvider)
    })">
    <x-admin.form-header :title="__('admin.ai.page_assistant_title')" :description="__('admin.ai.page_assistant_desc')">
        <x-slot:actions>
            <span class="admin-selection-chip">{{ $isConfigured ? 'Configurado: ' . strtoupper($activeProvider) : 'Configura un proveedor' }}</span>
            <span class="admin-text-muted">{{ __('admin.ai.active_model') }}: {{ $configuredModel }}</span>
            <x-admin.button type="button" @click="testActiveConnection()" x-bind:disabled="testing">
                <i class="fa-solid" :class="testing ? 'fa-spinner fa-spin' : 'fa-plug-circle-bolt'"></i>
                <span x-text="testing ? 'Probando...' : 'Test de Conexión'"></span>
            </x-admin.button>
            <x-admin.button :href="route('admin.modules.settings', ['identifier' => 'ai_assistant'])" icon="fa-solid fa-sliders">{{ __('admin.ai.configure_providers') }}</x-admin.button>
        </x-slot:actions>
    </x-admin.form-header>

    <!-- Feedback Banner -->
    <template x-if="toastMessage">
        <div class="p-4 rounded-xl border flex items-center justify-between transition-all"
             :class="toastType === 'success' ? 'bg-[color:var(--admin-accent)]/10 border-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)]' : 'bg-rose-500/10 border-rose-500/30 text-rose-300'">
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
    <div class="flex border-b border-[color:var(--admin-border)] space-x-2 flex-wrap">
        <button type="button" @click="currentTab = 'page'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'page' ? 'border-[color:var(--admin-accent)] text-[color:var(--admin-accent)] bg-[color:var(--admin-accent)]/5' : 'border-transparent text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'">
            <i class="fa-solid fa-file-circle-plus"></i>
            <span>{{ __('admin.ai.tab_draft') }}</span>
        </button>
        <button type="button" @click="currentTab = 'orchestrator'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'orchestrator' ? 'border-[color:var(--admin-accent)] text-[color:var(--admin-accent)] bg-[color:var(--admin-accent)]/5' : 'border-transparent text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'">
            <i class="fa-solid fa-brain text-[color:var(--admin-accent)]"></i>
            <span>Orquestador Autónomo</span>
        </button>
        <button type="button" @click="currentTab = 'media'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'media' ? 'border-[color:var(--admin-accent)] text-[color:var(--admin-accent)] bg-[color:var(--admin-accent)]/5' : 'border-transparent text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'">
            <i class="fa-solid fa-film text-[color:var(--admin-accent)]"></i>
            <span>Estudio Imagen y Vídeo</span>
        </button>
        <button type="button" @click="currentTab = 'refine'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'refine' ? 'border-[color:var(--admin-accent)] text-[color:var(--admin-accent)] bg-[color:var(--admin-accent)]/5' : 'border-transparent text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'">
            <i class="fa-solid fa-wand-magic"></i>
            <span>{{ __('admin.ai.humanize_seo') }}</span>
        </button>
        <button type="button" @click="currentTab = 'logs'"
                class="px-4 py-3 text-xs font-bold transition-all border-b-2 flex items-center gap-2"
                :class="currentTab === 'logs' ? 'border-[color:var(--admin-accent)] text-[color:var(--admin-accent)] bg-[color:var(--admin-accent)]/5' : 'border-transparent text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <span>{{ __('admin.ai.tab_history') }}</span>
        </button>
        <a href="{{ route('admin.modules.settings', ['identifier' => 'ai_assistant']) }}"
           class="px-4 py-3 text-xs font-bold transition-all border-b-2 border-transparent text-[color:var(--admin-muted)] hover:text-[color:var(--admin-accent)] flex items-center gap-2 ml-auto">
            <i class="fa-solid fa-server text-[color:var(--admin-accent)]"></i>
            <span>{{ __('admin.ai.tab_models') }}</span>
        </a>
    </div>

    <!-- TAB 1: PAGE ASSISTANT (create a complete element as a draft) -->
    <div x-show="currentTab === 'page'" class="grid grid-cols-1 lg:grid-cols-12 gap-6" style="display: none;">
        <div class="lg:col-span-5 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-5">
            <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-file-circle-plus text-[color:var(--admin-accent)]"></i>
                <span>{{ __('admin.ai.create_element') }}</span>
            </h2>

            <div>
                <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">{{ __('admin.ai.element_type') }}</label>
                <select x-model="pageForm.type" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl px-3 py-2 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none">
                    @foreach($aiEntities as $entityType => $entityLabel)
                        <option value="{{ $entityType }}">{{ $entityLabel }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">{{ __('admin.ai.brief') }}</label>
                <textarea x-model="pageForm.prompt"
                          rows="7"
                          class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-3 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none resize-none placeholder-[color:var(--admin-muted)] font-sans leading-relaxed"
                          :placeholder="briefPlaceholder()"></textarea>
                <p class="mt-2 text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.ai.prompt_context_hint') }}</p>

                <!-- Example briefs per entity type (helps to start writing) -->
                <div class="flex flex-wrap gap-1.5 mt-2">
                    <template x-for="(example, exIdx) in briefExamples()" :key="exIdx">
                        <button type="button"
                                @click="pageForm.prompt = example"
                                class="px-2 py-1 rounded-lg bg-[color:var(--admin-surface)] hover:bg-[color:var(--admin-accent)]/20 border border-[color:var(--admin-border)] hover:border-[color:var(--admin-accent)]/40 text-[color:var(--admin-muted)] hover:text-[color:var(--admin-accent)] text-[10px] transition-all text-left"
                                x-text="example"></button>
                    </template>
                </div>
            </div>

            <button type="button"
                    @click="generatePageElement()"
                    :disabled="generating || !pageForm.prompt.trim()"
                    class="w-full py-2.5 rounded-xl bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)] disabled:opacity-50 text-[color:var(--admin-on-accent)] text-xs font-bold transition-all shadow-lg shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                <span x-text="generating ? (progressNote || 'Generando...') : @js(__('admin.ai.generate_element'))"></span>
            </button>
        </div>

        <div class="lg:col-span-7 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-4">
            <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-eye text-[color:var(--admin-accent)]"></i>
                <span>{{ __('admin.ai.preview') }}</span>
            </h3>

            <div x-show="!pageResult" class="py-24 text-center text-[color:var(--admin-muted)]">
                <i class="fa-solid fa-file-circle-plus text-3xl mb-3 block opacity-40"></i>
                <p class="text-xs" x-text="generating ? (progressNote || 'Generando...') : @js(__('admin.ai.page_assistant_hint'))"></p>
            </div>

            <template x-if="pageResult">
                <div class="space-y-4">
                    <p x-show="pageResult.resolved_context" class="text-[11px] text-[color:var(--admin-muted)]">
                        {{ __('admin.ai.language') }}: <span x-text="pageResult.resolved_context?.locale"></span>
                        · {{ __('admin.ai.market') }}: <span x-text="pageResult.resolved_context?.market"></span>
                    </p>
                    <div class="p-3 rounded-xl bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/20 text-xs text-[color:var(--admin-accent)] flex items-center justify-between">
                        <span x-show="Number.isFinite(pageResult.anti_ai_audit?.anti_ai_score)">{{ __('admin.ai.anti_ai_quality') }} <strong x-text="pageResult.anti_ai_audit?.anti_ai_score"></strong>/100</span>
                        <span class="text-[11px] text-amber-300">{{ __('admin.ai.draft_notice') }}</span>
                    </div>

                    <div class="bg-[color:var(--admin-surface)] p-4 rounded-xl border border-[color:var(--admin-border)] space-y-3">
                        <div x-show="pageResult.fields?.title">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.field_title') }}</span>
                            <p class="text-sm text-[color:var(--admin-text)] font-semibold" x-text="pageResult.fields?.title"></p>
                        </div>
                        <div x-show="pageResult.fields?.summary">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.field_summary') }}</span>
                            <p class="text-xs text-[color:var(--admin-text)]" x-text="pageResult.fields?.summary"></p>
                        </div>
                        <div x-show="pageResult.fields?.body">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.field_content') }}</span>
                            <div class="text-xs text-[color:var(--admin-text)] leading-relaxed whitespace-pre-line max-h-56 overflow-y-auto" x-text="pageResult.fields?.body"></div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div class="bg-[color:var(--admin-surface)] p-3 rounded-xl border border-[color:var(--admin-border)]" x-show="pageResult.fields?.meta_title">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.seo_title') }}</span>
                            <p class="text-xs text-[color:var(--admin-text)]" x-text="pageResult.fields?.meta_title"></p>
                        </div>
                        <div class="bg-[color:var(--admin-surface)] p-3 rounded-xl border border-[color:var(--admin-border)]" x-show="pageResult.fields?.meta_description">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.meta_description_label') }}</span>
                            <p class="text-xs text-[color:var(--admin-text)]" x-text="pageResult.fields?.meta_description"></p>
                        </div>
                    </div>

                    <template x-if="pageResult.sections && pageResult.sections.length">
                        <div class="bg-[color:var(--admin-surface)] p-3 rounded-xl border border-[color:var(--admin-border)]">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-2">
                                {{ __('admin.ai.composed_blocks') }} (<span x-text="pageResult.sections.length"></span>)
                            </span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="(section, sIdx) in pageResult.sections" :key="sIdx">
                                    <span class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/15 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/30 text-[10px] font-mono" x-text="section.type"></span>
                                </template>
                            </div>
                        </div>
                    </template>

                    <button type="button"
                            @click="createDraft()"
                            :disabled="creating"
                            class="w-full py-2.5 rounded-xl bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)] disabled:opacity-50 text-[color:var(--admin-on-accent)] text-xs font-bold transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid" :class="creating ? 'fa-spinner fa-spin' : 'fa-file-arrow-down'"></i>
                        <span x-text="creating ? 'Creando...' : @js(__('admin.ai.create_draft'))"></span>
                    </button>
                </div>
            </template>
        </div>
    </div>

    <!-- TAB 2: REFINE (humanize / proofread / SEO) -->
    <div x-show="currentTab === 'refine'" class="grid grid-cols-1 lg:grid-cols-12 gap-6" style="display: none;">
        <div class="lg:col-span-6 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-4">
            <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-align-left text-[color:var(--admin-accent)]"></i>
                <span>{{ __('admin.ai.original_text') }}</span>
            </h2>

            <div>
                <div>
                    <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">{{ __('admin.ai.desired_action') }}</label>
                    <select x-model="refineForm.action" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl px-3 py-2 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none">
                        <option value="humanize">{{ __('admin.ai.action_humanize') }}</option>
                        <option value="proofread">{{ __('admin.ai.action_grammar') }}</option>
                        <option value="seo">{{ __('admin.ai.gen_seo') }}</option>
                    </select>
                </div>
            </div>

            <textarea x-model="refineForm.content"
                      rows="10"
                      class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-3 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none resize-none placeholder-[color:var(--admin-muted)] font-sans leading-relaxed"
                      placeholder="{{ __('admin.ai.ph_original_text') }}"></textarea>

            <div>
                <label for="ai-refine-instruction" class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">{{ __('admin.ai.optional_instruction') }}</label>
                <textarea id="ai-refine-instruction" x-model="refineForm.prompt" rows="2" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-3 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none resize-none"></textarea>
                <p class="mt-2 text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.ai.prompt_context_hint') }}</p>
            </div>

            <button type="button"
                    @click="executeRefine()"
                    :disabled="generating || !refineForm.content.trim()"
                    class="w-full py-2.5 rounded-xl bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)] disabled:opacity-50 text-[color:var(--admin-on-accent)] text-xs font-bold transition-all shadow-lg shadow-sm flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                <span x-text="generating ? (progressNote || 'Procesando...') : 'Ejecutar Mejora Inteligente'"></span>
            </button>
        </div>

        <div class="lg:col-span-6 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-4">
            <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-[color:var(--admin-accent)]"></i>
                <span>{{ __('admin.ai.optimized') }}</span>
            </h3>

            <div x-show="!refineResult" class="py-24 text-center text-[color:var(--admin-muted)]">
                <i class="fa-solid fa-sliders text-3xl mb-3 block opacity-40"></i>
                <p class="text-xs" x-text="generating ? (progressNote || 'Procesando...') : @js(__('admin.ai.action_hint'))"></p>
            </div>

            <div x-show="refineResult" class="space-y-4">
                <template x-if="refineResult && refineResult.humanized">
                    <div>
                        <div class="p-3 mb-3 rounded-xl bg-[color:var(--admin-accent)]/10 border border-[color:var(--admin-accent)]/20 text-xs text-[color:var(--admin-accent)] flex items-center justify-between">
                            <span x-show="Number.isFinite(refineResult.anti_ai_score)">{{ __('admin.ai.anti_ai_quality') }} <strong x-text="refineResult.anti_ai_score"></strong>/100</span>
                            <span class="text-[11px] text-[color:var(--admin-muted)]" x-text="refineResult.cliches_removed.length + ' clichés eliminados'"></span>
                        </div>
                        <div class="bg-[color:var(--admin-surface)] p-4 rounded-xl border border-[color:var(--admin-border)] text-xs text-[color:var(--admin-text)] leading-relaxed whitespace-pre-line"
                             x-text="refineResult.humanized"></div>
                    </div>
                </template>

                <template x-if="refineResult && refineResult.improved">
                    <div class="bg-[color:var(--admin-surface)] p-4 rounded-xl border border-[color:var(--admin-border)] text-xs text-[color:var(--admin-text)] leading-relaxed whitespace-pre-line"
                         x-text="refineResult.improved"></div>
                </template>

                <template x-if="refineResult && refineResult.meta_title">
                    <div class="space-y-3">
                        <div class="bg-[color:var(--admin-surface)] p-3 rounded-xl border border-[color:var(--admin-border)]">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.seo_title') }}</span>
                            <p class="text-xs text-[color:var(--admin-text)] font-semibold" x-text="refineResult.meta_title"></p>
                        </div>
                        <div class="bg-[color:var(--admin-surface)] p-3 rounded-xl border border-[color:var(--admin-border)]">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.meta_description_label') }}</span>
                            <p class="text-xs text-[color:var(--admin-text)]" x-text="refineResult.meta_description"></p>
                        </div>
                        <div class="bg-[color:var(--admin-surface)] p-3 rounded-xl border border-[color:var(--admin-border)]">
                            <span class="text-[10px] text-[color:var(--admin-muted)] uppercase font-bold block mb-1">{{ __('admin.ai.keywords') }}</span>
                            <div class="flex flex-wrap gap-1.5 mt-1">
                                <template x-for="kw in refineResult.keywords" :key="kw">
                                    <span class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/20 text-[color:var(--admin-accent)] text-[10px]" x-text="kw"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <!-- TAB 3: HISTORY -->
    <div x-show="currentTab === 'logs'" class="bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-4" style="display: none;">
        <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
            <i class="fa-solid fa-clock-rotate-left text-[color:var(--admin-accent)]"></i>
            <span>{{ __('admin.ai.history_title') }}</span>
        </h2>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-[color:var(--admin-muted)]">
                <thead class="text-[10px] uppercase bg-[color:var(--admin-surface)] text-[color:var(--admin-muted)] border-b border-[color:var(--admin-border)]">
                    <tr>
                        <th class="py-3 px-4">{{ __('admin.ai.action') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.provider_model') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.tokens') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.latency') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.language') }}</th>
                        <th class="py-3 px-4">{{ __('admin.ai.date') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[color:var(--admin-border)]/60">
                    @forelse($recentLogs as $log)
                        <tr class="hover:bg-[color:var(--admin-surface-raised)]/30 transition-colors">
                            <td class="py-3 px-4 font-semibold text-[color:var(--admin-text)]">
                                <span class="px-2 py-0.5 rounded bg-[color:var(--admin-accent)]/10 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/20 text-[10px] uppercase">
                                    {{ $log->action }}
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="text-[color:var(--admin-text)]">{{ strtoupper($log->provider) }}</span>
                                <span class="text-[color:var(--admin-muted)] text-[11px] block">{{ $log->model }}</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px]">
                                {{ number_format($log->total_tokens) }}
                                <span class="text-[color:var(--admin-muted)] text-[10px]">({{ $log->prompt_tokens }} / {{ $log->completion_tokens }})</span>
                            </td>
                            <td class="py-3 px-4 font-mono text-[11px] text-[color:var(--admin-text)]">
                                {{ $log->latency_ms }} ms
                            </td>
                            <td class="py-3 px-4 uppercase text-[11px] font-bold text-[color:var(--admin-muted)]">
                                {{ $log->locale }} ({{ $log->market }})
                            </td>
                            <td class="py-3 px-4 text-[11px] text-[color:var(--admin-muted)]">
                                {{ $log->created_at->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-8 text-center text-[color:var(--admin-muted)]">
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
        <div class="lg:col-span-5 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-5">
            <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-brain text-[color:var(--admin-accent)]"></i>
                <span>Orquestación de Entidad Multimodal</span>
            </h2>
            <p class="text-xs text-[color:var(--admin-muted)]">
                La IA investiga fuentes reales en internet, redacta contenido natural anti-IA, sintetiza imágenes o vídeos y compone la estructura completa de bloques para dbCMS.
            </p>

            <div>
                <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">Tipo de Entidad</label>
                <select x-model="orchestratorForm.entity_type" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl px-3 py-2 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none">
                    <option value="post">Artículo de Blog (con imagen destacada, bloques y SEO)</option>
                    <option value="page">Página Modular / Landing (con Hero, bloques interactivos y CTA)</option>
                </select>
            </div>

            <div>
                <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">Tema, Enfoque o Instrucción Natural</label>
                <textarea x-model="orchestratorForm.topic"
                          rows="4"
                          placeholder="Ej: Investiga las novedades de Laravel 11 y crea un post detallado explicando sus ventajas para empresas con ejemplos de código y bloques destacados..."
                          class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-3 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none resize-none leading-relaxed"></textarea>
            </div>

            <p class="text-[11px] text-[color:var(--admin-muted)]">{{ __('admin.ai.prompt_context_hint') }}</p>

            <div class="space-y-2 pt-2 border-t border-[color:var(--admin-border)]">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="orchestratorForm.generate_image" class="rounded border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0">
                    <span class="text-xs text-[color:var(--admin-text)]">Generar Imagen con IA (Magnific / DALL-E / Imagen)</span>
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" x-model="orchestratorForm.generate_video" class="rounded border-[color:var(--admin-border)] text-[color:var(--admin-accent)] focus:ring-0">
                    <span class="text-xs text-[color:var(--admin-text)]">Generar Vídeo Cinemático con IA (Higgsfield / Magnific)</span>
                </label>
            </div>

            <button type="button"
                    @click="orchestrateEntity()"
                    :disabled="generating || !orchestratorForm.topic.trim()"
                    class="w-full py-3 rounded-xl bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)] disabled:opacity-50 text-[color:var(--admin-on-accent)] text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-brain'"></i>
                <span x-text="generating ? (progressNote || 'Orquestando…') : 'Iniciar Orquestación Autónoma'"></span>
            </button>
        </div>

        <!-- Orchestrator Live Result Preview -->
        <div class="lg:col-span-7 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-4">
            <div class="flex items-center justify-between border-b border-[color:var(--admin-border)] pb-3">
                <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                    <i class="fa-solid fa-eye text-[color:var(--admin-accent)]"></i>
                    <span>Resultado de la Orquestación</span>
                </h3>
                <template x-if="orchestratorResult">
                    <button type="button"
                            @click="saveOrchestratedEntity()"
                            :disabled="creating"
                            class="px-3.5 py-1.5 rounded-lg bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)] text-[color:var(--admin-on-accent)] text-xs font-bold transition-all flex items-center gap-1.5">
                        <i class="fa-solid" :class="creating ? 'fa-spinner fa-spin' : 'fa-circle-check'"></i>
                        <span x-text="creating ? 'Guardando...' : 'Crear en dbCMS'"></span>
                    </button>
                </template>
            </div>

            <template x-if="!orchestratorResult && !generating">
                <div class="py-16 text-center text-[color:var(--admin-muted)] space-y-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-3xl opacity-30"></i>
                    <p class="text-xs">Introduce una instrucción y pulsa "Iniciar Orquestación Autónoma".</p>
                </div>
            </template>

            <template x-if="generating">
                <div class="py-16 text-center text-[color:var(--admin-accent)] space-y-3">
                    <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
                    <p class="text-xs font-bold" x-text="progressNote"></p>
                    <p class="text-[11px] text-[color:var(--admin-muted)]">Investigando fuentes, redactando contenido anti-IA y creando medios...</p>
                </div>
            </template>

            <template x-if="orchestratorResult">
                <div class="space-y-4">
                    <p x-show="orchestratorResult.resolved_context" class="text-[11px] text-[color:var(--admin-muted)]">
                        {{ __('admin.ai.language') }}: <span x-text="orchestratorResult.resolved_context?.locale"></span>
                        · {{ __('admin.ai.market') }}: <span x-text="orchestratorResult.resolved_context?.market"></span>
                    </p>
                    <!-- Title & Summary -->
                    <div class="space-y-2">
                        <h4 class="text-base font-bold text-[color:var(--admin-text)]" x-text="orchestratorResult.title"></h4>
                        <p class="text-xs text-[color:var(--admin-muted)] italic" x-text="orchestratorResult.summary"></p>
                    </div>

                    <!-- Media item if generated -->
                    <template x-if="orchestratorResult.featured_image_url">
                        <div class="rounded-xl overflow-hidden border border-[color:var(--admin-accent)]/30 bg-[color:var(--admin-surface)]/60 max-h-56 flex items-center justify-center">
                            <img :src="orchestratorResult.featured_image_url" alt="Imagen destacada" class="max-h-56 w-auto object-contain">
                        </div>
                    </template>
                    <template x-if="orchestratorResult.video_url">
                        <div class="rounded-xl overflow-hidden border border-[color:var(--admin-accent)]/30 bg-[color:var(--admin-surface)]/60 max-h-56 flex items-center justify-center">
                            <video :src="orchestratorResult.video_url" controls class="max-h-56 w-full object-contain"></video>
                        </div>
                    </template>

                    <!-- Content excerpt -->
                    <div class="p-3 bg-[color:var(--admin-surface)] rounded-xl border border-[color:var(--admin-border)] text-xs text-[color:var(--admin-text)] max-h-48 overflow-y-auto whitespace-pre-line leading-relaxed"
                         x-text="orchestratorResult.content"></div>

                    <!-- Synthesized Blocks -->
                    <template x-if="Array.isArray(orchestratorResult.sections) && orchestratorResult.sections.length > 0">
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-bold text-[color:var(--admin-muted)]">
                                <span>Bloques Modulares Creados (<span x-text="orchestratorResult.sections.length"></span>)</span>
                            </div>
                            <div class="space-y-1.5 max-h-44 overflow-y-auto pr-1">
                                <template x-for="(b, idx) in orchestratorResult.sections" :key="idx">
                                    <div class="p-2 rounded-lg bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] flex items-center justify-between text-xs">
                                        <div class="flex items-center gap-2">
                                            <span class="w-5 h-5 rounded bg-[color:var(--admin-accent)]/20 text-[color:var(--admin-accent)] flex items-center justify-center font-mono text-[10px]" x-text="idx + 1"></span>
                                            <span class="font-bold text-[color:var(--admin-text)] uppercase text-[11px]" x-text="b.type"></span>
                                            <span class="text-[color:var(--admin-muted)] text-[11px] truncate max-w-xs" x-text="b.title || b.headline || ''"></span>
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
        <div class="lg:col-span-5 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-5">
            <h2 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2">
                <i class="fa-solid fa-film text-[color:var(--admin-accent)]"></i>
                <span>Estudio de Creación de Medios IA</span>
            </h2>
            <p class="text-xs text-[color:var(--admin-muted)]">
                Genera imágenes fotorrealistas con Magnific AI / DALL-E / Imagen o vídeos cinemáticos con Higgsfield AI y guárdalos automáticamente en la mediateca de dbCMS.
            </p>

            <!-- Mode selector: Image vs Video -->
            <div class="flex items-center gap-2 p-1 bg-[color:var(--admin-surface)] rounded-xl border border-[color:var(--admin-border)]">
                <button type="button" @click="mediaForm.mode = 'image'"
                        :class="mediaForm.mode === 'image' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                        class="flex-1 py-1.5 rounded-lg text-xs transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-image"></i>
                    <span>Imagen (Magnific / DALL-E)</span>
                </button>
                <button type="button" @click="mediaForm.mode = 'video'"
                        :class="mediaForm.mode === 'video' ? 'bg-[color:var(--admin-accent)] text-[color:var(--admin-on-accent)] font-bold' : 'text-[color:var(--admin-muted)] hover:text-[color:var(--admin-text)]'"
                        class="flex-1 py-1.5 rounded-lg text-xs transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-video"></i>
                    <span>Vídeo (Higgsfield / Magnific)</span>
                </button>
            </div>

            <div>
                <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">Título en la Mediateca</label>
                <input type="text" x-model="mediaForm.title" placeholder="Ej: Paisaje ciberpunk de noche..."
                       class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl px-3 py-2 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none">
            </div>

            <div>
                <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">Prompt Detallado</label>
                <textarea x-model="mediaForm.prompt"
                          rows="4"
                          :placeholder="mediaForm.mode === 'image' ? 'Describe la imagen con iluminación, atmósfera, estilo de cámara...' : 'Describe el movimiento de cámara (travelling, paneo), acción, ángulo...'"
                          class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl p-3 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none resize-none leading-relaxed"></textarea>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">Formato / Aspecto</label>
                    <select x-model="mediaForm.aspect_ratio" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl px-3 py-2 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none">
                        <option value="16:9">16:9 Panorámica</option>
                        <option value="1:1">1:1 Cuadrada</option>
                        <option value="9:16">9:16 Vertical</option>
                        <option value="4:3">4:3 Estándar</option>
                    </select>
                </div>
                <div x-show="mediaForm.mode === 'video'">
                    <label class="admin-field-label block text-xs font-semibold text-[color:var(--admin-text)] mb-1">Duración</label>
                    <select x-model="mediaForm.duration" class="admin-control w-full bg-[color:var(--admin-surface)] border border-[color:var(--admin-border)] rounded-xl px-3 py-2 text-xs text-[color:var(--admin-text)] focus:border-[color:var(--admin-accent)] focus:outline-none">
                        <option value="5">5 Segundos</option>
                        <option value="10">10 Segundos</option>
                    </select>
                </div>
            </div>

            <button type="button"
                    @click="generateMedia()"
                    :disabled="generating || !mediaForm.prompt.trim()"
                    :class="mediaForm.mode === 'image' ? 'bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)]' : 'bg-[color:var(--admin-accent)] hover:bg-[color:var(--admin-accent-hover)]'"
                    class="w-full py-3 rounded-xl disabled:opacity-50 text-[color:var(--admin-text)] text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : (mediaForm.mode === 'image' ? 'fa-wand-magic-sparkles' : 'fa-film')"></i>
                <span x-text="generating ? (progressNote || 'Generando...') : (mediaForm.mode === 'image' ? 'Generar Imagen y Guardar' : 'Generar Vídeo y Guardar')"></span>
            </button>
        </div>

        <!-- Media Live Preview -->
        <div class="lg:col-span-7 bg-[color:var(--admin-surface)] p-6 rounded-2xl border border-[color:var(--admin-border)] space-y-4">
            <h3 class="text-sm font-bold text-[color:var(--admin-text)] flex items-center gap-2 border-b border-[color:var(--admin-border)] pb-3">
                <i class="fa-solid fa-film text-[color:var(--admin-accent)]"></i>
                <span>Vista Previa del Archivo Generado</span>
            </h3>

            <template x-if="!mediaResult && !generating">
                <div class="py-16 text-center text-[color:var(--admin-muted)] space-y-2">
                    <i class="fa-solid fa-film text-3xl opacity-30"></i>
                    <p class="text-xs">Escribe un prompt y pulsa el botón para generar el archivo multimedia.</p>
                </div>
            </template>

            <template x-if="generating">
                <div class="py-16 text-center text-[color:var(--admin-accent)] space-y-3">
                    <i class="fa-solid fa-spinner fa-spin text-3xl"></i>
                    <p class="text-xs font-bold" x-text="progressNote"></p>
                    <p class="text-[11px] text-[color:var(--admin-muted)]">Contactando con el motor de IA y procesando el archivo en la mediateca...</p>
                </div>
            </template>

            <template x-if="mediaResult && mediaResult.url">
                <div class="space-y-4">
                    <template x-if="mediaForm.mode === 'image'">
                        <div class="rounded-xl overflow-hidden border border-[color:var(--admin-accent)]/30 bg-[color:var(--admin-surface)]/60 flex items-center justify-center max-h-96">
                            <img :src="mediaResult.url" :alt="mediaResult.title || 'Generada'" class="max-h-96 w-auto object-contain">
                        </div>
                    </template>
                    <template x-if="mediaForm.mode === 'video'">
                        <div class="rounded-xl overflow-hidden border border-[color:var(--admin-accent)]/30 bg-[color:var(--admin-surface)]/60 flex items-center justify-center max-h-96">
                            <video :src="mediaResult.url" controls class="max-h-96 w-full object-contain"></video>
                        </div>
                    </template>

                    <div class="p-3 bg-[color:var(--admin-surface)] rounded-xl border border-[color:var(--admin-border)] space-y-2">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-[color:var(--admin-muted)]">Proveedor:</span>
                            <span class="font-mono text-[color:var(--admin-accent)] uppercase font-bold" x-text="mediaResult.provider"></span>
                        </div>
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-[color:var(--admin-muted)]">URL Permanente:</span>
                            <span class="font-mono text-[color:var(--admin-text)] text-[11px] truncate max-w-sm" x-text="mediaResult.url"></span>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <a :href="mediaResult.url" target="_blank"
                           class="flex-1 py-2 px-3 rounded-xl bg-[color:var(--admin-surface-raised)] hover:bg-[color:var(--admin-surface-raised)] text-[color:var(--admin-text)] text-xs font-bold transition-all text-center">
                            <i class="fa-solid fa-arrow-up-right-from-square mr-1"></i> Abrir en Pestaña Nueva
                        </a>
                        <button type="button" @click="navigator.clipboard.writeText(mediaResult.url); toastMessage = 'URL copiada al portapapeles'; toastType = 'success';"
                                class="py-2 px-4 rounded-xl bg-[color:var(--admin-accent)]/20 hover:bg-[color:var(--admin-accent)]/30 text-[color:var(--admin-accent)] border border-[color:var(--admin-accent)]/40 text-xs font-bold transition-all flex items-center gap-1.5">
                            <i class="fa-solid fa-copy"></i>
                            <span>Copiar URL</span>
                        </button>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>

@endsection
