@extends('admin.layout')

@section('title', __('admin.page_titles.modules'))

@section('content')
<div x-data="{
    showImpactModal: false,
    loadingImpact: false,
    targetModule: null,
    targetModuleName: '',
    impactData: null,
    submitFormId: null,

    confirmDeactivate(identifier, name, formId) {
        this.targetModule = identifier;
        this.targetModuleName = name;
        this.submitFormId = formId;
        this.loadingImpact = true;
        this.impactData = null;
        this.showImpactModal = true;

        fetch(@js(route('admin.modules.impact', ['identifier' => '__MODULE__'])).replace('__MODULE__', encodeURIComponent(identifier)))
            .then(res => res.json())
            .then(data => {
                this.impactData = data;
                this.loadingImpact = false;
            })
            .catch(() => {
                this.loadingImpact = false;
            });
    },

    proceedDeactivation() {
        if (this.submitFormId) {
            document.getElementById(this.submitFormId).submit();
        }
    }
}" class="space-y-8 font-mono text-xs">
    <!-- Upload Module Package Box -->
    <div class="cyber-card p-6 flex flex-col md:flex-row items-center justify-between gap-6">
        <div>
            <h3 class="text-base font-bold text-white flex items-center gap-2">
                <span class="text-[#00FF99]">&gt;</span> {{ __('admin.modules.install_title') }}
            </h3>
            <p class="text-gray-400 text-xs mt-1">{!! __('admin.modules.install_desc') !!}</p>
        </div>
        <form action="{{ route('admin.modules.upload') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-3">
            @csrf
            <input type="file" name="module_zip" accept=".zip" required class="text-xs text-gray-400 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-[#1A1A1A] file:text-gray-300 hover:file:bg-gray-800 cursor-pointer">
            <button type="submit" class="btn-neon">
                Subir e Instalar
            </button>
        </form>
    </div>

    <!-- Modules Grid Table -->
    <div class="cyber-card overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-800 flex items-center justify-between bg-[#121212]">
            <h2 class="text-sm font-bold text-white uppercase tracking-wider">{{ __('admin.modules.registry_title') }}</h2>
            <span class="text-xs font-semibold text-[#00FF99]">{{ __('admin.modules.registered_count', ['count' => $modules->count()]) }}</span>
        </div>

        <div class="divide-y divide-gray-800/60">
            @forelse($modules as $module)
                <div class="p-6 flex flex-col md:flex-row items-start md:items-center justify-between gap-4 hover:bg-gray-800/30 transition-colors">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 rounded-xl bg-[#1A1A1A] border border-[#00FF99]/40 flex items-center justify-center text-xl font-bold text-[#00FF99]">
                            {{ strtoupper(substr($module->identifier, 0, 1)) }}
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h4 class="font-bold text-white text-base">{{ $module->name }}</h4>
                                <x-admin.chip>v{{ $module->version }}</x-admin.chip>
                                @if($module->is_core)
                                    <x-admin.chip variant="purple" icon="fa-solid fa-shield-halved">
                                        TRONCAL / CORE
                                    </x-admin.chip>
                                @endif
                                @if($module->is_pageable)
                                    <x-admin.chip variant="brand">{{ __('admin.modules.pages_blocks') }}</x-admin.chip>
                                @endif
                                @php
                                    $hasSettingsSchema = !empty($module->getSettingsSchema());
                                @endphp
                                @if($hasSettingsSchema)
                                    @if($module->isConfigured())
                                        <x-admin.chip variant="success">
                                            Configurado
                                        </x-admin.chip>
                                    @else
                                        <x-admin.chip variant="warning">
                                            Configuración pendiente
                                        </x-admin.chip>
                                    @endif
                                @endif
                            </div>
                            <p class="text-gray-400 text-xs mt-1">{{ $module->description ?: __('admin.modules.no_description') }}</p>
                            <div class="text-[10px] text-gray-500 mt-1">{{ __('admin.modules.provider') }}: {{ $module->provider }}</div>
                        </div>
                    </div>

                    <div class="flex items-center gap-2 shrink-0">
                        @if(app(\App\Core\Integrations\IntegrationGuideRegistry::class)->all($module->identifier) !== [])
                            <x-admin.button :href="route('admin.modules.guides.index', ['identifier' => $module->identifier])" size="sm" :aria-label="__('integrations.module_tutorials', ['module' => $module->name])">{{ __('integrations.tutorials') }}</x-admin.button>
                        @endif
                        @if($hasSettingsSchema)
                            <a href="{{ route('admin.modules.settings', ['identifier' => $module->identifier]) }}" class="px-3 py-1.5 rounded-lg text-xs font-bold text-sky-400 bg-sky-500/10 hover:bg-sky-500/20 border border-sky-500/30 transition-colors flex items-center gap-1.5">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                                Ajustes
                            </a>
                        @endif
                        <!-- Activation / Deactivation Toggle -->
                        <form id="toggle-form-{{ $module->identifier }}" action="{{ route('admin.modules.toggle', ['identifier' => $module->identifier]) }}" method="POST">
                            @csrf
                            @if($module->is_active)
                                <button type="button"
                                        @click="confirmDeactivate('{{ $module->identifier }}', '{{ $module->name }}', 'toggle-form-{{ $module->identifier }}')"
                                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-colors bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/40 hover:bg-red-500/20 hover:text-red-400 hover:border-red-500/40"
                                        title="{{ __('admin.modules.click_deactivate') }}">
                                    Activo
                                </button>
                            @else
                                <button type="submit"
                                        class="px-4 py-1.5 rounded-lg text-xs font-bold transition-colors bg-gray-800 text-gray-400 hover:text-[#00FF99] hover:bg-gray-700"
                                        title="{{ __('admin.modules.click_activate') }}">
                                    Inactivo
                                </button>
                            @endif
                        </form>

                        <!-- Install / Uninstall -->
                        @if($module->is_core)
                            <span class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-gray-400 bg-gray-900 border border-gray-800 flex items-center gap-1.5 cursor-not-allowed" title="{{ __('admin.modules.core_tooltip') }}">
                                <i class="fa-solid fa-shield-halved text-purple-400 text-[10px]"></i>
                                <span>{{ __('admin.modules.core') }}</span>
                            </span>
                        @elseif($module->is_installed)
                            <form action="{{ route('admin.modules.uninstall', ['identifier' => $module->identifier]) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3.5 py-1.5 rounded-lg text-xs font-semibold text-red-400 bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 transition-colors">
                                    Desinstalar
                                </button>
                            </form>
                        @else
                            <form action="{{ route('admin.modules.install', ['identifier' => $module->identifier]) }}" method="POST">
                                @csrf
                                <button type="submit" class="btn-neon">
                                    Ejecutar Migraciones
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <div class="p-8 text-center text-gray-500 text-sm">{{ __('admin.modules.empty') }}</div>
            @endforelse
        </div>
    </div>

    <!-- Impact & Interconnection Warning Modal -->
    <x-admin.modal
        show="showImpactModal"
        maxWidth="2xl"
        icon="fa-solid fa-triangle-exclamation"
    >
        <x-slot:header>
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 shrink-0">
                    <i class="fa-solid fa-network-wired"></i>
                </div>
                <div>
                    <h3 class="text-sm font-bold text-white flex items-center gap-2">
                        <span>&gt; {{ __('admin.modules.deactivation_title') }}</span>
                        <span class="text-[#00FF99]" x-text="targetModuleName"></span>
                    </h3>
                    <p class="text-[11px] text-gray-400">{{ __('admin.modules.deactivation_subtitle') }}</p>
                </div>
            </div>
        </x-slot:header>

        <div class="space-y-4 text-xs font-mono">
            <!-- Loading State -->
            <div x-show="loadingImpact" class="py-8 text-center space-y-3">
                <i class="fa-solid fa-spinner fa-spin text-2xl text-[#00FF99]"></i>
                <p class="text-gray-400 text-xs">{{ __('admin.modules.analyzing') }}</p>
            </div>

            <!-- Content Loaded -->
            <div x-show="!loadingImpact && impactData" class="space-y-4">
                <!-- Critical Warning (e.g. Pages module) -->
                <div x-show="impactData?.is_core_risk" class="p-4 rounded-xl bg-red-950/40 border border-red-500/50 text-red-200 space-y-2">
                    <div class="flex items-center gap-2 font-bold text-red-400">
                        <i class="fa-solid fa-shield-halved text-sm"></i>
                        <span>{{ __('admin.modules.critical_warning') }}</span>
                    </div>
                    <p class="text-[11px] leading-relaxed" x-text="impactData?.warning"></p>
                </div>

                <!-- Affected Menus -->
                <div x-show="impactData?.affected_menus && impactData.affected_menus.length > 0" class="p-3.5 rounded-xl bg-[#1A1A1A] border border-amber-500/30 space-y-2">
                    <div class="flex items-center justify-between text-amber-400 font-bold text-xs">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-bars"></i>
                            <span>{{ __('admin.modules.affected_menus') }} (<span x-text="impactData?.affected_menus?.length ?? 0"></span>)</span>
                        </span>
                        <span class="text-[10px] text-gray-500">{{ __('admin.modules.affected_menus_hint') }}</span>
                    </div>
                    <ul class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                        <template x-for="(menuItem, idx) in impactData?.affected_menus" :key="idx">
                            <li class="p-2 rounded bg-black/40 border border-gray-800 flex items-center justify-between text-[11px]">
                                <span class="text-white font-bold" x-text="menuItem.label"></span>
                                <span class="text-gray-400 text-[10px]" x-text="'[' + menuItem.menu + '] ' + menuItem.url"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                <!-- Affected Pages -->
                <div x-show="impactData?.affected_pages && impactData.affected_pages.length > 0" class="p-3.5 rounded-xl bg-[#1A1A1A] border border-sky-500/30 space-y-2">
                    <div class="flex items-center justify-between text-sky-400 font-bold text-xs">
                        <span class="flex items-center gap-2">
                            <i class="fa-solid fa-file-lines"></i>
                            <span>{{ __('admin.modules.affected_pages') }} (<span x-text="impactData?.affected_pages?.length ?? 0"></span>)</span>
                        </span>
                        <span class="text-[10px] text-[#00FF99]">{{ __('admin.modules.silenced') }}</span>
                    </div>
                    <ul class="space-y-1.5 max-h-36 overflow-y-auto pr-1">
                        <template x-for="(pageItem, idx) in impactData?.affected_pages" :key="idx">
                            <li class="p-2 rounded bg-black/40 border border-gray-800 flex items-center justify-between text-[11px]">
                                <span class="text-white font-bold" x-text="pageItem.title"></span>
                                <span class="text-gray-400 text-[10px]" x-text="pageItem.reason"></span>
                            </li>
                        </template>
                    </ul>
                    <p class="text-[10px] text-gray-400 italic">
                        {{ __('admin.modules.silenced_note') }}
                    </p>
                </div>

                <!-- Clean Deactivation (No impact) -->
                <div x-show="impactData && !impactData.has_impact" class="p-4 rounded-xl bg-emerald-950/30 border border-emerald-500/30 text-emerald-300 space-y-1">
                    <div class="flex items-center gap-2 font-bold text-emerald-400">
                        <i class="fa-solid fa-circle-check"></i>
                        <span>{{ __('admin.modules.no_critical') }}</span>
                    </div>
                    <p class="text-[11px] leading-relaxed text-gray-300">
                        {{ __('admin.modules.no_critical_note') }}
                    </p>
                </div>
            </div>
        </div>

        <x-slot:footer>
            <button type="button"
                    @click="showImpactModal = false"
                    class="px-4 py-2 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 rounded-lg text-xs font-mono transition-colors">
                Cancelar
            </button>
            <button type="button"
                    @click="proceedDeactivation()"
                    class="px-4 py-2 bg-amber-600/80 hover:bg-amber-600 text-white font-bold rounded-lg text-xs font-mono shadow-lg shadow-amber-600/20 transition-all flex items-center gap-2">
                <i class="fa-solid fa-power-off"></i>
                <span>{{ __('admin.modules.confirm_deactivate') }}</span>
            </button>
        </x-slot:footer>
    </x-admin.modal>
</div>
@endsection
