@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center space-x-6';
    $navFallback = $frontFallbackLinks;
@endphp
<header class="border-b border-slate-800 bg-slate-950/90 backdrop-blur sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4 sm:gap-6">
        <div class="flex items-center gap-8">
            <a href="{{ $localization->url('/', $currentMarket) }}" class="flex items-center gap-2 text-xl font-bold tracking-tight text-white">
                <span class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-black text-sm">db</span>
                <span>dbCMS <span class="text-indigo-400 font-normal text-xs uppercase tracking-widest ml-1">Enterprise</span></span>
            </a>

            <!-- Desktop Navigation Menu (disposición configurable) -->
            @if($desktopNavVisible)
                @include('themes::components.nav-items', [
                    'items' => $headerMenu,
                    'fallback' => $navFallback,
                    'class' => $navDesktopClass.' text-xs font-semibold uppercase tracking-wider text-slate-300',
                    'linkClass' => 'hover:text-indigo-400 transition-colors py-2',
                ])
            @endif
        </div>

        <!-- Header Right: Market/Locale Switcher & Cart & Admin Button -->
        <div class="flex items-center space-x-3 text-xs">
            @if($hasMarkets)
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-900 hover:bg-slate-800 border border-slate-700 hover:border-indigo-500 rounded-lg text-slate-300 hover:text-white transition-all">
                        <span>{!! $flags[$marketCode] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                        <span class="font-bold text-indigo-400 uppercase">{{ $marketCode }}</span>
                        <span class="text-slate-600">/</span>
                        <span class="uppercase text-slate-300">{{ $currentLocale }}</span>
                        <span class="text-[10px] text-slate-500">▼</span>
                    </button>

                    <div x-show="open"
                         @click.outside="open = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-2 w-64 bg-slate-900 border border-slate-800 rounded-xl shadow-2xl py-2 z-50 divide-y divide-slate-800">
                        @foreach($allMarkets as $market)
                            <div class="p-2">
                                <div class="text-[10px] text-slate-400 uppercase font-bold px-2 py-1 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span>{!! $flags[$market->code] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                                        <span>{{ $market->name }}</span>
                                    </span>
                                    <span class="text-indigo-400 font-mono">[{{ strtoupper($market->code) }}]</span>
                                </div>
                                <div class="space-y-0.5 mt-1">
                                    @foreach($market->languages as $lang)
                                        @php
                                            $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang);
                                        @endphp
                                        <a href="{{ $directLink }}"
                                           class="flex items-center justify-between px-2 py-1.5 rounded text-xs {{ $market->code === $marketCode && $lang->code === $currentLocale ? 'bg-indigo-600/20 text-indigo-300 font-bold border border-indigo-500/30' : 'text-slate-300 hover:bg-slate-800 hover:text-white' }}">
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

            <!-- Cart Indicator (when Shop Mode is enabled) -->
            @if(($isShopEnabled ?? false))
                <a href="{{ $localization->url('/cart', $currentMarket) }}"
                   class="relative p-2 rounded-lg bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white transition-all flex items-center"
                   title="{{ __('Carrito de compras') }}">
                    <span class="text-sm"><i class="fa-solid fa-cart-shopping"></i></span>
                    @if($cartCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 bg-indigo-600 text-white font-bold text-[9px] rounded-full w-4 h-4 flex items-center justify-center shadow">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>
            @endif

            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'hidden sm:inline-block px-3 py-1.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded font-semibold transition-colors shadow-sm',
                'label' => 'Admin Portal',
            ])

            <!-- Mobile Menu Button -->
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
        'class' => 'border-t border-slate-800 bg-slate-900 px-4 py-4 space-y-2',
        'navClass' => 'flex flex-col space-y-2',
        'linkClass' => 'block py-2 px-3 text-sm font-semibold text-slate-300 hover:bg-slate-800 rounded-lg',
        'adminClass' => 'block text-center py-2 px-3 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-bold',
    ])
</header>
