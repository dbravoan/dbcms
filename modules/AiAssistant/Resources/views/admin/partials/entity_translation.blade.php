@php
    $translationSchema = app(\Modules\AiAssistant\Services\AiTaxonomyService::class)->entityForView(request()->route()?->getName());
    $translationAllowed = $translationSchema && auth()->user()?->hasPermission('use:ai') && ($translationSchema->permission === null || auth()->user()?->hasPermission($translationSchema->permission));
    $translationEntityId = null;
    if ($translationSchema) {
        foreach (request()->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof \Illuminate\Database\Eloquent\Model && is_a($parameter, $translationSchema->model)) {
                $translationEntityId = $parameter->getKey();
            }
        }
    }
@endphp
@if($translationAllowed)
<div x-data="cmsEntityTranslationComponent({
        entity: @js($translationSchema->type), entityId: @js($translationEntityId), fields: @js($translationSchema->fields),
        languages: @js(($aiTranslationLanguages ?? $activeLanguages ?? collect())->map(fn ($language) => ['code' => $language->code, 'name' => $language->name])->values()),
        startUrl: @js(route('admin.ai_assistant.generate_async')), statusUrl: @js(route('admin.ai_assistant.generate_status', ['task' => '__TASK__'])),
        csrf: @js(csrf_token()), messages: @js(__('ai.translation'))
     })" @cms-translate-open.window="open($event.detail.form)">
    <x-admin.modal show="show" :title="__('ai.translation.title')" icon="fa-solid fa-language" maxWidth="3xl">
        <p class="admin-text-muted">{{ __('ai.translation.help') }}</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <x-admin.select name="source_locale" id="cms-ai-translation-source" :label="__('ai.translation.source')" :error="false" x-model="sourceLocale" x-bind:disabled="busy" @change="result = null; replaceReviewed = false">
                    <template x-for="language in eligibleLanguages" :key="language.code"><option :value="language.code" x-text="language.name"></option></template>
            </x-admin.select>
            <x-admin.select name="target_locale" id="cms-ai-translation-target" :label="__('ai.translation.target')" :error="false" x-model="targetLocale" x-bind:disabled="busy" @change="result = null; replaceReviewed = false">
                    <option value="">{{ __('ai.translation.choose') }}</option>
                    <template x-for="language in eligibleLanguages" :key="language.code"><option :value="language.code" :disabled="language.code === sourceLocale" x-text="language.name"></option></template>
            </x-admin.select>
        </div>
        <p x-show="error" x-text="error" role="alert" class="admin-alert admin-alert--danger whitespace-pre-wrap"></p>
        <x-admin.button variant="primary" icon="fa-solid fa-language" @click="generate()" x-bind:disabled="busy || !targetLocale || sourceLocale === targetLocale" x-bind:aria-busy="busy">
            <span x-text="busy ? config.messages.working : config.messages.generate"></span>
        </x-admin.button>
        <div x-show="result" class="space-y-4">
            <p class="admin-text-muted">{{ __('ai.translation.preview') }}</p>
            <div class="admin-translation-preview" tabindex="0" aria-label="{{ __('ai.translation.preview') }}">
            <template x-for="(value, role) in (result?.fields || {})" :key="role">
                <div class="admin-panel"><strong class="admin-field-label" x-text="config.fields[role]?.label || role"></strong><p class="whitespace-pre-wrap mt-2" x-text="previewValue(value)"></p></div>
            </template>
            <template x-for="(text, index) in previewTexts" :key="index"><p class="admin-panel whitespace-pre-wrap" x-text="previewValue(text)"></p></template>
            </div>
            <template x-for="(warning, index) in (result?.warnings || [])" :key="index"><p class="admin-alert admin-alert--warning" x-text="warning"></p></template>
            <p x-show="result?.protected_block_types?.length" class="admin-alert admin-alert--warning" x-text="config.messages.protected + ' ' + (result?.protected_block_types || []).join(', ')"></p>
            <label x-show="targetHasContent" class="admin-check-label"><input type="checkbox" x-model="replaceReviewed">{{ __('ai.translation.replace') }}</label>
            <p class="admin-text-muted">{{ __('ai.translation.slug') }}</p>
            <x-admin.button variant="primary" icon="fa-solid fa-check" @click="apply()" x-bind:disabled="busy || (targetHasContent && !replaceReviewed)">{{ __('ai.translation.apply') }}</x-admin.button>
        </div>
    </x-admin.modal>
