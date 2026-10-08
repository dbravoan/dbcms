<footer class="border-t border-gray-800 bg-[#0F0F0F] pt-16 pb-12 font-mono text-xs text-gray-400">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-10">

            <!-- Col 1: Brand Info -->
            <div class="md:col-span-2 space-y-4">
                <div class="text-3xl font-bold text-white flex items-center">
                    <span>&gt;</span><span class="cursor-blink">_</span><span>{{ setting('general.site_name', 'dbCMS') }}</span>
                </div>
                <p class="text-gray-400 text-xs leading-relaxed max-w-md">
                    {{ $brandDescription }}
                </p>
                <div class="flex items-center space-x-3 text-xs pt-2">
                    <a href="{{ url("/{$marketCode}/{$currentLocale}/llms.txt") }}" class="px-2.5 py-1 bg-[#1A1A1A] border border-gray-800 hover:border-[#00FF99] rounded text-gray-300 hover:text-[#00FF99] transition-colors">
                        <i class="fa-solid fa-file-lines mr-1"></i> llms.txt
                    </a>
                    <a href="{{ url("/{$marketCode}/{$currentLocale}/sitemap.xml") }}" class="px-2.5 py-1 bg-[#1A1A1A] border border-gray-800 hover:border-[#00FF99] rounded text-gray-300 hover:text-[#00FF99] transition-colors">
                        <i class="fa-solid fa-map-location-dot mr-1"></i> sitemap.xml
                    </a>
                    @unless(app()->isProduction())
                    <a href="http://localhost:8088" target="_blank" class="px-2.5 py-1 bg-[#1A1A1A] border border-gray-800 hover:border-[#00FF99] rounded text-gray-300 hover:text-[#00FF99] transition-colors">
                        <i class="fa-solid fa-database mr-1"></i> Adminer DB
                    </a>
                    @endunless
                </div>
            </div>

            <!-- Col 2: Quick Links -->
            <div class="space-y-3">
                <div class="font-bold text-white uppercase tracking-wider text-xs border-b border-gray-800 pb-2">
                    {{ $quickNavTitle }}
                </div>
                <ul class="space-y-2">
                    @forelse($footerQuick as $link)
                        <li>
                            <a href="{{ $link['url'] }}" class="hover:text-[#00FF99] transition-colors">
                                &gt; {{ $link['title'] }}
                            </a>
                        </li>
                    @empty
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/") }}" class="hover:text-[#00FF99]">&gt; {{ $homeTitle }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/blog") }}" class="hover:text-[#00FF99]">&gt; {{ $blogTitle }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/sobre-nosotros") }}" class="hover:text-[#00FF99]">&gt; {{ $aboutLabel ?? $aboutTitle }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/servicios") }}" class="hover:text-[#00FF99]">&gt; {{ $servicesLabel ?? $servicesTitle }}</a></li>
                    @endforelse
                </ul>
            </div>

            <!-- Col 3: Legal & Privacy -->
            <div class="space-y-3">
                <div class="font-bold text-white uppercase tracking-wider text-xs border-b border-gray-800 pb-2">
                    {{ $legalTitle }}
                </div>
                <ul class="space-y-2">
                    @forelse($footerLegal as $legal)
                        <li>
                            <a href="{{ $legal['url'] }}" class="hover:text-[#00FF99] transition-colors">
                                &gt; {{ $legal['title'] }}
                            </a>
                        </li>
                    @empty
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/contacto") }}" class="hover:text-[#00FF99]">&gt; {{ $contactTitle }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/privacidad") }}" class="hover:text-[#00FF99]">&gt; {{ $privacyTitle }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/terminos") }}" class="hover:text-[#00FF99]">&gt; {{ $termsTitle }}</a></li>
                    @endforelse
                    <li>
                        <button type="button"
                                @click="window.dispatchEvent(new CustomEvent('open-cookie-settings'))"
                                class="hover:text-[#00FF99] transition-colors text-left font-mono">
                            &gt; {{ $cookieSettingsTitle }}
                        </button>
                    </li>
                </ul>
            </div>

        </div>

        <!-- Bottom Bar -->
        <div class="mt-12 pt-8 border-t border-gray-800 flex flex-col sm:flex-row items-center justify-between text-[11px] text-gray-500">
            <div>
                &copy; {{ date('Y') }} {{ setting('legal.company_name', setting('general.site_name', 'dbCMS')) }}. {{ setting('general.footer_text', $rightsReserved) }}
            </div>
            <div class="mt-4 sm:mt-0 flex items-center space-x-2">
                <button type="button"
                        @click="window.dispatchEvent(new CustomEvent('open-geo-modal'))"
                        class="hover:text-white transition-colors cursor-pointer flex items-center gap-1.5"
                        title="{{ $currentLocale === 'ca' ? 'Canviar regió o idioma' : ($currentLocale === 'en' ? 'Change region or language' : 'Cambiar región o idioma') }}">
                    <span>{{ $marketActiveLabel }} <strong class="text-white uppercase">{{ $marketCode }}</strong></span>
                    <span>&bull;</span>
                    <span>{{ $languageLabel }} <strong class="text-[#00FF99] uppercase">{{ $currentLocale }}</strong></span>
                </button>
            </div>
        </div>
    </div>
</footer>

