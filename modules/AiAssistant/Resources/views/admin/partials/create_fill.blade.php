@php
    /** @var \App\Core\Content\EntitySchema|null $schema */
    $schema = app(\App\Core\Content\Contracts\EntitySchemaRegistryInterface::class)->get($entityType);
@endphp

@if($schema !== null)
@once
<script nonce="{{ $cspNonce ?? '' }}">
document.addEventListener('alpine:init', () => {
    Alpine.data('aiCreateFill', (config) => ({
        open: false,
        brief: '',
        generating: false,
        error: null,
        progress: '',
        config,

        init() {
            const taskId = localStorage.getItem(this.storageKey());
            if (taskId) {
                this.resume(taskId);
            }
        },

        storageKey() {
            return 'ai.create.task.' + this.config.type;
        },

        async resume(taskId) {
            this.open = true;
            this.generating = true;
            this.progress = @js(__('admin.ai.resuming'));

            try {
                const data = await this.poll(taskId);
                this.fill(data);
            } catch (e) {
                this.error = e.message || @js(__('admin.ai.fill_error'));
            } finally {
                this.generating = false;
                this.progress = '';
                localStorage.removeItem(this.storageKey());
            }
        },

        async generate() {
            this.generating = true;
            this.error = null;
            this.progress = @js(__('admin.ai.queued'));

            try {
                const taskId = await this.enqueue();
                localStorage.setItem(this.storageKey(), taskId);
                const data = await this.poll(taskId);
                this.fill(data);
            } catch (e) {
                this.error = e.message || @js(__('admin.ai.fill_error'));
            } finally {
                this.generating = false;
                this.progress = '';
                localStorage.removeItem(this.storageKey());
            }
        },

        async enqueue() {
            const context = window.cmsContext || {};
            const res = await fetch(@js(route('admin.ai_assistant.generate_async')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrf(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    action: 'page_element',
                    element_type: this.config.type,
                    prompt: this.brief,
                    context_source: 'editor',
                    locale: context.locale || this.config.locale,
                    editor_market_ids: Array.isArray(context.marketIds)
                        ? context.marketIds.map(Number).filter((id) => Number.isInteger(id) && id > 0)
                        : [],
                    return_url: window.location.href
                })
            });
            const data = await res.json();
            if (!data.success) {
                throw new Error(data.question || data.message || data.error || @js(__('admin.ai.fill_error')));
            }
            return data.task_id;
        },

        async poll(taskId) {
            const url = @js(route('admin.ai_assistant.generate_status', ['task' => '__TASK__'])).replace('__TASK__', encodeURIComponent(taskId));
            for (let elapsed = 0; elapsed < 1800; elapsed += 2) {
                await new Promise((resolve) => setTimeout(resolve, 2000));
                const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const state = await res.json();
                if (state.status === 'needs_input') {
                    throw new Error(state.question || state.message || state.data?.question || @js(__('admin.ai.context_clarification')));
                }
                if (!state.success) {
                    throw new Error(state.error || @js(__('admin.ai.fill_error')));
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
                    throw new Error(state.error || @js(__('admin.ai.fill_error')));
                }
                this.progress = @js(__('admin.ai.processing')) + ' ' + (elapsed + 2) + 's';
            }
            throw new Error(@js(__('admin.ai.fill_error')));
        },

        fill(data) {
            const context = window.cmsContext || {};
            const locale = data.resolved_context?.locale || this.config.locale;
            if (data.resolved_context) {
                const markets = (context.marketIds || []).map(Number).sort((a, b) => a - b);
                const generatedMarkets = [...(data.resolved_context.market_ids || [])].sort((a, b) => a - b);
                if (locale !== (context.locale || this.config.locale) || JSON.stringify(markets) !== JSON.stringify(generatedMarkets)) {
                    throw new Error(@js(__('admin.ai.editor_context_changed')));
                }
            }
            const fields = data.fields || {};

            Object.entries(this.config.fields).forEach(([role, spec]) => {
                const value = fields[role];
                if (value === undefined || value === null || value === '') {
                    return;
                }
                this.writeInput(String(spec.input).replace('{locale}', locale), value);
            });

            if (this.config.sectionable && Array.isArray(data.sections) && data.sections.length) {
                window.dispatchEvent(new CustomEvent('cms-replace-blocks', { detail: { blocks: data.sections } }));
            }

            this.open = false;
        },

        writeInput(name, value) {
            const el = document.querySelector('[name="' + name + '"]');
            if (!el) {
                return;
            }
            if ('value' in el) {
                el.value = value;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                window.dispatchEvent(new CustomEvent('cms-ai-insert', { detail: { target: el, text: value, mode: 'replace' } }));
            }
        },

        csrf() {
            return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @js(csrf_token());
        }
    }));
});
</script>
@endonce

<div x-data="aiCreateFill({
        type: @js($schema->type),
        label: @js($schema->label),
        fields: @js($schema->fields),
        sectionable: @js($schema->sectionable),
        locale: @js($locale ?? app()->getLocale())
     })"
     class="inline-block">

    <button type="button"
            @click="open = true"
            class="px-3 py-1.5 rounded-lg bg-purple-600/20 hover:bg-purple-600/30 border border-purple-500/40 text-purple-300 hover:text-white text-xs font-semibold transition-all flex items-center gap-2">
        <i class="fa-solid fa-wand-magic-sparkles"></i>
        <span>{{ __('admin.ai.create_with_ai') }}</span>
    </button>

    <div x-show="open" x-cloak
         @click.self="open = false"
         @keydown.escape.window="open = false"
         class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
         style="display: none;">
        <div class="bg-[#141414] border border-purple-500/40 rounded-2xl p-6 w-full max-w-lg space-y-4 text-xs text-white font-mono" @click.stop>
            <div class="flex items-center justify-between pb-3 border-b border-gray-800">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-wand-magic-sparkles text-purple-400"></i>
                    <h3 class="text-sm font-bold">{{ __('admin.ai.create_with_ai') }} · <span x-text="config.label"></span></h3>
                </div>
                <button type="button" @click="open = false" class="text-gray-400 hover:text-white text-xl">&times;</button>
            </div>

            <textarea x-model="brief"
                      rows="5"
                      class="w-full bg-[#1A1A1A] border border-gray-700 rounded-xl p-3 text-xs text-white focus:border-purple-500 focus:outline-none resize-none"
                      placeholder="{{ $placeholder ?? __('admin.ai.ph_brief') }}"></textarea>

            <div x-show="error" class="p-3 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-xs" x-text="error"></div>

            <button type="button"
                    @click="generate()"
                    :disabled="generating || !brief.trim()"
                    class="w-full py-2.5 rounded-xl bg-purple-600 hover:bg-purple-500 disabled:opacity-50 text-white text-xs font-bold transition-all flex items-center justify-center gap-2">
                <i class="fa-solid" :class="generating ? 'fa-spinner fa-spin' : 'fa-wand-magic-sparkles'"></i>
                <span x-text="generating ? (progress || @js(__('admin.ai.generating'))) : @js(__('admin.ai.generate'))"></span>
            </button>

            <p class="text-[10px] text-gray-500">{{ __('admin.ai.create_fill_hint') }}</p>
            <p class="text-[10px] text-gray-500">{{ __('admin.ai.editor_context_hint') }}</p>
        </div>
    </div>
</div>
@endif