</div>
<script nonce="{{ $cspNonce ?? '' }}">
window.CmsEntityTranslator = {
    open(form) { window.dispatchEvent(new CustomEvent('cms-translate-open', { detail: { form } })); }
};
window.cmsEntityTranslationComponent = (config) => ({
    config, show: false, busy: false, error: '', result: null, form: null,
    sourceLocale: '', targetLocale: '', snapshot: '', replaceReviewed: false, targetHasContent: false,
    get eligibleLanguages() {
        const allowed = window.cmsContext?.activeLocales || [];
        return (this.config.languages || []).filter(language => allowed.includes(language.code));
    },
    open(form) {
        if (!form?.hasAttribute('data-cms-translation-editor')) return;
        if (this.busy) { this.show = true; return; }
        this.form = form; this.sourceLocale = window.cmsContext?.locale || this.eligibleLanguages[0]?.code || '';
        this.targetLocale = ''; this.result = null; this.error = ''; this.replaceReviewed = false; this.show = true;
    },
    fieldKey(role) {
        const input = this.config.fields[role]?.input || '';
        return input.match(/^translations\[\{locale\}\]\[([^\]]+)\]$/)?.[1] || null;
    },
    textRoles() { return ['title', 'summary', 'body', 'meta_title', 'meta_description'].filter(role => this.fieldKey(role)); },
    readLocale(locale) {
        const state = window.Alpine.$data(this.form);
        const translation = JSON.parse(JSON.stringify(state.translations?.[locale] || {}));
        const fields = {};
        this.textRoles().forEach(role => {
            const key = this.fieldKey(role);
            const input = Array.from(this.form.elements).find(element => element.name === this.config.fields[role].input.replace('{locale}', locale));
            fields[role] = String(input?.value ?? translation[key] ?? '');
        });
        const sectionsInput = this.form.querySelector('[name="sections_json"]');
        const allSections = sectionsInput ? JSON.parse(sectionsInput.value || '{}') : {};
        if (Array.isArray(allSections)) throw new Error(this.config.messages.invalid);
        return { fields, sections: allSections[locale] || [], translation };
    },
    capture() {
        return {
            source: this.readLocale(this.sourceLocale), target: this.readLocale(this.targetLocale),
            markets: Array.from(this.form.querySelectorAll('input[name="market_ids[]"]:checked')).map(input => Number(input.value)).sort((a, b) => a - b)
        };
    },
    hasContent(locale) {
        return Object.values(locale.fields || {}).some(value => String(value).trim() !== '') || (locale.sections || []).length > 0;
    },
    async generate() {
        this.error = ''; this.result = null; this.replaceReviewed = false;
        try {
            if (!this.eligibleLanguages.some(language => language.code === this.targetLocale) || this.sourceLocale === this.targetLocale) throw new Error(this.config.messages.invalid);
            const captured = this.capture();
            if (!this.hasContent(captured.source) || !captured.markets.length) throw new Error(this.config.messages.empty);
            this.snapshot = JSON.stringify(captured); this.targetHasContent = this.hasContent(captured.target); this.busy = true;
            const response = await fetch(this.config.startUrl, {
                method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': this.config.csrf },
                body: JSON.stringify({ action: 'translate_entity', entity_type: this.config.entity, entity_id: this.config.entityId,
                    context_source: 'editor', source_locale: this.sourceLocale, target_locale: this.targetLocale,
                    editor_market_ids: captured.markets, fields: captured.source.fields, sections: captured.source.sections })
            });
            const started = await response.json();
            if (!response.ok || !started.success) throw new Error(started.question || started.message || started.error || this.config.messages.failed);
            const deadline = Date.now() + 15 * 60 * 1000;
            while (Date.now() < deadline) {
                await new Promise(resolve => setTimeout(resolve, 2000));
                const poll = await fetch(this.config.statusUrl.replace('__TASK__', encodeURIComponent(started.task_id)), { headers: { Accept: 'application/json' } });
                const state = await poll.json();
                if (!poll.ok || !state.success || ['failed', 'needs_input', 'uncertain', 'approval_required'].includes(state.status)) throw new Error(state.question || state.message || state.error || this.config.messages.failed);
                if (state.status === 'completed') { this.result = state.data; return; }
            }
            throw new Error(this.config.messages.timeout);
        } catch (error) { this.error = error.message || this.config.messages.failed; }
        finally { this.busy = false; }
    },
    get previewTexts() {
        const texts = [];
        const visit = node => {
            if (!node || typeof node !== 'object') return;
            if (node.type === 'code_embed') return;
            Object.entries(node).forEach(([key, value]) => {
                if (typeof value === 'string' && ['title', 'content', 'text', 'description', 'desc', 'question', 'answer', 'caption', 'alt', 'cta_text', 'secondary_cta_text', 'badge', 'name'].includes(key) && value.trim()) texts.push(value);
                else if (value && typeof value === 'object') visit(value);
            });
        };
        visit(this.result?.sections || []); return texts;
    },
    previewValue(value) {
        const preview = new DOMParser().parseFromString(String(value), 'text/html');
        preview.querySelectorAll('script, style').forEach(node => node.remove());
        preview.querySelectorAll('p, div, li, h1, h2, h3, h4, br').forEach(node => node.append('\n'));
        return preview.body.textContent || '';
    },
    apply() {
        this.error = '';
        try {
            if (!this.result || this.result.source_locale !== this.sourceLocale || this.result.target_locale !== this.targetLocale) throw new Error(this.config.messages.invalid);
            const current = this.capture();
            if (this.snapshot !== JSON.stringify(current)) throw new Error(this.config.messages.stale);
            if (this.hasContent(current.target) && !this.replaceReviewed) throw new Error(this.config.messages.replace);
            const values = {};
            this.textRoles().forEach(role => { if (typeof this.result.fields?.[role] === 'string') values[this.fieldKey(role)] = this.result.fields[role]; });
            window.dispatchEvent(new CustomEvent('cms-translation-apply', { detail: { form: this.form, targetLocale: this.targetLocale, values } }));
            window.dispatchEvent(new CustomEvent('cms-translation-blocks', { detail: { form: this.form, targetLocale: this.targetLocale, blocks: this.result.sections || [] } }));
            this.show = false; this.result = null;
        } catch (error) { this.error = error.message || this.config.messages.failed; }
    }
});
</script>
@endif
