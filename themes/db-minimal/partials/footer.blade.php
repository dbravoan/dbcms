@php
    $minimalBrandDesc = match($currentLocale) {
        'ca' => 'Arquitectura digital pura, hiper-minimalista i optimitzada per a l\'era dels models d\'intel·ligència artificial.',
        'en' => 'Pure digital architecture, ultra-minimalist and optimized for the artificial intelligence era.',
        default => 'Arquitectura digital pura, hiper-minimalista y optimizada para la era de los modelos de inteligencia artificial.',
    };
    $minimalRights = match($currentLocale) {
        'ca' => 'Tots els drets reservats.',
        'en' => 'All rights reserved.',
        default => 'Todos los derechos reservados.',
    };
@endphp

<footer class="border-t border-neutral-200/80 bg-white pt-16 pb-12 text-xs text-neutral-500 font-sans">
    <div class="max-w-6xl mx-auto px-4 sm:px-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-12 mb-12">
            <!-- Col 1: Brand -->
            <div class="md:col-span-2 space-y-3">
                <div class="text-base font-mono font-medium tracking-widest uppercase text-neutral-900">db.minimal</div>
                <p class="text-neutral-500 text-xs max-w-sm leading-relaxed">
                    {{ $minimalBrandDesc }}
                </p>
                <div class="flex items-center space-x-2 pt-2 text-[11px] font-mono">
                    <a href="{{ url("/{$marketCode}/{$currentLocale}/llms.txt") }}" class="px-2 py-0.5 border border-neutral-200 rounded hover:border-neutral-900 text-neutral-600 hover:text-neutral-950 transition-colors">llms.txt</a>
                    <a href="{{ url("/{$marketCode}/{$currentLocale}/sitemap.xml") }}" class="px-2 py-0.5 border border-neutral-200 rounded hover:border-neutral-900 text-neutral-600 hover:text-neutral-950 transition-colors">sitemap.xml</a>
                </div>
            </div>

            <!-- Col 2: Navigation -->
            <div class="space-y-3">
                <div class="text-[10px] font-mono uppercase tracking-widest text-neutral-400">{{ __('Índice') }}</div>
                <ul class="space-y-2">
                    @forelse($footerQuick as $link)
                        <li><a href="{{ $link['url'] }}" class="text-neutral-600 hover:text-neutral-900 transition-colors">{{ $link['title'] }}</a></li>
                    @empty
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/") }}" class="hover:text-neutral-900">{{ __('Inicio') }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/blog") }}" class="hover:text-neutral-900">{{ __('Blog') }}</a></li>
                    @endforelse
                </ul>
            </div>

            <!-- Col 3: Legal & Privacy -->
            <div class="space-y-3">
                <div class="text-[10px] font-mono uppercase tracking-widest text-neutral-400">Legal</div>
                <ul class="space-y-2">
                    @forelse($footerLegal as $legal)
                        <li><a href="{{ $legal['url'] }}" class="text-neutral-600 hover:text-neutral-900 transition-colors">{{ $legal['title'] }}</a></li>
                    @empty
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/contacto") }}" class="hover:text-neutral-900">{{ __('Contacto') }}</a></li>
                        <li><a href="{{ url("/{$marketCode}/{$currentLocale}/privacidad") }}" class="hover:text-neutral-900">Privacidad (RGPD)</a></li>
                    @endforelse
                    <li>
                        <button type="button"
                                data-dispatch="open-cookie-settings"
                                class="text-neutral-600 hover:text-neutral-900 transition-colors text-left">
                            Galletas (Cookies)
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <div class="pt-8 border-t border-neutral-100 flex flex-col sm:flex-row items-center justify-between text-[11px] text-neutral-400">
            <div>&copy; {{ date('Y') }} {{ $cmsBrandName ?? config('cms.name') }} Minimal Studio. {{ $minimalRights }}</div>
            <div class="mt-2 sm:mt-0">
                <button type="button"
                        data-dispatch="open-geo-modal"
                        class="hover:text-neutral-800 transition-colors font-mono uppercase">
                    {{ $marketCode }} / {{ $currentLocale }}
                </button>
            </div>
        </div>
    </div>
</footer>
