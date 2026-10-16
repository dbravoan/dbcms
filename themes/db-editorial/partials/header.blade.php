@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center space-x-6';
    $navFallback = [['title' => __('Inicio'), 'url' => $localization->url('/', $currentMarket)]];
@endphp
<header class="border-b border-stone-200 bg-white/95 backdrop-blur sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between gap-4 sm:gap-6">
        <div class="flex items-center gap-8">
            <a href="{{ $localization->url('/', $currentMarket) }}" class="text-2xl font-serif font-bold tracking-tight text-stone-900">
                dbCMS <span class="text-sm font-sans font-normal text-stone-500 uppercase tracking-widest">Editorial</span>
            </a>

            <!-- Desktop Navigation Menu (disposición configurable) -->
            @if($desktopNavVisible)
                @include('themes::components.nav-items', [
                    'items' => $headerMenu,
                    'fallback' => $navFallback,
                    'class' => $navDesktopClass.' text-sm font-medium text-stone-700',
                    'linkClass' => 'hover:text-stone-950 hover:underline transition-colors py-2',
                ])
            @endif
        </div>

        <!-- Header Right: Market/Locale Switcher & Cart & Admin -->
        <div class="flex items-center space-x-3 text-xs">
            @if($hasMarkets)
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-stone-100 hover:bg-stone-200 border border-stone-300 rounded-lg text-stone-700 hover:text-stone-900 transition-all font-medium">
                        <span>{!! $flags[$marketCode] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                        <span class="font-bold uppercase">{{ $marketCode }}</span>
                        <span class="text-stone-400">/</span>
                        <span class="uppercase text-stone-600">{{ $currentLocale }}</span>
                        <span class="text-[10px] text-stone-500">▼</span>
                    </button>

                    <div x-show="open"
                         @click.outside="open = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-2 w-60 bg-white border border-stone-200 rounded-xl shadow-lg py-2 z-50 divide-y divide-stone-100">
                        @foreach($allMarkets as $market)
                            <div class="p-2">
                                <div class="text-[10px] text-stone-400 uppercase font-bold px-2 py-1 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span>{!! $flags[$market->code] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                                        <span>{{ $market->name }}</span>
                                    </span>
                                    <span class="font-mono text-stone-500">[{{ strtoupper($market->code) }}]</span>
                                </div>
                                <div class="space-y-0.5 mt-1">
                                    @foreach($market->languages as $lang)
                                        @php
                                            $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang);
                                        @endphp
                                        <a href="{{ $directLink }}"
                                           class="flex items-center justify-between px-2 py-1.5 rounded text-xs {{ $market->code === $marketCode && $lang->code === $currentLocale ? 'bg-stone-100 text-stone-900 font-bold' : 'text-stone-600 hover:bg-stone-50 hover:text-stone-900' }}">
                                            <span>{{ $lang->flag }} {{ $lang->name }} ({{ $lang->native_name }})</span>
                                            <span class="text-[10px] text-stone-400 uppercase">{{ $lang->code }}</span>
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
                   class="relative p-2 rounded-lg bg-stone-100 hover:bg-stone-200 border border-stone-300 text-stone-700 transition-all flex items-center"
                   title="{{ __('Carrito') }}">
                    <span class="text-sm"><i class="fa-solid fa-bag-shopping"></i></span>
                    @if($cartCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 bg-stone-900 text-white font-bold text-[9px] rounded-full w-4 h-4 flex items-center justify-center">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>
            @endif

            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'hidden sm:inline-block px-3 py-1.5 bg-stone-900 hover:bg-stone-800 text-white rounded font-medium transition-colors',
                'label' => 'Panel Admin',
            ])

            <!-- Mobile menu toggle -->
            @include('themes::components.nav-toggle', [
                'frontNav' => $nav,
                'class' => 'p-2 text-stone-600 hover:text-stone-900',
            ])
        </div>
    </div>

    @include('themes::components.nav-panel', [
        'frontNav' => $nav,
        'items' => $headerMenu,
        'fallback' => $navFallback,
        'class' => 'border-t border-stone-200 bg-white px-4 py-4 space-y-2',
        'navClass' => 'flex flex-col space-y-2',
        'linkClass' => 'block py-2 px-3 text-sm font-medium text-stone-700 hover:bg-stone-100 rounded-lg',
        'adminClass' => 'block text-center py-2 px-3 bg-stone-900 text-white rounded-lg text-xs font-bold',
    ])
</header>
