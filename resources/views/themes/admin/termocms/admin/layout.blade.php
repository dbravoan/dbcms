@php
    /** @var \App\Models\User|null $user */
    $user = $currentUser ?? auth()->user();
    $activeModules = $activeModules ?? [];
    $brand = $cmsBrand ?? brand();
    $cmsBrandName = $cmsBrandName ?? ($cmsBrand->name ?? (string) config('cms.name', 'CMS'));
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-termocms bg-slate-50 text-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', __('admin.dashboard.control_panel')) | {{ config('cms.name') }}</title>
    <link rel="icon" type="image/png" href="{{ theme_asset('termosalud-favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ theme_asset('termosalud-favicon.png') }}">
    <link rel="stylesheet" href="{{ theme_asset('termocms.css') }}?v={{ @filemtime(resource_path('views/themes/admin/termocms/assets/termocms.css')) ?: config('cms.version', '1.0.0') }}">
    <style nonce="{{ $cspNonce ?? '' }}">
        [x-cloak] { display: none !important; }
        body, html {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #F8FAFC;
            color: #0F172A;
        }
        .btn-neon {
            padding: 8px 16px;
            background-color: #0090A8;
            border: 1px solid #0090A8;
            color: #FFFFFF;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif;
            font-weight: 600;
            font-size: 0.85rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease-in-out;
            letter-spacing: 0.01em;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 1px 2px 0 rgba(0, 144, 168, 0.25);
        }
        .btn-neon:hover {
            background-color: #007B90;
            border-color: #007B90;
            box-shadow: 0 4px 12px rgba(0, 144, 168, 0.3);
            color: #FFFFFF;
            transform: translateY(-1px);
        }
        .cyber-card {
            background-color: #FFFFFF;
            border: 1px solid #E2E8F0;
            border-radius: 14px;
            box-shadow: 0 1px 3px 0 rgba(15, 23, 42, 0.06), 0 1px 2px -1px rgba(15, 23, 42, 0.04);
            color: #0F172A;
        }
        .nav-link-active {
            color: #0090A8 !important;
            background-color: rgba(0, 144, 168, 0.08) !important;
            border-left: 3px solid #0090A8;
            font-weight: 700;
        }
        .nav-sublink-active {
            color: #0090A8 !important;
            font-weight: 700;
        }
        /* Scrollbars personalizados globales (vertical y horizontal) */
        * {
            scrollbar-width: thin;
            scrollbar-color: #CBD5E1 transparent;
        }
        ::-webkit-scrollbar {
            width: 5px;
            height: 5px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: #CBD5E1;
            border-radius: 9999px;
            transition: background 0.2s ease, box-shadow 0.2s ease;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #0090A8;
        }
        /* Secondary Buttons & Spans in termoCMS */
        button.bg-gray-800, a.bg-gray-800, button.bg-gray-700, a.bg-gray-700,
        button.bg-gray-900, a.bg-gray-900, button.bg-\[\#1A1A1A\], a.bg-\[\#1A1A1A\],
        button.bg-\[\#181818\], a.bg-\[\#181818\], button.bg-\[\#141414\], a.bg-\[\#141414\],
        button.bg-\[\#121212\], a.bg-\[\#121212\], button.bg-\[\#101010\], a.bg-\[\#101010\],
        .bg-gray-800:not([class*="fixed inset-0"]):not(.peer-checked\:bg-):not(span),
        .bg-gray-700:not([class*="fixed inset-0"]):not(span),
        .bg-gray-900:not([class*="fixed inset-0"]):not(span) {
            background-color: #F8FAFC !important;
            border: 1px solid #CBD5E1 !important;
            color: #334155 !important;
            box-shadow: 0 1px 2px 0 rgba(15, 23, 42, 0.04) !important;
        }
        button.bg-gray-800:hover, a.bg-gray-800:hover, button.bg-gray-700:hover, a.bg-gray-700:hover,
        button.bg-\[\#1A1A1A\]:hover, a.bg-\[\#1A1A1A\]:hover, button.bg-\[\#181818\]:hover, a.bg-\[\#181818\]:hover,
        .hover\:bg-gray-700:hover, .hover\:bg-gray-800:hover {
            background-color: #F1F5F9 !important;
            border-color: #94A3B8 !important;
            color: #0F172A !important;
        }
        span.bg-gray-800, span.bg-gray-700, span.bg-gray-900,
        span.bg-\[\#1A1A1A\], span.bg-\[\#181818\], span.bg-\[\#141414\], span.bg-\[\#121212\],
        span.bg-black\/60, span.bg-black\/50, span[class*="bg-gray-800"] {
            background-color: #F1F5F9 !important;
            border: 1px solid #E2E8F0 !important;
            color: #475569 !important;
            font-weight: 600 !important;
        }
        /* Code, Pre & Technical Typography in termoCMS */
        code:not(.badge) {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace !important;
            background-color: #F1F5F9 !important;
            color: #0E7490 !important;
            border: 1px solid #CBD5E1 !important;
            border-radius: 6px !important;
            padding: 0.15rem 0.45rem !important;
            font-size: 0.85em !important;
            font-weight: 600 !important;
        }
        code.bg-black\/50, code.bg-black\/60, code.bg-black\/40,
        code.bg-\[\#1A1A1A\], code.bg-\[\#121212\], code.bg-\[\#141414\],
        code[class*="bg-black"], code[class*="bg-[#"],
        .bg-black\/50:not([class*="fixed inset-0"]):not([class*="backdrop-blur"]),
        .bg-black\/60:not([class*="fixed inset-0"]):not([class*="backdrop-blur"]) {
            background-color: #F1F5F9 !important;
            border-color: #CBD5E1 !important;
            color: #0E7490 !important;
        }
        code.text-\[\#00FF99\], code.text-white, code.text-gray-300 {
            color: #0E7490 !important;
        }
        pre {
            background-color: #F8FAFC !important;
            color: #0F172A !important;
            border: 1px solid #E2E8F0 !important;
            border-radius: 8px !important;
        }
        pre code {
            background-color: transparent !important;
            border: none !important;
            color: inherit !important;
        }
        .text-gray-200, .text-gray-300 {
            color: #334155 !important;
        }
        .text-gray-400, .text-gray-500 {
            color: #64748B !important;
        }
        .hover\:text-gray-200:hover, .hover\:text-white:hover {
            color: #0F172A !important;
        }
        .prompt-prefix {
            display: none !important;
        }
        td a.text-\[\#00FF99\], td button.text-\[\#00FF99\],
        td.text-right a.text-\[\#00FF99\], td.text-right button.text-\[\#00FF99\] {
            color: #0090A8 !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            font-weight: 600 !important;
        }
        td a.text-\[\#00FF99\]:hover, td button.text-\[\#00FF99\]:hover,
        td.text-right a.text-\[\#00FF99\]:hover, td.text-right button.text-\[\#00FF99\]:hover {
            color: #007B90 !important;
        }
        td a.text-gray-400, td button.text-gray-400,
        td.text-right a.text-gray-400, td.text-right button.text-gray-400 {
            color: #64748B !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            font-weight: 500 !important;
        }
        td a.text-red-400, td button.text-red-400,
        td.text-right a.text-red-400, td.text-right button.text-red-400,
        .text-red-400 {
            color: #E11D48 !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            font-weight: 600 !important;
        }
        td a.text-red-400:hover, td button.text-red-400:hover,
        td.text-right a.text-red-400:hover, td.text-right button.text-red-400:hover,
        .hover\:text-red-300:hover, .hover\:text-red-400:hover {
            color: #BE123C !important;
        }
        .text-amber-400, .text-amber-500 {
            color: #D97706 !important;
        }
        .text-emerald-400, .text-green-400 {
            color: #059669 !important;
        }
        .text-purple-400, .text-purple-300 {
            color: #7C3AED !important;
        }
        .text-sky-400, .text-blue-400 {
            color: #0284C7 !important;
        }
        .w-7.h-7.rounded-full.bg-gray-800,
        .w-7.h-7.rounded-full {
            background-color: rgba(0, 144, 168, 0.08) !important;
            border: 1px solid rgba(0, 144, 168, 0.25) !important;
            color: #0090A8 !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            font-weight: 700 !important;
        }
        .w-12.h-12.rounded-xl.bg-\[\#1A1A1A\],
        .w-12.h-12.rounded-xl {
            background-color: rgba(0, 144, 168, 0.08) !important;
            border: 1px solid rgba(0, 144, 168, 0.25) !important;
            color: #0090A8 !important;
            font-family: 'Plus Jakarta Sans', system-ui, sans-serif !important;
            font-weight: 700 !important;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-admin-shell class="h-full flex overflow-hidden" x-data="{ mobileNavOpen: false }" @keydown.escape.window="mobileNavOpen = false">

    <!-- Mobile Navigation Drawer Backdrop -->
    <div x-show="mobileNavOpen"
         @click="mobileNavOpen = false"
         x-transition.opacity
         x-cloak
         class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-40 md:hidden"
         style="display: none;"></div>

    <!-- Sidebar Navigation -->
    <aside id="admin-navigation" :class="mobileNavOpen ? 'translate-x-0 shadow-2xl' : '-translate-x-full md:translate-x-0'"
           class="fixed md:static inset-y-0 left-0 w-64 h-full bg-white border-r border-slate-200 shadow-sm flex flex-col shrink-0 z-50 md:z-20 overflow-hidden transition-transform duration-300 ease-in-out select-none">
        <!-- Brand Logo Header -->
        <div class="px-4 py-3.5 border-b border-slate-200 bg-white flex items-center justify-between gap-3 shrink-0 z-10">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2.5 group min-w-0" title="{{ brand()->name }} &bull; {{ brand()->tagline }}">
                <img src="{{ theme_asset('termosalud-favicon.png') }}" alt="{{ brand()->name }}"
                     class="h-9 w-9 rounded-xl shadow-sm ring-1 ring-slate-900/5 shrink-0 object-cover transition-transform duration-200 group-hover:scale-105">
                <span class="flex flex-col min-w-0 leading-none">
                    <span class="termo-wordmark">
                        <img src="{{ theme_asset('termosalud-logo.png') }}" alt="">
                    </span>
                    <span class="mt-1.5 flex items-center gap-1.5 min-w-0">
                        <span class="text-[9px] font-bold tracking-[0.2em] text-slate-400 uppercase leading-none">termo<span class="text-[#0090A8]">CMS</span></span>
                        @if(!empty($brand->edition))
                            <span class="h-2.5 w-px bg-slate-200 shrink-0"></span>
                            <span class="text-[9px] font-bold tracking-wider text-teal-600 uppercase leading-none">{{ mb_strtoupper($brand->edition) }}</span>
                        @endif
                    </span>
                </span>
            </a>
            <button type="button" @click="mobileNavOpen = false" aria-label="{{ __('admin.common.close') }}" class="md:hidden text-slate-400 hover:text-slate-700 p-2 min-h-10 min-w-10 text-lg leading-none shrink-0">&times;</button>
        </div>

        <!-- Grouped Navigation Links -->
        <nav class="flex-1 min-h-0 overflow-y-auto p-3 space-y-4 font-sans text-xs sidebar-scroll">
            @if(isset($navSections) && (is_array($navSections) || $navSections instanceof \Traversable))
                @foreach($navSections as $sectionKey => $section)
                    @php
                        $secTitle = is_object($section) ? $section->title : ($section['title'] ?? ucfirst((string) $sectionKey));
                        $secItems = is_object($section) ? $section->items : ($section['items'] ?? []);
                        $secVisible = is_object($section) ? ($section->visible ?? true) : ($section['visible'] ?? true);
                    @endphp
                    @if($secVisible && !empty($secItems))
                        <div class="space-y-1">
                            <div class="text-[10px] text-slate-400 uppercase font-bold px-3 py-1 tracking-wider">{{ $secTitle }}</div>
                            @foreach($secItems as $item)
                                @php
                                    $itemVisible = is_object($item) ? ($item->visible ?? true) : ($item['visible'] ?? true);
                                @endphp
                                @if($itemVisible)
                                    @php
                                        $itemId = is_object($item) ? $item->id : ($item['id'] ?? null);
                                        $itemLabel = is_object($item) ? $item->label : ($item['label'] ?? '');
                                        $itemRoute = is_object($item) ? $item->route : ($item['route'] ?? ($item['url'] ?? ''));
                                        $itemUrl = (is_object($item) && method_exists($item, 'getUrl'))
                                            ? $item->getUrl()
                                            : (\Illuminate\Support\Facades\Route::has($itemRoute) ? route($itemRoute, is_object($item) ? ($item->routeParameters ?? []) : ($item['routeParameters'] ?? [])) : url($itemRoute));
                                        $itemIcon = is_object($item) ? $item->icon : ($item['icon'] ?? 'fa-solid fa-circle');
                                        $itemActive = is_object($item) ? $item->active : ($item['active'] ?? false);
                                        $itemCreateRoute = is_object($item) ? $item->createRoute : ($item['createRoute'] ?? null);
                                        $itemCreateTooltip = is_object($item) ? $item->createTooltip : ($item['createTooltip'] ?? 'Nuevo');
                                        $itemBadgeText = is_object($item) ? ($item->badgeText ?? null) : ($item['badge'] ?? ($item['badgeText'] ?? null));
                                        $itemBadgeClass = is_object($item) ? $item->badgeClass : ($item['badge_class'] ?? null);
                                        $itemChildren = is_object($item) ? ($item->children ?? []) : ($item['children'] ?? []);
                                    @endphp
                                    <div class="space-y-0.5">
                                        <div class="flex items-center justify-between px-3 py-2 rounded-lg text-slate-600 hover:text-slate-900 hover:bg-slate-100 transition-colors {{ $itemActive ? 'nav-link-active' : '' }}">
                                            <a href="{{ $itemUrl }}"
                                               @if(!empty($itemId)) id="{{ $itemId }}" @endif
                                               @if($itemActive) aria-current="page" @endif
                                               class="flex items-center gap-3 flex-1 min-w-0">
                                                <i class="{{ $itemIcon }} text-sm w-4 text-center shrink-0"></i>
                                                <span class="truncate">{{ $itemLabel }}</span>
                                            </a>
                                            @if($itemCreateRoute && \Illuminate\Support\Facades\Route::has($itemCreateRoute))
                                                <a href="{{ route($itemCreateRoute) }}" title="{{ $itemCreateTooltip }}" class="px-1.5 py-0.5 rounded bg-white border border-slate-200 hover:border-[#0090A8] text-[#0090A8] text-[10px] font-bold shrink-0 ml-1">
                                                    +
                                                </a>
                                            @endif
                                            @if(!empty($itemBadgeText))
                                                <span class="text-[9px] px-1.5 py-0.5 rounded {{ $itemBadgeClass ?? 'bg-teal-50 text-teal-700 border border-teal-200' }} font-bold shrink-0 ml-1">
                                                    {{ $itemBadgeText }}
                                                </span>
                                            @endif
                                        </div>
                                        @if(!empty($itemChildren))
                                            @foreach($itemChildren as $child)
                                                @php
                                                    $childRoute = is_object($child) ? $child->route : ($child['route'] ?? '');
                                                    $childUrl = \Illuminate\Support\Facades\Route::has($childRoute) ? route($childRoute) : url($childRoute);
                                                    $childLabel = is_object($child) ? $child->label : ($child['label'] ?? '');
                                                    $childActive = is_object($child) ? $child->active : ($child['active'] ?? false);
                                                @endphp
                                                <a href="{{ $childUrl }}"
                                                   @if($childActive) aria-current="page" @endif
                                                   class="flex items-center gap-3 pl-8 pr-3 py-1.5 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 text-[11px] transition-colors {{ $childActive ? 'nav-sublink-active' : '' }}">
                                                    <span>↳</span>
                                                    <span class="truncate">{{ $childLabel }}</span>
                                                </a>
                                            @endforeach
                                        @endif
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    @endif
                @endforeach
            @endif
        </nav>

        <!-- Sidebar Footer -->
        <div class="p-4 border-t border-slate-200 bg-slate-50 shrink-0 font-sans text-xs">
            @unless(app()->isProduction())
            <div class="mb-3">
                <a href="http://localhost:8088" id="nav-adminer" target="_blank" class="w-full flex items-center justify-center gap-2 py-1.5 px-2 bg-white hover:bg-slate-100 border border-slate-200 hover:border-slate-300 rounded-lg text-[11px] text-slate-600 hover:text-[#0090A8] transition-colors shadow-sm">
                    <i class="fa-solid fa-database text-xs text-slate-400"></i>
                    <span>{{ __('admin.layout.adminer') }}</span>
                    <span class="text-slate-400">&nearr;</span>
                </a>
            </div>
            @endunless
            <div class="flex items-center justify-between">
                <div class="truncate max-w-[140px]">
                    <div class="text-slate-900 font-bold truncate">{{ $user?->name ?? 'Usuario' }}</div>
                    @if($isAdmin)
                        <span class="text-[9px] bg-teal-50 text-teal-700 px-1.5 py-0.5 rounded border border-teal-200 font-bold">ADMIN</span>
                    @elseif($isEditor)
                        <span class="text-[9px] bg-blue-50 text-blue-700 px-1.5 py-0.5 rounded border border-blue-200 font-bold">EDITOR</span>
                    @elseif($isAuthor)
                        <span class="text-[9px] bg-purple-50 text-purple-700 px-1.5 py-0.5 rounded border border-purple-200 font-bold">AUTHOR</span>
                    @elseif($isMarketing)
                        <span class="text-[9px] bg-amber-50 text-amber-700 px-1.5 py-0.5 rounded border border-amber-200 font-bold">MARKETING</span>
                    @elseif($isViewer)
                        <span class="text-[9px] bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded border border-slate-300 font-bold">VIEWER</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="text-rose-600 hover:text-rose-700 font-semibold">{{ __('admin.layout.logout') }}</button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Shell -->
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
        <!-- Top Action Bar -->
        <header class="h-16 bg-white border-b border-slate-200 font-sans flex items-center justify-between px-4 sm:px-6 shrink-0 text-xs shadow-sm">
            <div class="flex items-center gap-2 sm:gap-4 min-w-0 flex-none sm:flex-1">
                <button type="button"
                        @click="mobileNavOpen = !mobileNavOpen"
                        class="md:hidden p-2 min-h-[2.5rem] min-w-[2.5rem] inline-flex items-center justify-center text-slate-600 hover:text-slate-900 bg-slate-100 border border-slate-200 rounded-lg focus:outline-none shrink-0"
                        aria-label="Abrir menú" aria-controls="admin-navigation" :aria-expanded="mobileNavOpen.toString()">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <div class="hidden sm:flex items-center gap-2 min-w-0">
                    <img src="{{ theme_asset('termosalud-favicon.png') }}" alt="{{ brand()->name }}" class="w-6 h-6 rounded-md object-cover ring-1 ring-slate-900/5 hidden sm:inline shrink-0">
                    <span class="text-slate-500 hidden sm:inline shrink-0">{{ __('admin.layout.backoffice') }}:</span>
                    <span class="text-[#0090A8] font-bold truncate max-w-[40vw] sm:max-w-[220px]">{{ brand()->name }}</span>
                    <span class="text-slate-300 hidden sm:inline shrink-0">/</span>
                    <span class="text-slate-800 font-bold truncate min-w-0">@yield('title')</span>
                </div>
            </div>

            <div class="flex items-center gap-1.5 sm:gap-3 shrink-0">
                @if(!empty($isMaintenanceActive))
                    <a href="{{ route('admin.modules.settings', ['identifier' => 'maintenance']) }}" title="Haga clic para gestionar el mantenimiento" class="hidden lg:flex items-center gap-1.5 px-3 py-1 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-700 rounded-full text-[10px] font-bold transition-all">
                        <i class="fa-solid fa-triangle-exclamation text-[10px]"></i>
                        <span>{{ __('admin.layout.maintenance_active') }}</span>
                    </a>
                @endif

                <!-- 1-Click Persona Switcher -->
                @if(app()->environment(['local', 'testing']) && config('cms.quick_login', false))
                <div id="nav-persona-switcher" class="hidden lg:flex items-center gap-1 bg-slate-100 border border-slate-200 p-1 rounded-lg">
                    <span class="text-[11px] text-slate-500 px-2">{{ __('admin.layout.role') }}:</span>
                    <a href="{{ route('admin.quick-login', 'admin') }}" title="Probar como Admin" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('admin') ? 'bg-[#0090A8] text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">Admin</a>
                    <a href="{{ route('admin.quick-login', 'editor') }}" title="Probar como Editor" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('editor') ? 'bg-[#0090A8] text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">Editor</a>
                    <a href="{{ route('admin.quick-login', 'author') }}" title="Probar como Author" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('author') ? 'bg-[#0090A8] text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">Author</a>
                    <a href="{{ route('admin.quick-login', 'marketing') }}" title="Probar como Marketing" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('marketing') ? 'bg-[#0090A8] text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">Mkt</a>
                    <a href="{{ route('admin.quick-login', 'viewer') }}" title="Probar como Viewer" class="px-2 py-1 rounded {{ auth()->user()?->hasRole('viewer') ? 'bg-[#0090A8] text-white font-bold shadow-sm' : 'text-slate-600 hover:text-slate-900' }}">View</a>
                </div>
                @endif

                <!-- Backoffice language switcher -->
                <x-admin.locale-switcher />

                <!-- Onboarding Tour Button -->
                <button type="button"
                        data-dispatch="start-onboarding-tour"
                        class="px-2.5 sm:px-3 py-1.5 bg-slate-100 hover:bg-slate-200 border border-slate-300 text-slate-700 hover:text-[#0090A8] rounded-lg font-bold transition-all flex items-center gap-1.5 text-xs"
                        title="{{ __('admin.layout.help_tooltip') }}" aria-label="{{ __('admin.layout.help') }}">
                    <i class="fa-solid fa-circle-question sm:hidden" aria-hidden="true"></i>
                    <span class="hidden sm:inline">{{ __('admin.layout.help') }}</span>
                </button>

                <!-- Notifications Center Bell -->
                <div x-data="{
                    open: false,
                    unreadCount: 0,
                    notifications: [],
                    emailEnabled: true,
                    emailPreferenceError: null,
                    loading: false,
                    notifTimer: null,

                    init() {
                        this.fetchNotifications();
                        // Live updates: refresh the badge periodically.
                        this.notifTimer = setInterval(() => this.fetchNotifications(), 25000);
                    },

                    destroy() {
                        if (this.notifTimer) {
                            clearInterval(this.notifTimer);
                        }
                    },

                    fetchNotifications() {
                        fetch('{{ route('admin.notifications.index') }}')
                            .then(res => res.json())
                            .then(data => {
                                this.unreadCount = data.unread_count || 0;
                                this.notifications = data.notifications || [];
                                this.emailEnabled = data.email_enabled !== false;
                            })
                            .catch(() => {});
                    },

                    saveEmailPreference() {
                        this.emailPreferenceError = null;
                        fetch('{{ route('admin.notifications.email-preference') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({ enabled: this.emailEnabled })
                        }).then(res => {
                            if (!res.ok) throw new Error('preference');
                            return res.json();
                        }).then(data => { this.emailEnabled = data.email_enabled; })
                          .catch(() => {
                              this.emailEnabled = !this.emailEnabled;
                              this.emailPreferenceError = @js(__('mail.module_notification.preference_failed'));
                          });
                    },

                    markRead(id, actionUrl) {
                        fetch(@js(route('admin.notifications.read', ['id' => '__ID__'])).replace('__ID__', encodeURIComponent(id)), {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        }).then(() => {
                            this.fetchNotifications();
                            if (actionUrl) {
                                window.location.href = actionUrl;
                            }
                        });
                    },

                    markAllRead() {
                        fetch('{{ route('admin.notifications.mark-all-read') }}', {
                            method: 'POST',
                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                        }).then(() => {
                            this.unreadCount = 0;
                            this.notifications = [];
                        });
                    }
                }" class="relative">
                    <button type="button"
                            @click="open = !open; if(open) fetchNotifications()"
                            class="relative p-2 text-slate-600 hover:text-slate-900 bg-slate-100 hover:bg-slate-200 border border-slate-300 rounded-lg transition-all flex items-center justify-center"
                            title="Centro de notificaciones y alertas" aria-label="{{ __('admin.layout.notifications') }}" :aria-expanded="open.toString()">
                        <i class="fa-solid fa-bell text-sm"></i>
                        <span x-show="unreadCount > 0"
                              x-cloak
                              x-text="unreadCount"
                              class="absolute -top-1 -right-1 px-1.5 py-0.2 bg-[#0090A8] text-white font-bold text-[9px] rounded-full min-w-[16px] text-center shadow">
                        </span>
                    </button>

                    <!-- Dropdown Panel -->
                    <div x-show="open"
                         x-cloak
                         @click.outside="open = false"
                         x-transition:enter="transition ease-out duration-150"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         class="fixed inset-x-4 top-16 mt-2 sm:absolute sm:inset-x-auto sm:right-0 sm:top-auto w-auto sm:w-96 max-w-[calc(100vw-2rem)] bg-white border border-slate-200 shadow-xl font-sans rounded-xl z-50 overflow-hidden text-xs">
                        
                        <div class="p-3 bg-slate-50 border-b border-slate-200 text-slate-800 flex items-center justify-between">
                            <span class="font-bold flex items-center gap-1.5">
                                <span class="text-[#0090A8]">&gt;</span> {{ __('admin.layout.notifications') }}
                            </span>
                            <button x-show="unreadCount > 0"
                                    @click="markAllRead()"
                                    class="text-[10px] text-slate-500 hover:text-[#0090A8] underline">
                                {{ __('admin.layout.mark_all_read') }}
                            </button>
                        </div>

                        @include('admin.partials.notification-email-preference')

                        <div class="max-h-72 overflow-y-auto divide-y divide-slate-100">
                            <template x-if="notifications.length === 0">
                                <div class="py-8 text-center text-slate-400 text-xs">
                                    <i class="fa-solid fa-bell-slash mr-1.5"></i> {{ __('admin.layout.no_notifications') }}
                                </div>
                            </template>

                            <template x-for="n in notifications" :key="n.id">
                                <div @click="markRead(n.id, n.action_url)"
                                     class="p-3 hover:bg-slate-50 cursor-pointer transition-colors flex items-start gap-2.5">
                                    <template x-if="n.type === 'lead'">
                                        <i class="fa-solid fa-inbox text-[#0090A8] text-base shrink-0 mt-0.5"></i>
                                    </template>
                                    <template x-if="n.type === 'order'">
                                        <i class="fa-solid fa-cart-shopping text-[#0090A8] text-base shrink-0 mt-0.5"></i>
                                    </template>
                                    <template x-if="n.type !== 'lead' && n.type !== 'order'">
                                        <i class="fa-solid fa-bell text-[#0090A8] text-base shrink-0 mt-0.5"></i>
                                    </template>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1">
                                            <span class="font-bold text-slate-800 truncate text-[11px]" x-text="n.title"></span>
                                            <span class="text-[9px] uppercase px-1 rounded bg-slate-100 text-slate-600 border border-slate-200 shrink-0" x-text="n.module"></span>
                                        </div>
                                        <p class="text-[10px] text-slate-500 mt-0.5 line-clamp-2" x-text="n.message"></p>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Direct Frontoffice Link -->
                <a href="{{ url('/') }}"
                   target="_blank" aria-label="{{ __('admin.layout.view_site') }}"
                   class="btn-neon text-xs py-1.5 px-2.5 sm:px-3">
                    <span class="hidden sm:inline">{{ __('admin.layout.view_site') }}</span><span aria-hidden="true">&nearr;</span>
                </a>
            </div>
        </header>

        <!-- Main Body Area -->
        <main id="admin-content" tabindex="-1" class="flex-1 min-h-0 overflow-y-auto p-4 sm:p-6 md:p-8">
            <!-- Flash Message Alerts -->
            @if(session('success'))
                <div data-dismissable class="mb-6 p-4 rounded-xl bg-teal-50 border border-teal-200 text-teal-800 font-sans text-xs flex items-center justify-between shadow-sm">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-base text-[#0090A8]"></i>
                        <span>{{ session('success') }}</span>
                    </div>
                    <button type="button" data-dismiss aria-label="{{ __('admin.common.close') }}" class="text-slate-400 hover:text-slate-700">&times;</button>
                </div>
            @endif

            @if(isset($errors) && $errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 font-sans text-xs shadow-sm">
                    <ul class="list-disc pl-4 space-y-1">
                        @foreach($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Unified Canonical Components -->
    <x-admin.filemanager-modal />
    <x-admin.delete-modal />
    <x-admin.confirm-modal />
    <x-admin.onboarding-tutorial />

    <!-- Autonomous Module UI Stacks & Injections -->
    @stack('admin_modals')
    @stack('admin_scripts')

</body>
</html>
