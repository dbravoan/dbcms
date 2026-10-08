@php
    $defaultTitle = $currentLocale === 'ca' ? 'Solucions Enterprise Destacades' : ($currentLocale === 'en' ? 'Featured Enterprise Solutions' : 'Soluciones Enterprise Destacadas');
    $displayTitle = !empty($title) ? $title : $defaultTitle;
    $defaultSubtitle = $currentLocale === 'ca' ? 'Arquitectura corporativa escalable d\'alta disponibilitat.' : ($currentLocale === 'en' ? 'Scalable corporate architecture with high availability.' : 'Arquitectura corporativa escalable de alta disponibilidad.');
    $displaySubtitle = !empty($subtitle) ? $subtitle : $defaultSubtitle;
    $viewAllText = $currentLocale === 'ca' ? 'Veure Totes' : ($currentLocale === 'en' ? 'View All' : 'Ver Todas');
    $viewDetailsText = $currentLocale === 'ca' ? 'Detalls' : ($currentLocale === 'en' ? 'Details' : 'Detalles');
    $shopIndexUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url('/shop', $currentMarket ?? null);
@endphp

<div class="w-full my-12 bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-10">
    <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <span class="text-indigo-400"><i class="fa-solid fa-bolt"></i></span>
                <span>{{ $displayTitle }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">{{ $displaySubtitle }}</p>
        </div>
        <a href="{{ $shopIndexUrl }}" class="text-xs text-indigo-400 hover:text-indigo-300 hover:underline font-semibold">
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
            <div class="bg-slate-950 border border-slate-800 hover:border-indigo-500/50 rounded-xl overflow-hidden flex flex-col justify-between transition-all group">
                <div class="h-36 bg-slate-900 overflow-hidden flex items-center justify-center">
                    @if($prod->featured_image)
                        <img src="{{ $prod->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                    @else
                        <span class="text-3xl text-slate-700"><i class="fa-solid fa-box"></i></span>
                    @endif
                </div>

                <div class="p-4 space-y-2 text-xs">
                    <h4 class="font-semibold text-white group-hover:text-indigo-400 transition-colors truncate">
                        {{ $trans?->name }}
                    </h4>
                    <div class="flex items-center justify-between pt-2 border-t border-slate-800/80">
                        @if(!empty($showPrices))
                            <span class="font-bold text-white">{{ $prod->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-xs text-indigo-400 font-semibold">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                        <a href="{{ $detailUrl }}" class="px-2.5 py-1 bg-indigo-600/20 hover:bg-indigo-600 text-indigo-300 hover:text-white rounded text-[10px] font-semibold transition-colors">
                            {{ $viewDetailsText }} &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
