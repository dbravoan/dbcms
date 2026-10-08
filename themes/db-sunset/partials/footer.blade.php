@php
    $sunsetBrandDesc = match($currentLocale) {
        'ca' => 'Plataforma web d\'avantguarda amb estètica aurora, renderitzat ultra-ràpid i optimitzada per a la integració amb motors d\'intel·ligència artificial (GEO).',
        'en' => 'Cutting-edge web platform with aurora obsidian glassmorphism aesthetics, lightning-fast rendering, and native GEO optimization.',
        default => 'Plataforma web de vanguardia con estética aurora, renderizado ultra-rápido y optimizada para la integración con motores de inteligencia artificial (GEO).',
    };
    $sunsetRights = match($currentLocale) {
        'ca' => 'Tots els drets reservats.',
        'en' => 'All rights reserved.',
        default => 'Todos los derechos reservados.',
    };
@endphp

<footer class="border-t border-white/10 bg-[#07050e] pt-16 pb-12 text-xs text-slate-400">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
            <!-- Col 1: Brand -->
            <div class="md:col-span-2 space-y-4">
                <div class="flex items-center gap-2.5">
                    <span class="w-7 h-7 rounded-xl bg-gradient-to-tr from-rose-500 via-pink-500 to-amber-400 flex items-center justify-center text-white text-xs font-bold"><i class="fa-solid fa-sun text-rose-500"></i></span>
                    <span class="text-xl font-bold text-white">{{ $cmsBrandName ?? config('cms.name') }} Sunset</span>
                </div>
                <p class="text-slate-400 text-xs leading-relaxed max-w-md">
                    {{ $sunsetBrandDesc }}
                </p>
                <div class="flex items-center space-x-2 pt-2">
                    <a href="{{ url("/{$marketCode}/{$currentLocale}/llms.txt") }}" class="px-3 py-1 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-rose-500/40 rounded-lg text-slate-300 transition-all"><i class="fa-solid fa-file-lines mr-1"></i> llms.txt</a>
                    <a href="{{ url("/{$marketCode}/{$currentLocale}/sitemap.xml") }}" class="px-3 py-1 bg-white/5 hover:bg-white/10 border border-white/10 hover:border-rose-500/40 rounded-lg text-slate-300 transition-all"><i class="fa-solid fa-map-location-dot mr-1"></i> sitemap.xml</a>
                </div>
            </div>

            <!-- Col 2: Quick Links -->
            <div class="space-y-3">
                <div class="font-bold text-white uppercase tracking-wider text-[11px] pb-1 border-b border-white/10">{{ __('Navegación') }}</div>
                <ul class="space-y-2">
                    @forelse($footerQuick as $link)
                        <li><a href="{{ $link['url'] }}" class="hover:text-rose-400 transition-colors text-slate-400">{{ $link['title'] }}</a></li>
                    @empty
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/") }}" class="hover:text-rose-400">{{ __('Inicio') }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/blog") }}" class="hover:text-rose-400">{{ __('Blog') }}</a></li>
                    @endforelse
                </ul>
            </div>

            <!-- Col 3: Legal -->
            <div class="space-y-3">
                <div class="font-bold text-white uppercase tracking-wider text-[11px] pb-1 border-b border-white/10">Legal</div>
                <ul class="space-y-2">
                    @forelse($footerLegal as $legal)
                        <li><a href="{{ $legal['url'] }}" class="hover:text-rose-400 transition-colors text-slate-400">{{ $legal['title'] }}</a></li>
                    @empty
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/contacto") }}" class="hover:text-rose-400">{{ __('Contacto') }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/privacidad") }}" class="hover:text-rose-400">Privacidad (RGPD)</a></li>
                    @endforelse
                    <li>
                        <button type="button"
                                data-dispatch="open-cookie-settings"
                                class="hover:text-rose-400 transition-colors text-slate-400 text-left">
                            Preferencias de Cookies
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-8 border-t border-white/10 flex flex-col sm:flex-row items-center justify-between text-[11px] text-slate-500">
            <div>&copy; {{ date('Y') }} {{ $cmsBrandName ?? config('cms.name') }} Sunset Aurora. {{ $sunsetRights }}</div>
            <div class="mt-2 sm:mt-0">
                <button type="button"
                        data-dispatch="open-geo-modal"
                        class="hover:text-rose-400 transition-colors flex items-center gap-1.5">
                    <span>Mercado: <strong class="text-slate-300 uppercase">{{ $marketCode }}</strong></span>
                    <span>&bull;</span>
                    <span>Idioma: <strong class="text-rose-400 uppercase">{{ $currentLocale }}</strong></span>
                </button>
            </div>
        </div>
    </div>
</footer>
