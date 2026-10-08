@extends('admin.layout')

@section('title', __('admin.page_titles.maintenance'))

@section('content')
<div class="space-y-6 max-w-6xl mx-auto pb-16 font-mono text-xs" x-data="{
    mode: '{{ $settings['mode'] ?? 'default' }}',
    previewDevice: 'desktop'
}">
    <!-- Breadcrumb Header -->
    <div class="flex items-center justify-between gap-4 flex-wrap">
        <div class="flex items-center gap-2 text-gray-400 text-xs">
            <a href="{{ route('admin.dashboard') }}" class="hover:text-white transition-colors">{{ __('admin.maintenance.panel') }}</a>
            <span>/</span>
            <a href="{{ route('admin.modules.index') }}" class="hover:text-white transition-colors">{{ __('admin.maintenance.modules') }}</a>
            <span>/</span>
            <span class="text-[#00FF99] font-bold">{{ __('admin.maintenance.title') }}</span>
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.maintenance.preview') }}" target="_blank" class="px-3.5 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] text-gray-300 hover:text-[#00FF99] rounded-lg transition-all text-xs font-bold inline-flex items-center gap-2 shadow-sm">
                <i class="fa-solid fa-arrow-up-right-from-square text-[10px]"></i>
                <span>{{ __('admin.maintenance.preview') }}</span>
            </a>
            <form action="{{ route('admin.maintenance.toggle') }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-1.5 rounded-lg text-xs font-bold transition-all inline-flex items-center gap-2 {{ $isActive ? 'bg-red-500/20 text-red-400 border border-red-500/40 hover:bg-red-500/30 shadow-[0_0_15px_rgba(239,68,68,0.2)]' : 'bg-[#00FF99]/20 text-[#00FF99] border border-[#00FF99]/40 hover:bg-[#00FF99]/30 shadow-[0_0_15px_rgba(0,255,153,0.2)]' }}">
                    <span class="w-2 h-2 rounded-full {{ $isActive ? 'bg-red-400 animate-ping' : 'bg-[#00FF99]' }}"></span>
                    <span>{{ $isActive ? 'Desactivar Mantenimiento' : 'Activar Mantenimiento' }}</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Status Hero Card -->
    <div class="cyber-card p-6 flex flex-col md:flex-row md:items-center justify-between gap-6 border-l-4 {{ $isActive ? 'border-l-amber-500 bg-amber-500/5' : 'border-l-[#00FF99] bg-[#00FF99]/5' }}">
        <div class="space-y-2">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="w-10 h-10 rounded-xl bg-[#1A1A1A] border border-gray-700 flex items-center justify-center text-lg {{ $isActive ? 'text-amber-400' : 'text-[#00FF99]' }}">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2.5">
                        <h1 class="text-base font-bold text-white tracking-tight">{{ __('admin.maintenance.status_title') }}</h1>
                        @if($isActive)
                            <span class="px-3 py-0.5 rounded-full text-[11px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/50 flex items-center gap-1.5 animate-pulse">
                                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                MODO MANTENIMIENTO ACTIVO (HTTP 503)
                            </span>
                        @else
                            <span class="px-3 py-0.5 rounded-full text-[11px] font-bold bg-[#00FF99]/20 text-[#00FF99] border border-[#00FF99]/50 flex items-center gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-[#00FF99]"></span>
                                PORTAL PÚBLICO OPERATIVO (HTTP 200)
                            </span>
                        @endif
                    </div>
                    <p class="text-gray-400 text-xs mt-1">
                        @if($isActive)
                            Los visitantes externos están recibiendo la pantalla de mantenimiento con código HTTP 503 y Retry-After: <strong class="text-white">{{ $retryAfter }}s</strong>. Los usuarios del backoffice navegan de forma transparente con bypass.
                        @else
                            El portal está abierto para todos los usuarios y motores de búsqueda en todos los mercados.
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <div class="shrink-0 flex flex-col sm:flex-row md:flex-col items-start md:items-end gap-2 text-[11px] text-gray-400 font-mono">
            <div>
                <span class="text-gray-500">{{ __('admin.maintenance.bypass_staff') }}</span>
                <span class="text-[#00FF99] font-bold">{{ __('admin.maintenance.enabled') }}</span>
            </div>
            <div>
                <span class="text-gray-500">{{ __('admin.maintenance.deadline') }}</span>
                <span class="text-white font-bold">{{ $endDateTime ? $endDateTime->format('d/m/Y H:i') : __('admin.maintenance.not_scheduled') }}</span>
            </div>
        </div>
    </div>

    <!-- Main Configuration Form -->
    <form action="{{ route('admin.maintenance.save') }}" method="POST" class="space-y-6">
        @csrf

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Left 2 Cols: Main Settings -->
            <div class="lg:col-span-2 space-y-6">

                <!-- Section 1: Activation & Temporal Schedule -->
                <div class="cyber-card p-6 space-y-5">
                    <div class="border-b border-gray-800 pb-3 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span class="text-[#00FF99]">&gt;</span> 1. Activación y Programación de Fecha
                        </h2>
                        <span class="text-[10px] text-gray-500 font-mono">{{ __('admin.maintenance.seo_title') }}</span>
                    </div>

                    <div class="space-y-4">
                        <div class="p-3 bg-[#1A1A1A] border border-gray-800 rounded-lg flex items-center justify-between">
                            <div>
                                <label for="is_enabled" class="font-bold text-white text-xs block cursor-pointer">{{ __('admin.maintenance.activate') }}</label>
                                <p class="text-gray-400 text-[11px] mt-0.5">{{ __('admin.maintenance.status_desc') }}</p>
                            </div>
                            <input type="checkbox" name="is_enabled" id="is_enabled" value="1" {{ $isActive ? 'checked' : '' }} class="w-5 h-5 rounded bg-black border-gray-700 text-[#00FF99] focus:ring-0 cursor-pointer">
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="end_datetime" class="font-bold text-white text-xs block mb-1">
                                    Fecha y Hora Estimada de Finalización (Reapertura)
                                </label>
                                <p class="text-[11px] text-gray-400 mb-2">{!! __('admin.maintenance.retry_desc') !!}</p>
                                <input type="datetime-local"
                                       name="end_datetime"
                                       id="end_datetime"
                                       value="{{ $settings['end_datetime'] ?? '' }}"
                                       class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg p-2.5 text-white font-mono text-xs transition-colors">
                            </div>

                            <div>
                                <label for="retry_after_fallback" class="font-bold text-white text-xs block mb-1">
                                    Retry-After por Defecto (Segundos)
                                </label>
                                <p class="text-[11px] text-gray-400 mb-2">{{ __('admin.maintenance.retry_hint') }}</p>
                                <input type="number"
                                       name="retry_after_fallback"
                                       id="retry_after_fallback"
                                       value="{{ $settings['retry_after_fallback'] ?? 3600 }}"
                                       min="30"
                                       class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg p-2.5 text-white font-mono text-xs transition-colors">
                            </div>
                        </div>

                        <div class="pt-1">
                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="show_countdown" id="show_countdown" value="1" {{ !empty($settings['show_countdown']) ? 'checked' : '' }} class="w-4 h-4 rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99] focus:ring-0 cursor-pointer">
                                <span class="text-xs text-gray-300 select-none">{{ __('admin.maintenance.show_countdown') }}</span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Section 2: Page Customization Mode -->
                <div class="cyber-card p-6 space-y-5">
                    <div class="border-b border-gray-800 pb-3 flex items-center justify-between">
                        <h2 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-2">
                            <span class="text-[#00FF99]">&gt;</span> 2. Personalización Visual (Crear la página al gusto)
                        </h2>
                        <span class="text-[10px] text-gray-500 font-mono">{{ __('admin.maintenance.presentation') }}</span>
                    </div>

                    <!-- Mode Selector Radio -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label @click="mode = 'default'" class="p-3.5 rounded-lg border cursor-pointer transition-all flex items-start gap-3" :class="mode === 'default' ? 'bg-[#1A1A1A] border-[#00FF99] shadow-[0_0_15px_rgba(0,255,153,0.1)]' : 'bg-black/40 border-gray-800 hover:border-gray-700'">
                            <input type="radio" name="mode" value="default" x-model="mode" class="mt-0.5 text-[#00FF99] focus:ring-0">
                            <div>
                                <span class="text-xs font-bold text-white block">{{ __('admin.maintenance.template_cyber') }}</span>
                                <span class="text-[11px] text-gray-400 block mt-0.5">{{ __('admin.maintenance.template_desc') }}</span>
                            </div>
                        </label>

                        <label @click="mode = 'custom_html'" class="p-3.5 rounded-lg border cursor-pointer transition-all flex items-start gap-3" :class="mode === 'custom_html' ? 'bg-[#1A1A1A] border-[#00FF99] shadow-[0_0_15px_rgba(0,255,153,0.1)]' : 'bg-black/40 border-gray-800 hover:border-gray-700'">
                            <input type="radio" name="mode" value="custom_html" x-model="mode" class="mt-0.5 text-[#00FF99] focus:ring-0">
                            <div>
                                <span class="text-xs font-bold text-white block">{{ __('admin.maintenance.template_custom') }}</span>
                                <span class="text-[11px] text-gray-400 block mt-0.5">{{ __('admin.maintenance.template_custom_desc') }}</span>
                            </div>
                        </label>
                    </div>

                    <!-- Default Template Options -->
                    <div x-show="mode === 'default'" class="space-y-4 pt-2">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label for="title" class="font-bold text-white text-xs block mb-1">{{ __('admin.maintenance.tab_title') }}</label>
                                <input type="text" name="title" id="title" value="{{ $settings['title'] ?? 'David Bravo' }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg p-2.5 text-white font-mono text-xs">
                            </div>

                            <div>
                                <label for="logo_text" class="font-bold text-white text-xs block mb-1">{{ __('admin.maintenance.logo_text') }}</label>
                                <input type="text" name="logo_text" id="logo_text" value="{{ $settings['logo_text'] ?? '>_db' }}" placeholder=">_db" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg p-2.5 text-white font-mono text-xs">
                            </div>

                            <div>
                                <label for="accent_color" class="font-bold text-white text-xs block mb-1">{{ __('admin.maintenance.accent') }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="accent_color" id="accent_color" value="{{ $settings['accent_color'] ?? '#00FF99' }}" oninput="document.getElementById('accent_val').textContent = this.value" class="w-10 h-10 rounded border border-gray-700 bg-[#1A1A1A] cursor-pointer p-0.5">
                                    <span id="accent_val" class="font-mono text-xs text-[#00FF99]">{{ $settings['accent_color'] ?? '#00FF99' }}</span>
                                </div>
                            </div>

                            <div>
                                <label for="bg_color" class="font-bold text-white text-xs block mb-1">{{ __('admin.maintenance.bg') }}</label>
                                <div class="flex items-center gap-3">
                                    <input type="color" name="bg_color" id="bg_color" value="{{ $settings['bg_color'] ?? '#1A1A1A' }}" oninput="document.getElementById('bg_val').textContent = this.value" class="w-10 h-10 rounded border border-gray-700 bg-[#1A1A1A] cursor-pointer p-0.5">
                                    <span id="bg_val" class="font-mono text-xs text-gray-300">{{ $settings['bg_color'] ?? '#1A1A1A' }}</span>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="headline" class="font-bold text-white text-xs block mb-1">{{ __('admin.maintenance.secondary') }}</label>
                            <input type="text" name="headline" id="headline" value="{{ $settings['headline'] ?? 'PLATAFORMA EN CONSTRUCCIÓN' }}" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg p-2.5 text-white font-mono text-xs">
                        </div>

                        <div>
                            <label for="message" class="font-bold text-white text-xs block mb-1">{{ __('admin.maintenance.message') }}</label>
                            <textarea name="message" id="message" rows="3" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg p-2.5 text-white font-mono text-xs leading-relaxed">{{ $settings['message'] ?? '' }}</textarea>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="show_download_svg" id="show_download_svg" value="1" {{ !empty($settings['show_download_svg']) ? 'checked' : '' }} class="w-4 h-4 rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99] focus:ring-0 cursor-pointer">
                                <span class="text-xs text-gray-300 select-none">{{ __('admin.maintenance.show_svg') }}</span>
                            </label>

                            <label class="inline-flex items-center gap-2.5 cursor-pointer">
                                <input type="checkbox" name="show_admin_link" id="show_admin_link" value="1" {{ !empty($settings['show_admin_link']) ? 'checked' : '' }} class="w-4 h-4 rounded bg-[#1A1A1A] border-gray-700 text-[#00FF99] focus:ring-0 cursor-pointer">
                                <span class="text-xs text-gray-300 select-none">{{ __('admin.maintenance.show_admin_link') }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Custom HTML Mode -->
                    <div x-show="mode === 'custom_html'" class="space-y-2 pt-2" style="display: none;">
                        <label for="custom_html" class="font-bold text-white text-xs block">
                            Código HTML / Blade Personalizado al gusto:
                        </label>
                        <p class="text-gray-400 text-[11px]">{{ __('admin.maintenance.html_hint') }}</p>
                        <textarea name="custom_html" id="custom_html" rows="12" placeholder="<!DOCTYPE html><html><head>...</head><body>...</body></html>" class="w-full bg-[#121212] border border-gray-700 focus:border-[#00FF99] rounded-lg p-3 text-[#00FF99] font-mono text-xs transition-colors focus:outline-none placeholder-gray-600 leading-relaxed">{{ $settings['custom_html'] ?? '' }}</textarea>
                    </div>
                </div>

                <!-- Action Submit Bar -->
                <div class="flex items-center justify-between pt-2">
                    <a href="{{ route('admin.modules.index') }}" class="px-4 py-2 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 text-gray-300 hover:text-white rounded-lg transition-colors font-bold text-xs">
                        &larr; Volver a Módulos
                    </a>

                    <button type="submit" class="btn-neon text-xs py-2.5 px-6 shadow-sm">
                        <i class="fa-solid fa-floppy-disk mr-1.5"></i>
                        Guardar Configuración
                    </button>
                </div>
            </div>

            <!-- Right 1 Col: Live Preview Iframe & Information -->
            <div class="space-y-6">
                <!-- Preview Shell -->
                <div class="cyber-card p-4 space-y-3">
                    <div class="flex items-center justify-between border-b border-gray-800 pb-2">
                        <h3 class="text-xs font-bold text-white flex items-center gap-2">
                            <i class="fa-solid fa-eye text-[#00FF99]"></i>
                            <span>{{ __('admin.maintenance.preview_live') }}</span>
                        </h3>
                        <div class="flex items-center gap-1 bg-[#1A1A1A] p-0.5 rounded border border-gray-800">
                            <button type="button" @click="previewDevice = 'desktop'" :class="previewDevice === 'desktop' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'" class="px-2 py-0.5 rounded text-[10px]" title="{{ __('admin.maintenance.desktop') }}">
                                <i class="fa-solid fa-desktop"></i>
                            </button>
                            <button type="button" @click="previewDevice = 'mobile'" :class="previewDevice === 'mobile' ? 'bg-[#00FF99] text-black font-bold' : 'text-gray-400 hover:text-white'" class="px-2 py-0.5 rounded text-[10px]" title="{{ __('admin.maintenance.mobile') }}">
                                <i class="fa-solid fa-mobile-screen"></i>
                            </button>
                        </div>
                    </div>

                    <div class="relative bg-black rounded-lg overflow-hidden border border-gray-800 flex justify-center items-center" style="height: 480px;">
                        <iframe src="{{ route('admin.maintenance.preview') }}"
                                class="w-full h-full border-0 transition-all duration-300"
                                :style="previewDevice === 'mobile' ? 'max-width: 320px; height: 100%; border: 1px solid #333; border-radius: 8px;' : 'width: 100%; height: 100%;'"
                                title="{{ __('admin.maintenance.preview_title') }}"></iframe>
                    </div>

                    <div class="pt-1 text-center">
                        <a href="{{ route('admin.maintenance.preview') }}" target="_blank" class="text-[11px] text-[#00FF99] hover:underline inline-flex items-center gap-1.5">
                            <span>{{ __('admin.maintenance.fullscreen') }}</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                        </a>
                    </div>
                </div>

                <!-- Security & Governance Architecture Details -->
                <div class="p-4 rounded-xl bg-[#141414] border border-gray-800 text-gray-400 text-xs space-y-2">
                    <div class="font-bold text-white flex items-center gap-1.5 text-xs">
                        <i class="fa-solid fa-shield-halved text-[#00FF99]"></i>
                        <span>{{ __('admin.maintenance.bypass_title') }}</span>
                    </div>
                    <ul class="space-y-1.5 text-[11px] text-gray-400 list-disc list-inside">
                        <li>{!! __('admin.maintenance.bypass_routes_line') !!}</li>
                        <li>{{ __('admin.maintenance.bypass_users') }}</li>
                        <li>{!! __('admin.maintenance.bypass_health') !!}</li>
                        <li>{!! __('admin.maintenance.bypass_response') !!}</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection
