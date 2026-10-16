@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center space-x-6';
    $navFallback = [['title' => __('Inicio'), 'url' => $localization->url('/', $currentMarket)]];
@endphp
<header class="border-b border-white/10 bg-[#0a0712]/80 backdrop-blur-xl sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4 sm:gap-6">
        <div class="flex items-center gap-8">
            <a href="{{ $localization->url('/', $currentMarket) }}" class="flex items-center gap-2.5 group">
                <span class="w-8 h-8 rounded-xl bg-gradient-to-tr from-rose-500 via-pink-500 to-amber-400 flex items-center justify-center text-white font-black text-sm shadow-lg shadow-rose-500/20 group-hover:scale-105 transition-transform"><i class="fa-solid fa-sun text-rose-500"></i></span>
                <span class="text-xl font-bold tracking-tight text-white">
                    dbCMS <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-400 to-amber-300 font-medium text-xs uppercase tracking-widest ml-1">Sunset</span>
                </span>
            </a>

            <!-- Desktop Navigation Menu (disposición configurable) -->
            @if($desktopNavVisible)
                @include('themes::components.nav-items', [
                    'items' => $headerMenu,
                    'fallback' => $navFallback,
                    'class' => $navDesktopClass.' text-xs font-medium uppercase tracking-wider text-slate-300',
                    'linkClass' => 'hover:text-rose-400 transition-colors py-2',
                ])
            @endif
        </div>

        <!-- Header Right: Market/Locale Switcher & Cart & Admin -->
        <div class="flex items-center space-x-3 text-xs">
            @if($hasMarkets)
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-rose-500/40 rounded-xl text-slate-200 transition-all">
                        <span>{!! $flags[$marketCode] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                        <span class="font-bold text-rose-400 uppercase">{{ $marketCode }}</span>
                        <span class="text-slate-500">/</span>
                        <span class="uppercase text-slate-300">{{ $currentLocale }}</span>
                        <span class="text-[10px] text-slate-400">▼</span>
                    </button>

                    <div x-show="open"
                         @click.outside="open = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-2 w-64 bg-[#120c22] border border-white/15 rounded-2xl shadow-2xl py-2 z-50 divide-y divide-white/10 backdrop-blur-2xl">
                        @foreach($allMarkets as $market)
                            <div class="p-2">
                                <div class="text-[10px] text-slate-400 uppercase font-bold px-2 py-1 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span>{!! $flags[$market->code] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                                        <span>{{ $market->name }}</span>
                                    </span>
                                    <span class="text-amber-400 font-mono">[{{ strtoupper($market->code) }}]</span>
                                </div>
                                <div class="space-y-0.5 mt-1">
                                    @foreach($market->languages as $lang)
                                        @php
                                            $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang);
                                        @endphp
                                        <a href="{{ $directLink }}"
                                           class="flex items-center justify-between px-2 py-1.5 rounded-lg text-xs {{ $market->code === $marketCode && $lang->code === $currentLocale ? 'bg-gradient-to-r from-rose-500/20 to-amber-500/20 text-rose-300 font-bold border border-rose-500/30' : 'text-slate-300 hover:bg-white/5 hover:text-white' }}">
                                            <span>{{ $lang->flag }} {{ $lang->name }} ({{ $lang->native_name }})</span>
                                            <span class="text-[10px] text-slate-500 uppercase">{{ $lang->code }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Cart Indicator -->
            @if(($isShopEnabled ?? false))
                <a href="{{ $localization->url('/cart', $currentMarket) }}"
                   class="relative p-2 rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 text-slate-300 hover:text-white transition-all flex items-center"
                   title="{{ __('Carrito') }}">
                    <span class="text-sm"><i class="fa-solid fa-bag-shopping"></i></span>
                    @if($cartCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 bg-gradient-to-r from-rose-500 to-amber-500 text-white font-bold text-[9px] rounded-full w-4 h-4 flex items-center justify-center shadow-lg shadow-rose-500/30">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>
            @endif

            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'hidden sm:inline-block px-3.5 py-1.5 bg-gradient-to-r from-rose-500 to-amber-500 hover:from-rose-600 hover:to-amber-600 text-white rounded-xl font-semibold transition-all shadow-md shadow-rose-500/20',
                'label' => 'Admin',
            ])

            <!-- Mobile menu toggle -->
            @include('themes::components.nav-toggle', [
                'frontNav' => $nav,
                'class' => 'p-2 text-slate-400 hover:text-white',
            ])
        </div>
    </div>

    @include('themes::components.nav-panel', [
        'frontNav' => $nav,
        'items' => $headerMenu,
        'fallback' => $navFallback,
        'class' => 'border-t border-white/10 bg-[#0e0a1a] px-4 py-4 space-y-2',
        'navClass' => 'flex flex-col space-y-2',
        'linkClass' => 'block py-2 px-3 text-sm font-medium text-slate-300 hover:bg-white/5 rounded-xl',
        'adminClass' => 'block text-center py-2 px-3 bg-gradient-to-r from-rose-500 to-amber-500 text-white rounded-xl text-xs font-bold',
    ])
</header>
