@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center space-x-6';
    $navFallback = [['title' => __('Inicio'), 'url' => $localization->url('/', $currentMarket)]];
@endphp
<header class="border-b-3 border-black bg-[#FFFDF8] sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between gap-4 sm:gap-6">
        <div class="flex items-center gap-8">
            <a href="{{ $localization->url('/', $currentMarket) }}" class="flex items-center gap-2 font-black text-2xl tracking-tighter uppercase text-black">
                <span class="px-2.5 py-1 bg-[#FFE600] border-2 border-black shadow-[3px_3px_0px_0px_#000]">
                    db
                </span>
                <span>dbCMS <span class="bg-[#00F0FF] px-1 text-sm border border-black shadow-[2px_2px_0px_0px_#000]">Brutalist</span></span>
            </a>

            <!-- Desktop Navigation Menu (disposición configurable) -->
            @if($desktopNavVisible)
                @include('themes::components.nav-items', [
                    'items' => $headerMenu,
                    'fallback' => $navFallback,
                    'class' => $navDesktopClass.' text-xs font-black uppercase tracking-wider text-black',
                    'linkClass' => 'hover:bg-[#FFE600] px-2 py-1 border-2 border-transparent hover:border-black hover:shadow-[2px_2px_0px_0px_#000] transition-all',
                ])
            @endif
        </div>

        <!-- Header Right: Market Switcher & Cart & Admin -->
        <div class="flex items-center space-x-3 text-xs font-black">
            @if($hasMarkets)
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-white border-2 border-black shadow-[3px_3px_0px_0px_#000] hover:bg-[#FFE600] transition-all uppercase">
                        <span>{!! $flags[$marketCode] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                        <span>{{ $marketCode }}:{{ $currentLocale }}</span>
                        <span>▼</span>
                    </button>

                    <div x-show="open"
                         @click.outside="open = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-2 w-60 bg-white border-3 border-black shadow-[5px_5px_0px_0px_#000] p-2 z-50 divide-y-2 divide-black">
                        @foreach($allMarkets as $market)
                            <div class="py-2">
                                <div class="text-[10px] uppercase font-black px-2 py-0.5 bg-[#FFE600] border border-black flex items-center justify-between mb-1.5">
                                    <span>{{ $market->name }}</span>
                                    <span>[{{ strtoupper($market->code) }}]</span>
                                </div>
                                <div class="space-y-1">
                                    @foreach($market->languages as $lang)
                                        @php
                                            $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang);
                                        @endphp
                                        <a href="{{ $directLink }}"
                                           class="flex items-center justify-between px-2 py-1 text-xs border border-transparent hover:border-black hover:bg-[#00F0FF] {{ $market->code === $marketCode && $lang->code === $currentLocale ? 'bg-black text-white font-bold' : 'text-black' }}">
                                            <span>{{ $lang->flag }} {{ $lang->name }}</span>
                                            <span class="text-[10px] uppercase">{{ $lang->code }}</span>
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
                   class="relative p-2 bg-[#00F0FF] border-2 border-black shadow-[3px_3px_0px_0px_#000] hover:bg-[#FFE600] transition-all flex items-center"
                   title="{{ __('Carrito') }}">
                    <span class="text-sm"><i class="fa-solid fa-cart-shopping"></i></span>
                    @if($cartCount > 0)
                        <span class="absolute -top-2 -right-2 bg-[#FF5A5F] text-white font-black text-[10px] border border-black w-5 h-5 flex items-center justify-center">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>
            @endif

            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'hidden sm:inline-block px-3 py-1.5 bg-black text-white border-2 border-black shadow-[3px_3px_0px_0px_#FFE600] hover:bg-[#FFE600] hover:text-black transition-all uppercase',
                'label' => 'Panel',
            ])

            <!-- Collapsible menu toggle -->
            @include('themes::components.nav-toggle', [
                'frontNav' => $nav,
                'class' => 'p-1.5 bg-white border-2 border-black shadow-[2px_2px_0px_0px_#000]',
            ])
        </div>
    </div>

    @include('themes::components.nav-panel', [
        'frontNav' => $nav,
        'items' => $headerMenu,
        'fallback' => $navFallback,
        'class' => 'border-t-3 border-black bg-[#FFFDF8] px-4 py-4 font-black uppercase text-xs',
        'navClass' => 'flex flex-col space-y-2',
        'linkClass' => 'block py-2 px-3 border-2 border-black bg-white shadow-[2px_2px_0px_0px_#000]',
        'adminClass' => 'mt-2 block text-center py-2 px-3 bg-black text-white border-2 border-black font-black',
    ])
</header>
