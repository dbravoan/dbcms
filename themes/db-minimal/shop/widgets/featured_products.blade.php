@php
    $defaultTitle = $currentLocale === 'ca' ? 'Productes Destacats' : ($currentLocale === 'en' ? 'Featured Selection' : 'Selección Destacada');
    $displayTitle = !empty($title) ? $title : $defaultTitle;
    $defaultSubtitle = $currentLocale === 'ca' ? 'Peces seleccionades pel seu disseny i simplicitat.' : ($currentLocale === 'en' ? 'Pieces selected for their design and simplicity.' : 'Piezas seleccionadas por su diseño y simplicidad.');
    $displaySubtitle = !empty($subtitle) ? $subtitle : $defaultSubtitle;
    $viewAllText = $currentLocale === 'ca' ? 'Veure Tots' : ($currentLocale === 'en' ? 'View All' : 'Ver Todos');
    $viewDetailsText = $currentLocale === 'ca' ? 'Veure' : ($currentLocale === 'en' ? 'View' : 'Ver');
    $shopIndexUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url('/shop', $currentMarket ?? null);
@endphp

<div class="w-full my-12 minimal-card p-6 sm:p-10 font-sans">
    <div class="flex items-center justify-between border-b border-neutral-100 pb-4 mb-6">
        <div>
            <h3 class="text-xl font-light text-neutral-900">
                {{ $displayTitle }}
            </h3>
            <p class="text-xs text-neutral-400 font-mono mt-0.5">{{ $displaySubtitle }}</p>
        </div>
        <a href="{{ $shopIndexUrl }}" class="text-xs text-neutral-900 hover:underline font-mono uppercase tracking-widest">
            {{ $viewAllText }} &rarr;
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        @foreach($products as $prod)
            @php
                $trans = $prod->translations->where('locale', $currentLocale)->first() ?? $prod->translations->first();
                $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                $detailUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$trans?->slug}", $currentMarket);
            @endphp
            <div class="border border-neutral-200 hover:border-neutral-400 rounded overflow-hidden flex flex-col justify-between transition-colors group">
                <div class="h-36 bg-neutral-50 overflow-hidden flex items-center justify-center">
                    @if($prod->featured_image)
                        <img src="{{ $prod->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                    @else
                        <span class="text-2xl text-neutral-300">◻</span>
                    @endif
                </div>

                <div class="p-4 space-y-2 text-xs">
                    <h4 class="font-medium text-neutral-900 truncate">
                        {{ $trans?->name }}
                    </h4>
                    <div class="flex items-center justify-between pt-2 border-t border-neutral-100 font-mono">
                        @if(!empty($showPrices))
                            <span class="font-semibold text-neutral-900">{{ $prod->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-xs font-semibold text-neutral-600">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                        <a href="{{ $detailUrl }}" class="px-2 py-0.5 border border-neutral-300 hover:border-neutral-900 hover:bg-neutral-900 hover:text-white text-neutral-700 rounded text-[10px] uppercase tracking-wider transition-colors">
                            {{ $viewDetailsText }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
