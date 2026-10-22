@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'icon' => 'bars', 'toggleDesktop' => false, 'admin' => false];
    $desktopNavVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
    $navDesktopClass = ($nav['layout'] ?? 'inline') === 'stacked'
        ? 'hidden md:flex flex-col items-start gap-3'
        : 'hidden md:flex items-center gap-6';
    $navFallback = $frontFallbackLinks;
@endphp
<header class="border-b border-slate-200 bg-white/90 backdrop-blur-md sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4 sm:gap-6">
        <a href="{{ $localization->url('/', $currentMarket) }}" class="font-bold text-lg tracking-tight">
            {{ $cmsBrandName ?? config('cms.name') }}
        </a>

        @if($desktopNavVisible)
            @include('themes::components.nav-items', [
                'items' => $headerMenu,
                'fallback' => $navFallback,
                'class' => $navDesktopClass.' text-sm font-semibold',
                'linkClass' => 'text-slate-600 hover:text-slate-900 transition-colors',
            ])
        @endif

        <!-- General search (module Search) -->
        @include('themes::components.search-box', ['variant' => 'light'])

        <div class="flex items-center gap-3 text-sm">
            @if($hasMarkets || $hasLangs)
                <div x-data="{ open: false }" class="relative">
                    <button type="button" @click="open = !open" class="px-3 py-1.5 border border-slate-200 rounded-lg uppercase font-bold text-xs">
                        {{ $marketCode }} / {{ $currentLocale }}
                    </button>
                    <div x-show="open" x-cloak @click.outside="open = false" class="absolute right-0 mt-2 w-56 bg-white border border-slate-200 rounded-xl shadow-lg py-2 z-50">
                        @foreach($allMarkets as $market)
                            @foreach($market->languages as $lang)
                                @php $directLink = $alternateUrls[$market->code][$lang->code] ?? $localization->url('/', $market, $lang); @endphp
                                <a href="{{ $directLink }}" class="block px-4 py-2 text-xs hover:bg-slate-50">{{ $market->name }} · {{ $lang->name }}</a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            @endif

            @include('themes::components.nav-admin', [
                'frontNav' => $nav,
                'class' => 'hidden sm:inline-flex px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition-colors',
                'label' => 'Admin',
            ])

            @include('themes::components.nav-toggle', [
                'frontNav' => $nav,
                'class' => 'p-2 rounded-lg text-slate-600 hover:text-slate-900 transition-colors',
            ])
        </div>
    </div>

    @include('themes::components.nav-panel', [
        'frontNav' => $nav,
        'items' => $headerMenu,
        'fallback' => $navFallback,
        'class' => 'border-t border-slate-200 bg-white px-4 py-4',
        'navClass' => 'flex flex-col',
        'linkClass' => 'block py-2 text-sm font-semibold text-slate-600 hover:text-slate-900',
        'adminClass' => 'mt-2 block text-center py-2 px-3 bg-slate-900 text-white rounded-lg text-xs font-bold',
    ])
</header>