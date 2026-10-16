@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center space-x-8';
    $navFallback = [['title' => __('Inicio'), 'url' => $localization->url('/', $currentMarket)]];
@endphp
<header class="border-b border-neutral-200/80 bg-white/95 backdrop-blur sticky top-0 z-40">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 h-20 flex items-center justify-between gap-4 sm:gap-6">
        <div class="flex items-center gap-10">
            <a href="{{ $localization->url('/', $currentMarket) }}" class="text-lg font-mono font-medium tracking-widest uppercase text-neutral-900">
                db.minimal
            </a>

            <!-- Desktop Navigation Menu (disposición configurable) -->
            @if($desktopNavVisible)
                @include('themes::components.nav-items', [
                    'items' => $headerMenu,
                    'fallback' => $navFallback,
                    'class' => $navDesktopClass.' text-xs uppercase tracking-widest text-neutral-500 font-medium',
                    'linkClass' => 'hover:text-neutral-950 transition-colors py-2',
                ])
            @endif

        <!-- Header Right: Market Switcher & Cart & Admin -->
        <div class="flex items-center space-x-4 text-xs font-mono">
            @if($hasMarkets)
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-1.5 px-2.5 py-1 text-neutral-600 hover:text-neutral-950 border border-neutral-200 rounded transition-colors text-[11px] uppercase tracking-wider">
                        <span>{!! $flags[$marketCode] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                        <span>{{ $marketCode }}:{{ $currentLocale }}</span>
                    </button>

                    <div x-show="open"
                         @click.outside="open = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-2 w-56 bg-white border border-neutral-200 rounded-lg shadow-lg py-2 z-50 divide-y divide-neutral-100 font-sans">
                        @foreach($allMarkets as $market)
                            <div class="p-2">
                                <div class="text-[10px] text-neutral-400 uppercase font-semibold px-2 py-1 flex items-center justify-between">
                                    <span>{{ $market->name }}</span>
                                    <span class="font-mono text-[9px]">{{ strtoupper($market->code) }}</span>
                                </div>
                                <div class="space-y-0.5 mt-0.5">
                                    @foreach($market->languages as $lang)
                                        @php
                                            $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang);
                                        @endphp
                                        <a href="{{ $directLink }}"
                                           class="flex items-center justify-between px-2 py-1.5 rounded text-xs {{ $market->code === $marketCode && $lang->code === $currentLocale ? 'bg-neutral-100 text-neutral-900 font-semibold' : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900' }}">
                                            <span>{{ $lang->flag }} {{ $lang->name }}</span>
                                            <span class="text-[10px] text-neutral-400 uppercase font-mono">{{ $lang->code }}</span>
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
                   class="relative p-2 text-neutral-600 hover:text-neutral-950 transition-colors flex items-center"
                   title="Cesta">
                    <span class="text-sm">◻</span>
                    @if($cartCount > 0)
                        <span class="absolute -top-1 -right-1 bg-neutral-900 text-white font-mono text-[9px] w-4 h-4 rounded-full flex items-center justify-center">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>
            @endif

            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'hidden sm:inline-block px-3 py-1 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-[11px] uppercase tracking-wider transition-colors',
                'label' => 'Admin',
            ])

            <!-- Collapsible menu toggle -->
            @include('themes::components.nav-toggle', [
                'frontNav' => $nav,
                'class' => 'p-1.5 text-neutral-600 hover:text-neutral-900',
            ])
        </div>
    </div>

    @include('themes::components.nav-panel', [
        'frontNav' => $nav,
        'items' => $headerMenu,
        'fallback' => $navFallback,
        'class' => 'border-t border-neutral-200 bg-white px-4 py-4 text-xs uppercase tracking-wider',
        'navClass' => 'flex flex-col space-y-2',
        'linkClass' => 'block py-2 px-3 text-neutral-600 hover:text-neutral-900 hover:bg-neutral-50 rounded',
        'adminClass' => 'mt-2 block text-center py-2 px-3 bg-neutral-900 text-white rounded text-xs font-mono',
    ])
</header>
