<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full theme-termocms bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title') | {{ config('cms.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('themes/admin/termocms/termosalud-favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('themes/admin/termocms/termosalud-favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('themes/admin/termocms/termocms.css') }}">
    <style nonce="{{ $cspNonce ?? '' }}">
        body, html {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #F8FAFC;
            color: #0F172A;
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-admin-shell class="min-h-full flex flex-col justify-between py-10 px-4 sm:px-6 lg:px-8 bg-slate-50 text-slate-800">
    <!-- Header Brand -->
    <header class="w-full max-w-2xl mx-auto flex items-center justify-between pb-6">
        <a href="{{ url('/') }}" class="flex items-center gap-3">
            <img src="{{ theme_asset('termosalud-favicon.png') }}" alt="{{ brand()->name }}" class="w-9 h-9 rounded-xl shadow-sm ring-1 ring-slate-900/5 object-cover">
            <span class="flex flex-col leading-none">
                <span class="termo-wordmark">
                    <img src="{{ theme_asset('termosalud-logo.png') }}" alt="">
                </span>
                <span class="mt-1.5 text-[9px] font-bold tracking-[0.2em] text-slate-400 uppercase leading-none">termo<span class="text-[#0090A8]">CMS</span></span>
            </span>
        </a>
        @if(!empty(($cmsBrand ?? brand())->edition))
            <span class="text-[10px] font-bold text-[#0090A8] border border-[#0090A8]/30 bg-[#0090A8]/10 px-2.5 py-1 rounded-full uppercase tracking-wider">
                {{ mb_strtoupper(($cmsBrand ?? brand())->edition) }}
            </span>
        @endif
    </header>

    <!-- Main Content Card -->
    <main class="w-full max-w-2xl mx-auto my-auto">
        <div class="bg-white border border-slate-200 shadow-xl rounded-2xl p-8 sm:p-12 text-center space-y-6">
            <!-- Icon & Status Code Badge -->
            <div class="flex flex-col items-center justify-center gap-3">
                @hasSection('icon')
                    <div class="w-16 h-16 rounded-2xl bg-[#0090A8]/10 text-[#0090A8] border border-[#0090A8]/20 flex items-center justify-center text-3xl shadow-inner">
                        @yield('icon')
                    </div>
                @endif
                <div class="text-6xl sm:text-7xl font-extrabold tracking-tight text-[#0090A8]">
                    @yield('code')
                </div>
            </div>

            <!-- Error Title & Description -->
            <div class="space-y-2">
                <h1 class="text-xl sm:text-2xl font-bold text-slate-900">
                    @yield('title')
                </h1>
                <p class="text-sm text-slate-600 max-w-lg mx-auto leading-relaxed">
                    @yield('message')
                </p>
            </div>

            <!-- Action Buttons -->
            <div class="pt-4 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ url('/') }}"
                   class="admin-btn admin-btn--primary admin-btn--lg">
                    <i class="fa-solid fa-house"></i>
                    <span>Ir a la Portada</span>
                </a>

                @if(auth('web')->check() && auth('web')->user()?->hasPermission('access:admin'))
                    <a href="{{ route('admin.dashboard') }}"
                       class="admin-btn admin-btn--neutral admin-btn--lg">
                        <i class="fa-solid fa-gauge-high"></i>
                        <span>Panel de Administración</span>
                    </a>
                @endif
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full max-w-2xl mx-auto pt-6 text-center text-xs admin-text-muted font-sans">
        <p>{{ brand()->name }} &middot; {{ brand()->tagline }}</p>
    </footer>
</body>
</html>
