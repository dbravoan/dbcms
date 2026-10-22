@php
    $brutalistRights = match($currentLocale) {
        'ca' => 'Tots els drets reservats.',
        'en' => 'All rights reserved.',
        default => 'Todos los derechos reservados.',
    };
@endphp

<footer class="border-t-3 border-black bg-[#FFE600] pt-16 pb-12 text-xs text-black font-mono font-bold">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
            <!-- Col 1: Brand -->
            <div class="md:col-span-2 space-y-4">
                <div class="inline-block px-3 py-1 bg-black text-white font-black text-xl border-2 border-black shadow-[4px_4px_0px_0px_#00F0FF] uppercase">
                    {{ $cmsBrandName ?? config('cms.name') }} Brutalist
                </div>
                <p class="text-black font-semibold text-xs leading-relaxed max-w-md">
                    {{ $brandDescription }}
                </p>
                <div class="flex items-center space-x-2 pt-2">
                    <a href="{{ $localization->url('/llms.txt', $currentMarket, $currentLocale) }}" class="px-3 py-1 bg-white border-2 border-black shadow-[2px_2px_0px_0px_#000] hover:bg-[#00F0FF] transition-all"><i class="fa-solid fa-file-lines mr-1"></i> llms.txt</a>
                    <a href="{{ $localization->url('/sitemap.xml', $currentMarket, $currentLocale) }}" class="px-3 py-1 bg-white border-2 border-black shadow-[2px_2px_0px_0px_#000] hover:bg-[#00F0FF] transition-all"><i class="fa-solid fa-map-location-dot mr-1"></i> sitemap.xml</a>
                </div>
            </div>

            <!-- Col 2: Navigation -->
            <div class="space-y-3">
                <div class="font-black uppercase tracking-wider text-sm pb-1 border-b-2 border-black">{{ __('Navegación') }}</div>
                <ul class="space-y-2">
                    @foreach(($footerQuick ?: $frontFallbackLinks) as $link)
                        <li><a href="{{ $link['url'] }}" class="hover:bg-white px-1.5 py-0.5 border border-transparent hover:border-black transition-all">{{ $link['title'] }}</a></li>
                    @endforeach
                </ul>
            </div>

            <!-- Col 3: Legal -->
            <div class="space-y-3">
                <div class="font-black uppercase tracking-wider text-sm pb-1 border-b-2 border-black">Legal</div>
                <ul class="space-y-2">
                    @foreach($footerLegal as $legal)
                        <li><a href="{{ $legal['url'] }}" class="hover:bg-white px-1.5 py-0.5 border border-transparent hover:border-black transition-all">{{ $legal['title'] }}</a></li>
                    @endforeach
                    <li>
                        <button type="button"
                                data-dispatch="open-cookie-settings"
                                class="hover:bg-white px-1.5 py-0.5 border border-transparent hover:border-black transition-all text-left">
                            Preferencias de Cookies
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-8 border-t-2 border-black flex flex-col sm:flex-row items-center justify-between text-xs">
            <div>&copy; {{ date('Y') }} {{ $cmsBrandName ?? config('cms.name') }} Brutalist System. {{ $brutalistRights }}</div>
            <div class="mt-2 sm:mt-0">
                <button type="button"
                        data-dispatch="open-geo-modal"
                        class="px-2.5 py-1 bg-white border-2 border-black shadow-[2px_2px_0px_0px_#000] hover:bg-[#00F0FF] transition-all uppercase font-black">
                    <span>MERCADO: {{ $marketCode }} / {{ $currentLocale }}</span>
                </button>
            </div>
        </div>
    </div>
</footer>
