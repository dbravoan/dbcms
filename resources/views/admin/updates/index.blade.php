@extends('admin.layout')
@section('title', __('updates.title'))
@section('content')
<div class="admin-page max-w-5xl mx-auto space-y-6" x-data="coreUpdates(@js($catalog), @js(route('admin.updates.index')))">
    <x-admin.form-header :title="__('updates.title')" :description="__('updates.privacy')" />
    <section class="admin-card p-6 space-y-4" aria-live="polite">
        <p>{{ __('updates.installed') }}: <strong x-text="catalog.installed?.version || @js(__('updates.unknown'))"></strong></p>
        <p x-show="catalog.checked_at">{{ __('updates.updated') }}: <span x-text="catalog.checked_at ? new Date(catalog.checked_at * 1000).toLocaleString() : ''"></span></p>
        <p x-show="catalog.error" role="alert" class="text-amber-500" x-text="catalog.error"></p>
        <p x-show="!catalog.available && !catalog.error" x-text="catalog.checked_at ? @js(__('updates.current')) : @js(__('updates.unchecked'))"></p>
        <x-admin.button variant="primary" icon="fa-solid fa-rotate" x-on:click="check()" x-bind:disabled="busy">{{ __('updates.check') }}</x-admin.button>
    </section>
    <section x-show="catalog.available" class="admin-card p-6 space-y-4" x-cloak>
        <h2 class="text-lg font-semibold">{{ __('updates.available') }} <span x-text="catalog.available?.version"></span></h2>
        <p class="whitespace-pre-line break-words" x-text="catalog.available?.body"></p>
        <x-admin.button variant="primary" x-on:click="prepare()" x-bind:disabled="busy">{{ __('updates.prepare') }}</x-admin.button>
    </section>
    <form x-show="!catalog.installed?.verified" x-cloak class="admin-card p-6 space-y-4" x-on:submit.prevent="adopt($event.target)">
        <h2 class="text-lg font-semibold">{{ __('updates.adopt') }}</h2>
        <p>{{ __('updates.adopt_help') }}</p>
        <x-admin.input name="version" :label="__('updates.version')" placeholder="3.1.0" required pattern="[0-9]+\.[0-9]+\.[0-9]+" />
        <x-admin.select name="distribution" :label="__('updates.distribution')"><option value="source">{{ __('updates.source') }}</option><option value="runtime">{{ __('updates.runtime') }}</option></x-admin.select>
        <x-admin.button type="submit" x-bind:disabled="busy">{{ __('updates.adopt') }}</x-admin.button>
    </form>
    @if($recentRuns !== [])
        <section class="admin-card p-6 space-y-3">
            <h2 class="text-lg font-semibold">{{ __('updates.history') }}</h2>
            <ul class="space-y-2">
                @foreach($recentRuns as $previousRun)
                    <li class="flex flex-wrap items-center justify-between gap-3">
                        <span>{{ $previousRun['version'] ?? '' }} · <span x-text="stateLabel(@js($previousRun['status'] ?? ''))"></span> · {{ $previousRun['updated_at'] ?? '' }}</span>
                    <x-admin.button x-on:click="resume('{{ $previousRun['id'] }}')" x-bind:disabled="busy">{{ __('updates.open_run') }}</x-admin.button>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
    <p x-show="message" x-text="message" class="admin-card p-4" role="status" x-cloak></p>
    <section x-show="run" x-cloak class="admin-card p-6 space-y-5" aria-live="polite">
        <h2 class="text-lg font-semibold">{{ __('updates.run') }}</h2>
        <p>{{ __('updates.status') }}: <span x-text="stateLabel(run?.status)"></span></p>
        <template x-if="run?.message"><p x-text="run.message"></p></template>
        <template x-if="run?.preflight?.issues?.length"><ul class="list-disc pl-6"><template x-for="issue in run.preflight.issues" :key="issue"><li x-text="issue"></li></template></ul></template>
        <template x-if="run?.conflicts?.length"><div><h3>{{ __('updates.conflicts') }}</h3><ul class="list-disc pl-6"><template x-for="(conflict, index) in run.conflicts" :key="index"><li x-text="typeof conflict === 'string' ? conflict : (conflict.path || conflict.reason)"></li></template></ul></div></template>
        <div x-show="run?.mode === 'managed'" class="space-y-3"><p>{{ __('updates.managed') }}</p><x-admin.button href="#" x-bind:href="run ? base + '/runs/' + run.id + '/proposal' : '#'">{{ __('updates.proposal') }}</x-admin.button></div>
        <form x-show="run && run.mode !== 'managed' && ['prepared', 'ready', 'backup_required'].includes(run.status) && !run.conflicts?.length" class="space-y-4" x-on:submit.prevent="approve($event.target)">
            <p>{{ __('updates.backup_help') }}</p>
            <label class="flex gap-3 items-start"><input name="hosting[single_host]" type="checkbox" value="1" required class="mt-1"> <span>{{ __('updates.hosting') }}</span></label>
            <label class="flex gap-3 items-start"><input name="hosting[no_unmanaged_workers]" type="checkbox" value="1" required class="mt-1"> <span>{{ __('updates.workers') }}</span></label>
            <details class="space-y-4"><summary class="cursor-pointer">{{ __('updates.external') }}</summary><div class="space-y-4 pt-4">
                <x-admin.input name="backup[provider]" :label="__('updates.provider')" />
                <x-admin.input name="backup[reference]" :label="__('updates.reference')" />
                <x-admin.input type="datetime-local" name="backup[created_at]" :label="__('updates.created')" />
                <x-admin.input type="datetime-local" name="backup[restored_at]" :label="__('updates.restored')" />
                <x-admin.input type="file" name="verification_report" :label="__('updates.report')" accept=".pdf,.txt" />
            </div></details>
            <x-admin.input type="password" name="password" :label="__('updates.password')" required autocomplete="current-password" />
            <x-admin.button type="submit" variant="primary" x-bind:disabled="busy">{{ __('updates.approve') }}</x-admin.button>
        </form>
        <div x-show="recovery && !['completed','cancelled','rolled_back'].includes(run?.status)" class="space-y-3"><p>{{ __('updates.recovery') }}</p><x-admin.button href="#" x-bind:href="recovery?.url || '#'">{{ __('updates.recovery_link') }}</x-admin.button><x-admin.button x-on:click="advance()" x-bind:disabled="busy">{{ __('updates.continue') }}</x-admin.button></div>
        <x-admin.button x-show="run && ['prepared','ready','blocked','conflict','managed','backup_required'].includes(run.status)" x-on:click="cancel()" x-bind:disabled="busy">{{ __('updates.cancel') }}</x-admin.button>
    </section>
