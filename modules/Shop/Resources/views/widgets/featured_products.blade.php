@php
    $defaultTitle = $currentLocale === 'ca' ? 'Productes & Solucions Destacades' : ($currentLocale === 'en' ? 'Featured Products & Solutions' : 'Productos & Soluciones Destacadas');
    $displayTitle = !empty($title) ? $title : $defaultTitle;
    $defaultSubtitle = $currentLocale === 'ca' ? 'Catàleg oficial amb arquitectura d\'alt rendiment.' : ($currentLocale === 'en' ? 'Official catalog with high-performance architecture.' : 'Catálogo oficial con arquitectura de alto rendimiento.');
    $displaySubtitle = !empty($subtitle) ? $subtitle : $defaultSubtitle;
    $viewAllText = $currentLocale === 'ca' ? 'Veure Tots' : ($currentLocale === 'en' ? 'View All' : 'Ver Todos');
    $viewDetailsText = $currentLocale === 'ca' ? 'Veure' : ($currentLocale === 'en' ? 'View' : 'Ver');
    $shopIndexUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url('/shop', $currentMarket ?? null);
@endphp

<div class="w-full my-8 cyber-card p-6 sm:p-10 font-mono" data-scroll-magic>
    <div class="flex items-center justify-between border-b border-gray-800 pb-4 mb-6">
        <div>
            <h3 class="text-lg font-bold text-white flex items-center gap-2.5">
                <i class="fa-solid fa-bag-shopping text-[#00FF99]"></i>
                <span>{{ $displayTitle }}</span>
            </h3>
            <p class="text-xs text-gray-400 mt-0.5">{{ $displaySubtitle }}</p>
        </div>
        <a href="{{ $shopIndexUrl }}" class="text-xs text-[#00FF99] hover:underline font-bold">
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
            <div class="bg-[#181818] border border-gray-800 hover:border-[#00FF99] rounded-xl overflow-hidden flex flex-col justify-between transition-all group">
                <div class="h-36 bg-black overflow-hidden flex items-center justify-center">
                    @if($prod->featured_image)
                        <img src="{{ $prod->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                    @else
                        <span class="text-3xl text-gray-600"><i class="fa-solid fa-box"></i></span>
                    @endif
                </div>

                <div class="p-4 space-y-2 text-xs">
                    <h4 class="font-bold text-white group-hover:text-[#00FF99] transition-colors truncate">
                        {{ $trans?->name }}
                    </h4>
                    <div class="flex items-center justify-between pt-2 border-t border-gray-800">
                        @php
                            $widgetShowPrices = app(\Modules\Shop\Services\ShopDomainService::class)->shouldShowPrices();
                        @endphp
                        @if($widgetShowPrices)
                            <span class="font-bold text-[#00FF99]">{{ $prod->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-[10px] text-gray-400 font-mono">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                        <a href="{{ $detailUrl }}" class="px-2.5 py-1 bg-[#121212] hover:bg-[#00FF99] hover:text-black text-gray-300 rounded text-[10px] font-bold transition-colors">
                            {{ $viewDetailsText }} &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
