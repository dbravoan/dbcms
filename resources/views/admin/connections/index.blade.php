@extends('admin.layout')

@section('title', __('admin.page_titles.connections'))

@section('content')
<div class="space-y-6 max-w-5xl mx-auto pb-16 font-mono text-xs" x-data="externalConnections()">
    <!-- Breadcrumb Header -->
    <div class="flex items-center gap-2 text-gray-400 text-xs">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">{{ __('admin.connections.panel') }}</a>
        <span>/</span>
        <span class="text-gray-400">{{ __('admin.connections.system') }}</span>
        <span>/</span>
        <span class="text-[#00FF99] font-bold">{{ __('admin.nav.items.connections') }}</span>
    </div>

    <!-- Title Card -->
    <div class="cyber-card p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#1A1A1A] border border-[#00FF99]/40 flex items-center justify-center text-xl text-[#00FF99] shrink-0">
                <i class="fa-solid fa-cloud-arrow-up"></i>
            </div>
            <div>
                <h1 class="text-lg font-bold text-white tracking-tight">{{ __('admin.connections.title') }}</h1>
                <p class="text-gray-400 text-xs mt-1">{{ __('admin.connections.subtitle') }}</p>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-3">
            @if($isConfigured)
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] font-bold">
                    <span class="w-2 h-2 rounded-full bg-[#00FF99] animate-pulse"></span>
                    {{ __('admin.connections.badge_active') }}
                </span>
            @else
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-[#1A1A1A] border border-gray-700 text-gray-400 font-bold">
                    <span class="w-2 h-2 rounded-full bg-gray-500"></span>
                    {{ __('admin.connections.badge_inactive') }}
                </span>
            @endif
        </div>
    </div>

    <div class="cyber-card px-4 py-3 text-gray-400 border border-gray-800/80">
        <i class="fa-solid fa-circle-info text-[#00FF99] mr-2"></i>
        {{ $isConfigured ? __('admin.connections.badge_active_desc') : __('admin.connections.badge_inactive_desc') }}
    </div>

    <!-- Storage Form -->
    <form action="{{ route('admin.connections.update') }}" method="POST" class="space-y-6">
        @csrf
        @method('PUT')

        <div class="cyber-card p-6 sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span class="text-[#00FF99]">&gt;</span> {{ __('admin.connections.storage_title') }}
                </h2>
                <p class="text-gray-400 text-xs mt-1">{{ __('admin.connections.storage_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-800/80">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_provider') }}</label>
                    <select name="provider" x-model="provider"
                            class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors">
                        @foreach($providerLabels as $key => $label)
                            <option value="{{ $key }}" @selected(old('provider', $configuration['provider']) === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_provider') }}</p>
                </div>
            </div>
        </div>

        <!-- Credentials & Location (hidden for local storage) -->
        <div x-show="provider !== 'local'" x-cloak class="cyber-card p-6 sm:p-7 space-y-6">
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span class="text-[#00FF99]">&gt;</span> {{ __('admin.connections.credentials_title') }}
                </h2>
                <p class="text-gray-400 text-xs mt-1">{{ __('admin.connections.credentials_desc') }}</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-800/80">
                <div class="space-y-1.5 md:col-span-2">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_endpoint') }}</label>
                    <input type="text" name="endpoint" value="{{ old('endpoint', $configuration['endpoint']) }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors"
                           placeholder="https://s3.eu-west-par.io.cloud.ovh.net">
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_endpoint') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_region') }}</label>
                    <input type="text" name="region" value="{{ old('region', $configuration['region']) }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors"
                           placeholder="eu-west-par">
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_region') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_bucket') }}</label>
                    <input type="text" name="bucket" value="{{ old('bucket', $configuration['bucket']) }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors"
                           placeholder="dbcms-media">
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_bucket') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_access_key') }}</label>
                    <input type="text" name="access_key" value="{{ old('access_key', $configuration['access_key']) }}" autocomplete="off"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors">
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_access_key') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_secret_key') }}</label>
                    <input type="password" name="secret_key" value="{{ old('secret_key', $configuration['secret_key']) }}" autocomplete="new-password"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors"
                           placeholder="••••••••">
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_secret_key') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_public_url') }}</label>
                    <input type="text" name="public_url" value="{{ old('public_url', $configuration['public_url']) }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors"
                           placeholder="https://cdn.midominio.com">
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_public_url') }}</p>
                </div>

                <div class="space-y-1.5">
                    <label class="font-bold text-white text-xs">{{ __('admin.connections.field_root') }}</label>
                    <input type="text" name="root" value="{{ old('root', $configuration['root']) }}"
                           class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white text-xs outline-none transition-colors"
                           placeholder="media">
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_root') }}</p>
                </div>

                <div class="space-y-1.5 md:col-span-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="path_style" value="1" @checked(old('path_style', $configuration['path_style']))
                               class="w-4 h-4 rounded border-gray-700 bg-[#1A1A1A] text-[#00FF99] focus:ring-[#00FF99]">
                        <span class="font-bold text-white text-xs">{{ __('admin.connections.field_path_style') }}</span>
                    </label>
                    <p class="text-[11px] text-gray-500">{{ __('admin.connections.hint_path_style') }}</p>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="cyber-card p-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <p class="text-gray-500 text-[11px]">
                <i class="fa-solid fa-shield-halved mr-1"></i> {{ __('admin.connections.security_note') }}
            </p>
            <div class="flex items-center gap-3 shrink-0">
                <a href="{{ route('admin.dashboard') }}" class="px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white rounded-lg transition-colors font-bold">
                    {{ __('admin.connections.cancel') }}
                </a>
                <button type="submit" class="btn-neon text-xs flex items-center gap-2">
                    <i class="fa-solid fa-floppy-disk"></i>
                    <span>{{ __('admin.connections.save') }}</span>
                </button>
            </div>
        </div>
    </form>

    <!-- Test Connection -->
    @if($isConfigured)
        <form action="{{ route('admin.connections.test') }}" method="POST" class="cyber-card p-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            @csrf
            <div>
                <h2 class="text-sm font-bold text-white flex items-center gap-2">
                    <span class="text-[#00FF99]">&gt;</span> {{ __('admin.connections.test_title') }}
                </h2>
                <p class="text-gray-400 text-xs mt-1">{{ __('admin.connections.test_desc') }}</p>
            </div>
            <button type="submit" class="px-4 py-2 bg-[#1A1A1A] hover:bg-gray-800 border border-[#00FF99]/40 text-[#00FF99] rounded-lg transition-colors text-xs font-bold shrink-0 flex items-center gap-2">
                <i class="fa-solid fa-plug-circle-check"></i>
                <span>{{ __('admin.connections.test_button') }}</span>
            </button>
        </form>

        <!-- Migrate existing files -->
        <div class="cyber-card p-6 sm:p-7 space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div>
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="text-[#00FF99]">&gt;</span> {{ __('admin.connections.migrate_title') }}
                    </h2>
                    <p class="text-gray-400 text-xs mt-1">{{ __('admin.connections.migrate_desc') }}</p>
                </div>
                <span class="shrink-0 px-3 py-1.5 rounded-lg bg-[#1A1A1A] border border-gray-700 text-gray-300 font-bold">
                    {{ __('admin.connections.migrate_pending') }}: <span class="text-[#00FF99]">{{ $pendingFiles }}</span>
                </span>
            </div>

            <div class="pt-4 border-t border-gray-800/80 space-y-4">
                <div class="flex flex-wrap items-center gap-5">
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="migrationOptions.rewriteUrls" class="w-4 h-4 rounded border-gray-700 bg-[#1A1A1A] text-[#00FF99] focus:ring-[#00FF99]">
                        <span class="font-bold text-white text-xs">{{ __('admin.connections.migrate_rewrite') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="migrationOptions.pruneLocal" class="w-4 h-4 rounded border-gray-700 bg-[#1A1A1A] text-[#00FF99] focus:ring-[#00FF99]">
                        <span class="font-bold text-white text-xs">{{ __('admin.connections.migrate_prune') }}</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" x-model="migrationOptions.dryRun" class="w-4 h-4 rounded border-gray-700 bg-[#1A1A1A] text-[#00FF99] focus:ring-[#00FF99]">
                        <span class="font-bold text-white text-xs">{{ __('admin.connections.migrate_dry_run') }}</span>
                    </label>
                </div>

                <p x-show="migrationOptions.pruneLocal" x-cloak class="text-[11px] text-amber-400">
                    <i class="fa-solid fa-triangle-exclamation mr-1"></i> {{ __('admin.connections.migrate_prune_warning') }}
                </p>

                <!-- Progress -->
                <div x-show="migration" x-cloak class="space-y-2">
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="font-bold text-white" x-text="migrationLabel()"></span>
                        <span class="text-gray-400" x-text="(migration?.processed ?? 0) + ' / ' + (migration?.total ?? 0)"></span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-[#1A1A1A] border border-gray-800 overflow-hidden">
                        <div class="h-full bg-[#00FF99] transition-all" :style="`width: ${migration?.percent ?? 0}%`"></div>
                    </div>
                    <div class="flex flex-wrap gap-4 text-[11px] text-gray-400">
                        <span>Migrados: <span class="text-white" x-text="migration?.migrated ?? 0"></span></span>
                        <span>Omitidos: <span class="text-white" x-text="migration?.skipped ?? 0"></span></span>
                        <span>Fallidos: <span :class="(migration?.failed ?? 0) > 0 ? 'text-red-400' : 'text-white'" x-text="migration?.failed ?? 0"></span></span>
                        <span x-show="!migrationOptions.dryRun">Reescritas: <span class="text-white" x-text="migration?.rewritten ?? 0"></span></span>
                    </div>
                    <template x-if="migration?.errors && migration.errors.length">
                        <ul class="mt-1 space-y-0.5 text-[11px] text-amber-400 max-h-32 overflow-y-auto">
                            <template x-for="(err, i) in migration.errors" :key="i">
                                <li x-text="'• ' + err"></li>
                            </template>
                        </ul>
                    </template>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <p class="text-gray-500 text-[11px]">
                    <i class="fa-solid fa-circle-info mr-1"></i> {{ __('admin.connections.migrate_hint') }}
                </p>
                <form action="{{ route('admin.connections.migrate') }}" method="POST" class="shrink-0">
                    @csrf
                    <input type="hidden" name="rewrite_urls" :value="migrationOptions.rewriteUrls ? 1 : 0">
                    <input type="hidden" name="prune_local" :value="migrationOptions.pruneLocal ? 1 : 0">
                    <input type="hidden" name="dry_run" :value="migrationOptions.dryRun ? 1 : 0">
                    <button type="submit"
                            :disabled="isMigrating"
                            :class="isMigrating ? 'opacity-50 cursor-not-allowed' : ''"
                            class="px-4 py-2 bg-[#00FF99]/10 hover:bg-[#00FF99] text-[#00FF99] hover:text-black border border-[#00FF99]/40 rounded-lg transition-colors text-xs font-bold flex items-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        <span x-text="isMigrating ? @js(__('admin.connections.migrate_running')) : @js(__('admin.connections.migrate_button'))"></span>
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>

<script nonce="{{ $cspNonce ?? '' }}">
function externalConnections() {
    return {
        provider: @js(old('provider', $configuration['provider'])),
        migration: @js($migrationRun),
        migrationOptions: {
            rewriteUrls: true,
            pruneLocal: false,
            dryRun: false,
        },
        pollTimer: null,

        get isMigrating() {
            return this.migration !== null && ! this.migration.finished;
        },

        migrationLabel() {
            if (! this.migration) return '';
            const map = { pending: 'En cola', running: 'En curso', completed: 'Completada', failed: 'Con errores' };
            return map[this.migration.status] ?? this.migration.status;
        },

        init() {
            if (this.isMigrating) {
                this.startPolling();
            }
        },

        startPolling() {
            this.stopPolling();
            this.pollTimer = setInterval(() => this.poll(), 2500);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
        },

        async poll() {
            try {
                const response = await fetch('{{ route('admin.connections.migration.status') }}', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                });
                if (! response.ok) return;
                const payload = await response.json();
                if (payload.run) {
                    this.migration = payload.run;
                    if (this.migration.finished) this.stopPolling();
                }
            } catch (error) {
                // Keep polling on transient errors.
            }
        },
    };
}
</script>
@endsection