</div>
@endsection
@push('admin_scripts')
<script nonce="{{ $cspNonce ?? '' }}">
function coreUpdates(catalog, base) {
    return {
        catalog, base: base.replace(/\/$/, ''), run: null, recovery: null, busy: false, message: '',
        async call(url, body = {}, form = false) {
            const headers = {'Accept': 'application/json', 'X-CSRF-TOKEN': @js(csrf_token())};
            if (!form) headers['Content-Type'] = 'application/json';
            const response = await fetch(url, {method: 'POST', headers, credentials: 'same-origin', body: form ? body : JSON.stringify(body)});
            const data = await response.json().catch(() => ({message: 'No se pudo leer la respuesta. Abre la recuperación si la actualización ya había comenzado.'}));
            if (!response.ok) throw new Error(Object.values(data.errors || {}).flat().join(' ') || data.message || data.error || 'No se pudo completar la operación.');
            return data;
        },
        async perform(action) { if (this.busy) return; this.busy = true; this.message = ''; try { await action(); } catch (error) { this.message = error.message; } finally { this.busy = false; } },
        resume(id) { return this.perform(async () => { const response = await fetch(this.base + '/runs/' + id, {headers:{Accept:'application/json'}, credentials:'same-origin'}); const state = await response.json(); if (!response.ok) throw new Error(state.message || state.error || 'No se pudo consultar la preparación.'); this.run = state; this.recovery = null; try { const saved = JSON.parse(sessionStorage.getItem('cms-update-run')); if(saved?.id === id) this.recovery = saved.recovery; } catch (_) {} this.remember(); }); },
        check() { return this.perform(async () => { this.catalog = await this.call(this.base + '/check', {force: true}); }); },
        prepare() { return this.perform(async () => { this.run = await this.call(this.base + '/prepare', {version: this.catalog.available.version}); this.remember(); }); },
        adopt(form) { return this.perform(async () => { const adopted = await this.call(this.base + '/adopt', Object.fromEntries(new FormData(form))); this.catalog = await this.call(this.base + '/check'); this.message = 'Base oficial registrada. Personalizaciones conservadas: ' + (adopted.customizations?.length ?? adopted.customization_count ?? 0) + '.'; }); },
        approve(form) { return this.perform(async () => { const body = new FormData(form); await this.call(this.base + '/confirm-password', {password: body.get('password')}); body.delete('password'); body.set('plan_hash', this.run.plan_hash); this.run = await this.call(this.base + '/runs/' + this.run.id + '/approve', body, true); this.recovery = this.run.recovery || null; this.remember(); if (this.recovery) setTimeout(() => this.advance(), 500); }); },
        advance() { return this.perform(async () => { if (!this.recovery) return; const response = await fetch(this.recovery.url, {method: 'POST', credentials: 'same-origin', headers: {'Accept':'application/json', 'Content-Type':'application/json', 'X-Update-CSRF':this.recovery.csrf}, body: JSON.stringify({action:'advance', csrf:this.recovery.csrf})}); const state = await response.json(); if (!response.ok) throw new Error(state.message || state.error || 'Revisa la recuperación de la actualización.'); this.run = state; if (state.status === 'prepared') this.recovery = null; this.remember(); if (!['prepared','completed','cancelled','failed','recovery_required','code_restored','rolled_back'].includes(state.status)) setTimeout(() => this.advance(), 500); }); },
        cancel() { return this.perform(async () => { this.run = await this.call(this.base + '/runs/' + this.run.id + '/cancel'); this.remember(); }); },
        remember() { try { if (['completed','cancelled','rolled_back'].includes(this.run?.status)) { sessionStorage.removeItem('cms-update-run'); this.recovery = null; } else { sessionStorage.setItem('cms-update-run', JSON.stringify({id:this.run?.id, recovery:this.recovery})); } } catch (_) {} },
        async init() { let saved; try { saved = JSON.parse(sessionStorage.getItem('cms-update-run')); } catch (_) {} if (!saved?.id) return; this.recovery = saved.recovery; try { const response = await fetch(this.base + '/runs/' + saved.id, {headers:{Accept:'application/json'}, credentials:'same-origin'}); if(response.ok) this.run = await response.json(); } catch (_) {} },
        stateLabel(value) { return ({prepared:'Preparada', ready:'Preparada', blocked:'Necesita revisión', conflict:'Necesita revisión', managed:'Propuesta para revisión', approved:'Autorizada', backup_required:'Requiere respaldo', completed:'Completada', cancelled:'Cancelada', failed:'Se necesita recuperación', draining:'Esperando a que terminen las operaciones', backing_up:'Verificando la copia', files:'Aplicando archivos', files_applied:'Archivos actualizados', migrations_running:'Actualizando datos', health_pending:'Comprobando el sitio', health_verified:'Verificación superada', cache_pending:'Renovando las cachés', code_restored:'Código restaurado; revisa la recuperación', rolled_back:'Código anterior recuperado', waiting_requests:'Esperando las operaciones activas', recovery_required:'Se necesita recuperación', applying:'Aplicando archivos', migrating:'Actualizando datos', healthy:'Comprobando el sitio'})[value] || value || ''; }
    };
}
</script>
@endpush
