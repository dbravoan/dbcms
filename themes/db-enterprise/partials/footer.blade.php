@php
    $enterpriseQuickTitle = match($currentLocale) {
        'ca' => 'Enllaços Ràpids',
        'en' => 'Quick Links',
        default => 'Enlaces Rápidos',
    };
    $enterpriseLegalTitle = match($currentLocale) {
        'ca' => 'Legal & Compliment',
        'en' => 'Legal & Compliance',
        default => 'Legal & Cumplimiento',
    };
    $enterpriseCookieTitle = match($currentLocale) {
        'ca' => 'Configuració de Galetes',
        'en' => 'Cookie Settings',
        default => 'Configuración de Cookies',
    };
    $enterpriseRights = match($currentLocale) {
        'ca' => 'Tots els drets reservats.',
        'en' => 'All rights reserved.',
        default => 'Todos los derechos reservados.',
    };
    $enterpriseMarketLabel = match($currentLocale) {
        'ca' => 'Mercat:',
        'en' => 'Market:',
        default => 'Mercado:',
    };
    $enterpriseLangLabel = match($currentLocale) {
        'ca' => 'Idioma:',
        'en' => 'Language:',
        default => 'Idioma:',
    };
@endphp

<footer class="border-t border-slate-800 bg-slate-950 pt-16 pb-12 text-xs text-slate-400">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-10">
            <!-- Col 1: Brand -->
            <div class="md:col-span-2 space-y-3">
                <div class="flex items-center gap-2 text-xl font-bold text-white">
                    <span class="w-6 h-6 rounded bg-indigo-600 flex items-center justify-center text-white text-xs font-black">db</span>
                    <span>{{ $cmsBrandName ?? config('cms.name') }} Enterprise</span>
                </div>
                <p class="text-slate-400 leading-relaxed max-w-md text-xs">
                    {{ $brandDescription }}
                </p>
                <div class="flex items-center space-x-2 pt-2">
                    <a href="{{ $localization->url('/llms.txt', $currentMarket, $currentLocale) }}" class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-indigo-500 rounded text-slate-300 transition-colors"><i class="fa-solid fa-file-lines mr-1"></i> llms.txt</a>
                    <a href="{{ $localization->url('/sitemap.xml', $currentMarket, $currentLocale) }}" class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-800 hover:border-indigo-500 rounded text-slate-300 transition-colors"><i class="fa-solid fa-map-location-dot mr-1"></i> sitemap.xml</a>
                </div>
            </div>

            <!-- Col 2: Quick Links -->
            <div class="space-y-2">
                <div class="font-bold text-white uppercase tracking-wider text-[11px] pb-1 border-b border-slate-800">{{ $enterpriseQuickTitle }}</div>
                <ul class="space-y-2">
                    @foreach(($footerQuick ?: $frontFallbackLinks) as $link)
                        <li><a href="{{ $link['url'] }}" class="hover:text-indigo-400 transition-colors text-slate-400">{{ $link['title'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <!-- Col 3: Legal -->
            <div class="space-y-2">
                <div class="font-bold text-white uppercase tracking-wider text-[11px] pb-1 border-b border-slate-800">{{ $enterpriseLegalTitle }}</div>
                <ul class="space-y-2">
                    @foreach($footerLegal as $legal)
                        <li><a href="{{ $legal['url'] }}" class="hover:text-indigo-400 transition-colors text-slate-400">{{ $legal['title'] }}</a></li>
                    @endforeach
                    <li>
                        <button type="button"
                                data-dispatch="open-cookie-settings"
                                class="hover:text-indigo-400 transition-colors text-slate-400 text-left">
                            {{ $enterpriseCookieTitle }}
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-8 border-t border-slate-800 flex flex-col sm:flex-row items-center justify-between text-slate-500 text-[11px]">
            <div>&copy; {{ date('Y') }} {{ $cmsBrandName ?? config('cms.name') }} Enterprise SaaS Solution. {{ $enterpriseRights }}</div>
            <div class="mt-2 sm:mt-0 flex items-center space-x-2">
                <button type="button"
                        data-dispatch="open-geo-modal"
                        class="hover:text-white transition-colors flex items-center gap-1.5">
                    <span>{{ $enterpriseMarketLabel }} <strong class="text-slate-300 uppercase">{{ $marketCode }}</strong></span>
                    <span>&bull;</span>
                    <span>{{ $enterpriseLangLabel }} <strong class="text-indigo-400 uppercase">{{ $currentLocale }}</strong></span>
                </button>
            </div>
        </div>
    </div>
</footer>
