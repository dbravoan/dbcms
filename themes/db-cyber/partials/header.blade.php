@php
    // Front navigation options configured in the backoffice (Settings → Navegación).
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center space-x-6';
    $navFallback = $frontFallbackLinks;
@endphp
<header class="border-b border-gray-800 bg-[#121212]/90 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-20 flex items-center justify-between gap-4 sm:gap-6">

        <!-- Logo with >_db animated cursor -->
        <a href="{{ $localization->url('/', $currentMarket) }}" class="flex items-center gap-1 text-2xl sm:text-3xl font-bold tracking-tight text-white select-none">
            <span>&gt;</span><span class="cursor-blink">_</span><span>dbCMS</span>
        </a>

        <!-- Dynamic Menu Items (disposición configurable en el backoffice) -->
        @if($desktopNavVisible)
            @include('themes::components.nav-items', [
                'items' => $headerMenu,
                'fallback' => $navFallback,
                'class' => $navDesktopClass.' text-xs font-mono font-bold tracking-wider uppercase',
                'linkClass' => 'text-gray-300 hover:text-[#00FF99] transition-colors py-2',
            ])
        @endif

        <!-- General search (module Search) -->
        @include('themes::components.search-box', ['variant' => 'dark'])

        <!-- Right Side: Market/Language Switcher & Admin Button -->
        <div class="flex items-center space-x-4 font-mono text-xs">

            <!-- Case 1: Multiple Markets -> Full Dropdown -->
            @if($hasMarkets)
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] rounded-lg text-gray-300 hover:text-white transition-all shadow-sm">
                        <span>{!! $flags[$marketCode] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                        <span class="font-bold text-[#00FF99] uppercase">{{ $marketCode }}</span>
                        <span class="text-gray-500">/</span>
                        <span class="uppercase text-gray-300">{{ $currentLocale }}</span>
                        <span class="text-[10px] text-gray-500">▼</span>
                    </button>

                    <div x-show="open"
                         @click.outside="open = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-2 w-64 bg-[#141414] border border-gray-800 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.9)] py-2 z-50 divide-y divide-gray-800/60">
                        @foreach($allMarkets as $market)
                            <div class="p-2">
                                <div class="text-[10px] text-gray-400 uppercase font-bold px-2 py-1 flex items-center justify-between">
                                    <span class="flex items-center gap-1.5">
                                        <span>{!! $flags[$market->code] ?? '<i class="fa-solid fa-globe"></i>' !!}</span>
                                        <span>{{ $market->name }}</span>
                                    </span>
                                    <span class="text-[#00FF99] font-mono">[{{ strtoupper($market->code) }}]</span>
                                </div>
                                <div class="space-y-0.5 mt-1">
                                    @foreach($market->languages as $lang)
                                        @php
                                            $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang);
                                        @endphp
                                        <a href="{{ $directLink }}"
                                           class="flex items-center justify-between px-2 py-1.5 rounded text-xs {{ $market->code === $marketCode && $lang->code === $currentLocale ? 'bg-[#00FF99]/10 text-[#00FF99] font-bold border border-[#00FF99]/30' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                                            <span>{{ $lang->flag }} {{ $lang->name }} ({{ $lang->native_name }})</span>
                                            <span class="text-[10px] text-gray-500 uppercase">{{ $lang->code }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

            <!-- Case 2: 1 Market & Multiple Languages -> Language Switcher Only -->
            @elseif($hasLangs)
                <div x-data="{ open: false }" class="relative">
                    <button type="button"
                            @click="open = !open"
                            class="flex items-center gap-1.5 px-3 py-1.5 bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] rounded-lg text-gray-300 hover:text-white transition-all shadow-sm">
                        <span><i class="fa-solid fa-globe"></i></span>
                        <span class="font-bold text-[#00FF99] uppercase">{{ $currentLocale }}</span>
                        <span class="text-[10px] text-gray-500">▼</span>
                    </button>

                    <div x-show="open"
                         @click.outside="open = false"
                         x-transition
                         x-cloak
                         class="absolute right-0 mt-2 w-44 bg-[#141414] border border-gray-800 rounded-xl shadow-[0_10px_30px_rgba(0,0,0,0.9)] py-1.5 z-50">
                        @foreach($currentMarket->languages as $lang)
                            @php
                                $directLink = $alternateUrls[$marketCode][$lang->code] ?? $localization->url('/', $currentMarket, $lang);
                            @endphp
                            <a href="{{ $directLink }}"
                               class="flex items-center justify-between px-3 py-2 text-xs {{ $lang->code === $currentLocale ? 'bg-[#00FF99]/10 text-[#00FF99] font-bold' : 'text-gray-300 hover:bg-gray-800 hover:text-white' }}">
                                <span>{{ $lang->flag }} {{ $lang->name }}</span>
                                <span class="text-[10px] text-gray-500 uppercase">{{ $lang->code }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Cart Indicator (when Shop Mode is enabled) -->
            @if(($isShopEnabled ?? false))
                <a href="{{ $localization->url('/cart', $currentMarket) }}"
                   class="relative p-2 rounded-lg bg-[#1A1A1A] hover:bg-gray-800 border border-gray-700 hover:border-[#00FF99] text-gray-300 hover:text-white transition-all shadow-sm flex items-center"
                   title="{{ __('Carrito de Compras') }}">
                    <span class="text-sm"><i class="fa-solid fa-cart-shopping"></i></span>
                    @if($cartCount > 0)
                        <span class="absolute -top-1.5 -right-1.5 bg-[#00FF99] text-black font-bold text-[9px] rounded-full w-4 h-4 flex items-center justify-center">
                            {{ $cartCount }}
                        </span>
                    @endif
                </a>
            @endif

            <!-- Backoffice Access CTA (configurable; oculto por defecto) -->
            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'btn-neon text-xs py-1.5 px-3 hidden sm:inline-block',
                'label' => '<span>&gt;_admin</span>',
            ])

            <!-- Collapsible menu toggle -->
            @include('themes::components.nav-toggle', [
                'frontNav' => $nav,
                'class' => 'p-2 rounded-lg bg-[#1A1A1A] border border-gray-700 text-gray-300 hover:text-[#00FF99] transition-colors',
                'activeClass' => 'text-[#00FF99] border-[#00FF99]/50',
            ])
        </div>
    </div>

    @include('themes::components.nav-panel', [
        'frontNav' => $nav,
        'items' => $headerMenu,
        'fallback' => $navFallback,
        'class' => 'border-t border-gray-800 bg-[#121212] px-4 py-4 font-mono text-xs uppercase tracking-wider',
        'navClass' => 'flex flex-col',
        'linkClass' => 'block py-2 text-gray-300 hover:text-[#00FF99] transition-colors',
        'adminClass' => 'mt-2 block text-center btn-neon text-xs py-2 px-3',
    ])
</header>
