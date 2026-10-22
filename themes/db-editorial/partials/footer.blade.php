@php
    $editorialNavTitle = match($currentLocale) {
        'ca' => 'Navegació',
        'en' => 'Navigation',
        default => 'Navegación',
    };
    $editorialLegalTitle = match($currentLocale) {
        'ca' => 'Legal & Suport',
        'en' => 'Legal & Support',
        default => 'Legal & Soporte',
    };
    $editorialCookieTitle = match($currentLocale) {
        'ca' => 'Preferències de Galetes',
        'en' => 'Cookie Preferences',
        default => 'Preferencias de Cookies',
    };
    $editorialRights = match($currentLocale) {
        'ca' => 'Tots els drets reservats.',
        'en' => 'All rights reserved.',
        default => 'Todos los derechos reservados.',
    };
    $editorialMarketLabel = match($currentLocale) {
        'ca' => 'Mercat:',
        'en' => 'Market:',
        default => 'Mercado:',
    };
    $editorialLangLabel = match($currentLocale) {
        'ca' => 'Idioma:',
        'en' => 'Language:',
        default => 'Idioma:',
    };
@endphp

<footer class="border-t border-stone-200 bg-white pt-12 pb-8 text-xs text-stone-600">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
            <!-- Col 1: Brand -->
            <div class="md:col-span-2 space-y-3">
                <div class="text-xl font-serif font-bold text-stone-900">{{ $cmsBrandName ?? config('cms.name') }} Editorial</div>
                <p class="text-stone-500 leading-relaxed max-w-md text-xs">
                    {{ $brandDescription }}
                </p>
                <div class="flex items-center space-x-2 pt-2">
                    <a href="{{ $localization->url('/llms.txt', $currentMarket, $currentLocale) }}" class="px-2 py-1 bg-stone-100 hover:bg-stone-200 rounded border border-stone-200 text-stone-700"><i class="fa-solid fa-file-lines mr-1"></i> llms.txt</a>
                    <a href="{{ $localization->url('/sitemap.xml', $currentMarket, $currentLocale) }}" class="px-2 py-1 bg-stone-100 hover:bg-stone-200 rounded border border-stone-200 text-stone-700"><i class="fa-solid fa-map-location-dot mr-1"></i> sitemap.xml</a>
                </div>
            </div>

            <!-- Col 2: Quick Links -->
            <div class="space-y-2">
                <div class="font-bold text-stone-900 uppercase tracking-wider text-[11px]">{{ $editorialNavTitle }}</div>
                <ul class="space-y-1.5">
                    @foreach(($footerQuick ?: $frontFallbackLinks) as $link)
                        <li><a href="{{ $link['url'] }}" class="hover:underline text-stone-600 hover:text-stone-900">{{ $link['title'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <!-- Col 3: Legal Links -->
            <div class="space-y-2">
                <div class="font-bold text-stone-900 uppercase tracking-wider text-[11px]">{{ $editorialLegalTitle }}</div>
                <ul class="space-y-1.5">
                    @foreach($footerLegal as $legal)
                        <li><a href="{{ $legal['url'] }}" class="hover:underline text-stone-600 hover:text-stone-900">{{ $legal['title'] }}</a></li>
                    @endforeach
                    <li>
                        <button type="button"
                                data-dispatch="open-cookie-settings"
                                class="hover:underline text-stone-600 hover:text-stone-900 text-left">
                            {{ $editorialCookieTitle }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-6 border-t border-stone-200 flex flex-col sm:flex-row items-center justify-between text-stone-400 text-[11px]">
            <div>&copy; {{ date('Y') }} {{ $cmsBrandName ?? config('cms.name') }} Editorial Platform. {{ $editorialRights }}</div>
            <div class="mt-2 sm:mt-0 flex items-center space-x-2">
                <button type="button"
                        data-dispatch="open-geo-modal"
                        class="hover:text-stone-700 transition-colors flex items-center gap-1.5">
                    <span>{{ $editorialMarketLabel }} <strong class="text-stone-700 uppercase">{{ $marketCode }}</strong></span>
                    <span>&bull;</span>
                    <span>{{ $editorialLangLabel }} <strong class="text-stone-700 uppercase">{{ $currentLocale }}</strong></span>
                </button>
            </div>
        </div>
    </div>
</footer>
