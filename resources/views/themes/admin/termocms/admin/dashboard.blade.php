@extends('admin.layout')

@section('title', __('admin.dashboard.control_panel'))

@section('content')
<div class="space-y-8 sm:space-y-10 pb-12 font-sans">
    <!-- Header Hero Banner -->
    <div class="cyber-card p-6 sm:p-7 relative overflow-hidden bg-white border border-slate-200 shadow-sm rounded-2xl">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-teal-50 text-teal-700 border border-teal-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-[#0090A8] animate-pulse"></span>
                        {{ __('admin.dashboard.platform_corporate') }} &bull; v{{ config('cms.version') }}
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight flex flex-wrap items-center gap-2.5">
                    {{ __('admin.dashboard.control_panel') }} <span class="text-[#0090A8]">{{ $cmsBrandName }}</span>
                </h1>
                <p class="text-xs text-slate-500 mt-1.5">
                    {{ __('admin.dashboard.active_session_as') }} <span class="text-slate-800 font-bold">{{ auth()->user()?->name }}</span>
                    ({{ __('admin.dashboard.role') }}: <span class="text-teal-700 font-bold uppercase">{{ auth()->user()?->roles->pluck('name')->join(', ') ?: __('admin.dashboard.no_role') }}</span>)
                </p>
            </div>

            <!-- Header Action Buttons -->
            <div class="flex flex-wrap items-center gap-3">
                @stack('admin_dashboard_actions')
                <button type="button"
                        data-dispatch="start-onboarding-tour"
                        class="btn-neon text-xs py-2.5 px-4 shadow-sm"
                        title="{{ __('admin.dashboard.system_tour_tooltip') }}">
                    <span>{{ __('admin.dashboard.system_tour') }}</span>
                </button>
                @unless(app()->isProduction())
                <a href="http://localhost:8088"
                   target="_blank"
                   class="px-4 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 hover:border-slate-300 text-slate-700 hover:text-[#0090A8] rounded-lg text-xs font-semibold transition-all flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-database text-slate-400"></i>
                    <span>{{ __('admin.layout.adminer') }}</span>
                    <span class="text-slate-400">&nearr;</span>
                </a>
                @endunless
            </div>
        </div>
    </div>

    <!-- KPI Statistics Grid -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-chart-simple text-[#0090A8]"></i> {{ __('admin.dashboard.metrics') }}
            </h2>
            <span class="text-[11px] text-slate-400">{{ __('admin.dashboard.realtime') }}</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 sm:gap-6">
            <!-- Páginas -->
            @if(!empty($hasPages) && Route::has('admin.pages.index'))
            <a href="{{ route('admin.pages.index') }}" class="cyber-card p-5 min-w-0 hover:border-teal-300 hover:shadow-md transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-[11px] uppercase font-semibold group-hover:text-slate-700">{{ __('admin.dashboard.pages') }}</span>
                        <span class="p-1.5 rounded-lg bg-teal-50 border border-teal-200 flex items-center justify-center text-[#0090A8]"><i class="fa-solid fa-file-lines text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 group-hover:text-[#0090A8] transition-colors">{{ $stats['total_pages'] }}</div>
                </div>
                <div class="text-[10px] text-[#0090A8] mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between font-semibold">
                    <span>{{ __('admin.dashboard.pages_hint') }}</span>
                    <span class="text-slate-400 group-hover:text-[#0090A8]">&rarr;</span>
                </div>
            </a>
            @endif

            @foreach($moduleDashboard as $contribution)
                @if(isset($contribution['views']['metrics']))
                    @include($contribution['views']['metrics'], $contribution['data'])
                @endif
            @endforeach



            <!-- Módulos -->
            @if(!empty($canManageModules))
            <a href="{{ route('admin.modules.index') }}" class="cyber-card p-5 min-w-0 hover:border-teal-300 hover:shadow-md transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-[11px] uppercase font-semibold group-hover:text-slate-700">{{ __('admin.dashboard.modules') }}</span>
                        <span class="p-1.5 rounded-lg bg-teal-50 border border-teal-200 flex items-center justify-center text-[#0090A8]"><i class="fa-solid fa-puzzle-piece text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 group-hover:text-[#0090A8] transition-colors">{{ $stats['installed_modules'] }}</div>
                </div>
                <div class="text-[10px] text-[#0090A8] mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between font-semibold">
                    <span>{{ __('admin.dashboard.modules_hint') }}</span>
                    <span class="text-slate-400 group-hover:text-[#0090A8]">&rarr;</span>
                </div>
            </a>
            @endif

            <!-- Mercados -->
            @if(!empty($canManageModules))
            <a href="{{ route('admin.markets.index') }}" class="cyber-card p-5 min-w-0 hover:border-teal-300 hover:shadow-md transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-[11px] uppercase font-semibold group-hover:text-slate-700">{{ __('admin.dashboard.markets') }}</span>
                        <span class="p-1.5 rounded-lg bg-teal-50 border border-teal-200 flex items-center justify-center text-[#0090A8]"><i class="fa-solid fa-globe text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 group-hover:text-[#0090A8] transition-colors">{{ $stats['active_markets'] }}</div>
                </div>
                <div class="text-[10px] text-[#0090A8] mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between font-semibold">
                    <span>{{ __('admin.dashboard.markets_hint') }}</span>
                    <span class="text-slate-400 group-hover:text-[#0090A8]">&rarr;</span>
                </div>
            </a>
            @endif

            <!-- Usuarios -->
            @if(!empty($canManageUsers))
            <a href="{{ route('admin.users.index') }}" class="cyber-card p-5 min-w-0 hover:border-teal-300 hover:shadow-md transition-all duration-200 group flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between text-slate-500 mb-2">
                        <span class="text-[11px] uppercase font-semibold group-hover:text-slate-700">{{ __('admin.dashboard.users') }}</span>
                        <span class="p-1.5 rounded-lg bg-teal-50 border border-teal-200 flex items-center justify-center text-[#0090A8]"><i class="fa-solid fa-users text-xs"></i></span>
                    </div>
                    <div class="text-2xl sm:text-3xl font-extrabold text-slate-900 group-hover:text-[#0090A8] transition-colors">{{ $stats['total_users'] }}</div>
                </div>
                <div class="text-[10px] text-[#0090A8] mt-3 pt-2.5 border-t border-slate-100 flex items-center justify-between font-semibold">
                    <span>{{ __('admin.dashboard.users_hint') }}</span>
                    <span class="text-slate-400 group-hover:text-[#0090A8]">&rarr;</span>
                </div>
            </a>
            @endif
        </div>
    </div>

    <!-- Quick Creation & Action Hub -->
    <div>
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-xs font-bold text-slate-500 uppercase tracking-wider flex items-center gap-2">
                <i class="fa-solid fa-bolt text-[#0090A8]"></i> {{ __('admin.dashboard.quick_actions') }}
            </h2>
            <span class="text-[11px] text-slate-400">{{ __('admin.dashboard.frequent_flows') }}</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 sm:gap-6">
            @if(!empty($hasPages) && Route::has('admin.pages.create'))
            <a href="{{ route('admin.pages.create') }}" class="cyber-card p-5 hover:border-teal-300 hover:shadow-md transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-teal-50 border border-teal-200 group-hover:border-teal-400 transition-colors flex items-center justify-center text-[#0090A8] text-base"><i class="fa-solid fa-file-lines"></i></span>
                    <span class="text-slate-400 group-hover:text-[#0090A8] text-xs transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm group-hover:text-[#0090A8] transition-colors">{{ __('admin.dashboard.new_page') }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('admin.dashboard.new_page_desc') }}</p>
            </a>
            @endif

            @foreach($moduleDashboard as $contribution)
                @if(isset($contribution['views']['actions']))
                    @include($contribution['views']['actions'], $contribution['data'])
                @endif
            @endforeach


            @if(!empty($hasSeo) && Route::has('admin.seo.audit'))
            <a href="{{ route('admin.seo.audit') }}" class="cyber-card p-5 hover:border-teal-300 hover:shadow-md transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-teal-50 border border-teal-200 group-hover:border-teal-400 transition-colors flex items-center justify-center text-[#0090A8] text-base"><i class="fa-solid fa-bullseye"></i></span>
                    <span class="text-slate-400 group-hover:text-[#0090A8] text-xs transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm group-hover:text-[#0090A8] transition-colors">{{ __('admin.dashboard.seo_audit') }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('admin.dashboard.seo_audit_desc') }}</p>
            </a>
            @endif

            @if(Route::has('admin.modules.index'))
            @if(!empty($canManageModules))
            <a href="{{ route('admin.modules.index') }}" class="cyber-card p-5 hover:border-teal-300 hover:shadow-md transition-all duration-200 group block">
                <div class="flex items-center justify-between mb-3">
                    <span class="w-10 h-10 rounded-lg bg-teal-50 border border-teal-200 group-hover:border-teal-400 transition-colors flex items-center justify-center text-[#0090A8] text-base"><i class="fa-solid fa-puzzle-piece"></i></span>
                    <span class="text-slate-400 group-hover:text-[#0090A8] text-xs transition-colors">&rarr;</span>
                </div>
                <h3 class="font-bold text-slate-900 text-sm group-hover:text-[#0090A8] transition-colors">{{ __('admin.dashboard.modules_marketplace') }}</h3>
                <p class="text-xs text-slate-500 mt-1 leading-relaxed">{{ __('admin.dashboard.modules_marketplace_desc', ['app' => config('cms.name')]) }}</p>
            </a>
            @endif
            @endif
        </div>
    </div>

    <!-- Two Column Detail Section -->
    @if($moduleDashboard !== [])
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 sm:gap-8">

            @foreach($moduleDashboard as $contribution)
                @if(isset($contribution['views']['details']))
                    @include($contribution['views']['details'], $contribution['data'])
                @endif
            @endforeach

    </div>
    @else
    <!-- Zero Modules Clean Install Notice -->
    <div class="cyber-card p-8 text-center border border-slate-200 bg-white">
        <div class="w-16 h-16 rounded-2xl bg-teal-50 border border-teal-200 text-[#0090A8] flex items-center justify-center text-2xl mx-auto mb-4">
            <i class="fa-solid fa-microchip"></i>
        </div>
        <h3 class="text-lg font-bold text-slate-800">{{ __('admin.dashboard.clean_install_title') }}</h3>
        <p class="text-xs text-slate-500 max-w-xl mx-auto mt-2 leading-relaxed">
            {{ $cmsBrandName }} {{ __('admin.dashboard.clean_install_desc') }}
        </p>
        <div class="mt-6 flex flex-wrap justify-center gap-4">
            @if(!empty($canManageModules))
            <a href="{{ route('admin.modules.index') }}" class="btn-neon text-xs py-2.5 px-6">
                <span>{{ __('admin.dashboard.explore_modules') }}</span>
            </a>
            @endif
            @if(!empty($canManageUsers))
            <a href="{{ route('admin.users.index') }}" class="px-5 py-2.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 hover:text-slate-900 rounded-lg text-xs font-semibold transition-all shadow-sm">
                <span>{{ __('admin.dashboard.manage_users') }}</span>
            </a>
            @endif
        </div>
    </div>
    @endif

    <!-- Architecture & Diagnostics Overview -->
    <div class="cyber-card p-6 border border-slate-200 bg-white">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 border-b border-slate-100 gap-2">
            <div class="flex items-center gap-2 text-xs font-bold text-slate-800">
                <i class="fa-solid fa-server text-[#0090A8]"></i> {{ __('admin.dashboard.architecture') }}
            </div>
            <div class="text-[11px] text-slate-500 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-[#0090A8] inline-block animate-pulse"></span>
                <span>{{ __('admin.dashboard.environment') }}: {{ app()->environment() }}</span>
            </div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 pt-5 text-xs">
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="text-[10px] text-slate-500 uppercase font-semibold">{{ __('admin.dashboard.framework') }}</div>
                <div class="text-slate-900 font-bold mt-1">Laravel {{ app()->version() }}</div>
                <div class="text-[10px] text-teal-700 mt-1">PHP {{ PHP_MAJOR_VERSION }}.{{ PHP_MINOR_VERSION }} </div>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="text-[10px] text-slate-500 uppercase font-semibold">{{ __('admin.dashboard.database') }}</div>
                <div class="text-slate-900 font-bold mt-1">{{ strtoupper((string) config('database.default')) }}</div>
                <div class="text-[10px] text-teal-700 mt-1">{{ __('admin.dashboard.configuration_only') }}</div>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="text-[10px] text-slate-500 uppercase font-semibold">{{ __('admin.dashboard.ai_protocol') }}</div>
                <div class="text-slate-900 font-bold mt-1">{{ __('admin.dashboard.mcp_endpoint') }}</div>
                <div class="text-[10px] text-teal-700 mt-1">{{ __('admin.dashboard.configuration_only') }}</div>
            </div>
            <div class="p-3 bg-slate-50 rounded-lg border border-slate-200">
                <div class="text-[10px] text-slate-500 uppercase font-semibold">{{ __('admin.dashboard.multi_market') }}</div>
                <div class="text-slate-900 font-bold mt-1">{{ __('admin.dashboard.rfc') }}</div>
                <div class="text-[10px] text-teal-700 mt-1">{{ __('admin.dashboard.i18n_geo') }}</div>
            </div>
        </div>
    </div>
</div>
@endsection
