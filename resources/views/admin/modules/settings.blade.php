@extends('admin.layout')

@section('title', __('admin.page_titles.module_settings') . ' ' . $module->name)

@section('content')
<div class="space-y-6 max-w-4xl mx-auto pb-12 font-mono text-xs">
    <!-- Breadcrumb Header -->
    <div class="flex items-center gap-2 text-gray-400 text-xs">
        <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">Panel</a>
        <span>/</span>
        <a href="{{ route('admin.modules.index') }}" class="hover:text-white transition-colors">Módulos</a>
        <span>/</span>
        <span class="text-white font-bold">{{ $module->name }}</span>
        <span>/</span>
        <span class="text-[#00FF99]">{{ __('admin.modules.settings_title') }}</span>
    </div>

    <!-- Module Identity Card -->
    <div class="cyber-card p-6 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-[#1A1A1A] border border-[#00FF99]/40 flex items-center justify-center text-xl font-bold text-[#00FF99] shrink-0">
                {{ strtoupper(substr($module->identifier, 0, 1)) }}
            </div>
            <div>
                <div class="flex items-center gap-2 flex-wrap">
                    <h1 class="text-lg font-bold text-white tracking-tight">{{ $module->name }}</h1>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border border-gray-700 bg-[#1A1A1A] text-gray-300">v{{ $module->version }}</span>
                    <span class="text-[10px] font-bold px-2 py-0.5 rounded border border-[#00FF99]/30 bg-[#00FF99]/10 text-[#00FF99] uppercase">{{ $module->identifier }}</span>
                </div>
                <p class="text-gray-400 text-xs mt-1">{{ $module->description ?: __('admin.modules.default_description', ['app' => config('cms.name')]) }}</p>
            </div>
        </div>

        <div class="shrink-0 flex items-center gap-3">
            @if($configured)
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-[#00FF99]/10 text-[#00FF99] border border-[#00FF99]/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#00FF99]"></span>
                    Requisitos configurados
                </span>
            @else
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                    Configuración pendiente
                </span>
            @endif

            <a href="{{ route('admin.modules.index') }}" class="px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white rounded-lg transition-colors text-xs font-bold">
                &larr; Volver
            </a>
        </div>
    </div>

    <!-- Settings Form -->
    @if(empty($schema))
        <div class="cyber-card p-8 text-center space-y-3">
            <div class="text-2xl text-gray-500"><i class="fa-solid fa-gear"></i></div>
            <h3 class="text-sm font-bold text-white">Este módulo no requiere configuración adicional</h3>
            <p class="text-gray-400 text-xs max-w-md mx-auto">
                No hay claves de API, credenciales ni parámetros definidos en el manifiesto <code>module.json</code> de este módulo.
            </p>
            <div class="pt-2">
                <a href="{{ route('admin.modules.index') }}" class="btn-neon text-xs">
                    Volver a lista de módulos
                </a>
            </div>
        </div>
    @else
        <form action="{{ route('admin.modules.settings.update', ['identifier' => $module->identifier]) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="cyber-card p-6 sm:p-7 space-y-6 divide-y divide-gray-800/80">
                <div class="pb-2">
                    <h2 class="text-sm font-bold text-white flex items-center gap-2">
                        <span class="text-[#00FF99]">&gt;</span> Parámetros y Credenciales del Módulo
                    </h2>
                    <p class="text-gray-400 text-xs mt-1">Los cambios se aplican de forma inmediata en el runtime y quedan persistidos en el registro del módulo.</p>
                </div>

                @php
                    // Global fields on top; the ones scoped to a language/market
                    // live inside the tabs below (so a language created in the
                    // backoffice gets its own field without touching code).
                    $tabs = [];
                    foreach ($schema as $key => $field) {
                        if (in_array($field['scope'] ?? null, ['locale', 'market'], true)) {
                            $tabs[$field['scope']][$key] = $field;
                        }
                    }
                    $renderField = function (string $key, array $field) use ($settings): string {
                        return view('admin.modules.partials.setting-field', [
                            'key' => $key,
                            'field' => $field,
                            'settings' => $settings,
                        ])->render();
                    };
                @endphp

                @foreach($schema as $key => $field)
                    @if(! in_array($field['scope'] ?? null, ['locale', 'market'], true))
                        {!! $renderField($key, $field) !!}
                    @endif
                @endforeach
            </div>

            @if(($tabs['locale'] ?? []) !== [])
                <div class="cyber-card p-6 sm:p-7 space-y-4"
                     x-data="{ activeScope: @js(array_key_first($tabs['locale'])) }"
                     x-init="activeScope = @js(array_key_first($tabs['locale']))">
                    <div class="pb-2">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <span class="text-[#00FF99]">&gt;</span> {{ __('admin.modules.locale_settings_title') }}
                        </h2>
                        <p class="text-gray-400 text-xs mt-1">{{ __('admin.modules.locale_settings_desc') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($tabs['locale'] as $key => $field)
                            <button type="button"
                                    @click="activeScope = @js($key)"
                                    :class="activeScope === @js($key) ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                                    class="px-3.5 py-1.5 rounded transition-all flex items-center gap-1.5 text-xs">
                                <span>{{ ($activeLanguages ?? collect())->firstWhere('code', $field['locale'] ?? '')?->flag ?? '🌐' }}</span>
                                <span>{{ ($activeLanguages ?? collect())->firstWhere('code', $field['locale'] ?? '')?->name ?? strtoupper((string) ($field['locale'] ?? '')) }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach($tabs['locale'] as $key => $field)
                        <div x-show="activeScope === @js($key)" x-cloak class="pt-2">
                            {!! $renderField($key, $field) !!}
                        </div>
                    @endforeach
                </div>
            @endif

            @if(($tabs['market'] ?? []) !== [])
                <div class="cyber-card p-6 sm:p-7 space-y-4"
                     x-data="{ activeScope: @js(array_key_first($tabs['market'])) }"
                     x-init="activeScope = @js(array_key_first($tabs['market']))">
                    <div class="pb-2">
                        <h2 class="text-sm font-bold text-white flex items-center gap-2">
                            <span class="text-[#00FF99]">&gt;</span> {{ __('admin.modules.market_settings_title') }}
                        </h2>
                        <p class="text-gray-400 text-xs mt-1">{{ __('admin.modules.market_settings_desc') }}</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach($tabs['market'] as $key => $field)
                            <button type="button"
                                    @click="activeScope = @js($key)"
                                    :class="activeScope === @js($key) ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'"
                                    class="px-3.5 py-1.5 rounded transition-all flex items-center gap-1.5 text-xs">
                                <span>{{ ($activeMarkets ?? collect())->firstWhere('code', $field['market'] ?? '')?->flag ?? '🌐' }}</span>
                                <span>{{ ($activeMarkets ?? collect())->firstWhere('code', $field['market'] ?? '')?->name ?? strtoupper((string) ($field['market'] ?? '')) }}</span>
                            </button>
                        @endforeach
                    </div>

                    @foreach($tabs['market'] as $key => $field)
                        <div x-show="activeScope === @js($key)" x-cloak class="pt-2">
                            {!! $renderField($key, $field) !!}
                        </div>
                    @endforeach
                </div>
            @endif

            <!-- Provider Context & External Guides -->
            @if($module->identifier === 'meta')
                <div class="p-4 rounded-xl bg-[#1A1A1A] border border-gray-800 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-white">{{ __('admin.modules.meta_help_title') }}</span>
                        <p class="text-gray-400 text-[11px] mt-0.5">{{ __('admin.modules.meta_help_desc') }}</p>
                    </div>
                    <a href="https://business.facebook.com/events_manager2" target="_blank" class="text-[#00FF99] hover:underline shrink-0 text-xs font-bold">
                        Meta Events Manager &nearr;
                    </a>
                </div>
            @elseif($module->identifier === 'analytics')
                <div class="p-4 rounded-xl bg-[#1A1A1A] border border-gray-800 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-white">¿Dónde obtener tu ID de Medición GA4?</span>
                        <p class="text-gray-400 text-[11px] mt-0.5">En Google Analytics &rarr; Administrar &rarr; Flujos de Datos &rarr; Selecciona tu flujo web (formato G-XXXXXXXXXX).</p>
                    </div>
                    <a href="https://analytics.google.com" target="_blank" class="text-[#00FF99] hover:underline shrink-0 text-xs font-bold">
                        Google Analytics &nearr;
                    </a>
                </div>
            @elseif($module->identifier === 'ads')
                <div class="p-4 rounded-xl bg-[#1A1A1A] border border-gray-800 flex items-center justify-between text-xs">
                    <div>
                        <span class="font-bold text-white">¿Dónde obtener tu ID de Editor de AdSense?</span>
                        <p class="text-gray-400 text-[11px] mt-0.5">En Google AdSense &rarr; Cuenta &rarr; Información de la cuenta &rarr; ID de editor (formato ca-pub-XXXXXXXXXXXXXXXX).</p>
                    </div>
                    <a href="https://www.google.com/adsense" target="_blank" class="text-[#00FF99] hover:underline shrink-0 text-xs font-bold">
                        Google AdSense &nearr;
                    </a>
                </div>
            @elseif($module->identifier === 'mcp')
                @include('mcp::admin.guide')
            @endif

            <!-- Form Action Footer -->
            <div class="flex items-center justify-between pt-2">
                <a href="{{ route('admin.modules.index') }}" class="px-4 py-2 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white rounded-lg transition-colors font-bold text-xs">
                    Cancelar
                </a>

                <button type="submit" class="btn-neon text-xs py-2.5 px-6">
                    Guardar Configuración
                </button>
            </div>
        </form>
    @endif
</div>
@endsection
