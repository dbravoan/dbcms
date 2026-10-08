@php
    $defaultTitle = $currentLocale === 'ca' ? 'PRODUCTES DESTACATS' : ($currentLocale === 'en' ? 'FEATURED GEAR' : 'PRODUCTOS DESTACADOS');
    $displayTitle = !empty($title) ? $title : $defaultTitle;
    $defaultSubtitle = $currentLocale === 'ca' ? 'Selecció radical amb màxim impacte visual.' : ($currentLocale === 'en' ? 'Radical selection with maximum visual punch.' : 'Selección radical con máximo impacto visual.');
    $displaySubtitle = !empty($subtitle) ? $subtitle : $defaultSubtitle;
    $viewAllText = $currentLocale === 'ca' ? 'VEURE TOTS' : ($currentLocale === 'en' ? 'VIEW ALL' : 'VER TODOS');
    $viewDetailsText = $currentLocale === 'ca' ? 'VEURE' : ($currentLocale === 'en' ? 'VIEW' : 'VER');
    $shopIndexUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url('/shop', $currentMarket ?? null);
@endphp

<div class="w-full my-12 brutalist-card p-6 sm:p-10 font-mono">
    <div class="flex items-center justify-between border-b-2 border-black pb-4 mb-6">
        <div>
            <h3 class="text-2xl font-black text-black uppercase flex items-center gap-2">
                <span class="bg-[#FFE600] px-2 py-0.5 border border-black shadow-[2px_2px_0px_0px_#000]"><i class="fa-solid fa-bolt"></i></span>
                <span>{{ $displayTitle }}</span>
            </h3>
            <p class="text-xs text-black font-bold mt-1">{{ $displaySubtitle }}</p>
        </div>
        <a href="{{ $shopIndexUrl }}" class="btn-brutalist text-xs px-3 py-1.5">
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
            <div class="bg-white border-2 border-black shadow-[3px_3px_0px_0px_#000] overflow-hidden flex flex-col justify-between transition-all group hover:bg-[#FFE600]/10">
                <div class="h-36 bg-black overflow-hidden flex items-center justify-center border-b-2 border-black">
                    @if($prod->featured_image)
                        <img src="{{ $prod->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                    @else
                        <span class="text-2xl text-[#FFE600] font-black">[ PROD ]</span>
                    @endif
                </div>

                <div class="p-4 space-y-2 text-xs font-black">
                    <h4 class="text-black group-hover:text-[#FF5A5F] transition-colors truncate uppercase">
                        {{ $trans?->name }}
                    </h4>
                    <div class="flex items-center justify-between pt-2 border-t-2 border-black">
                        @if(!empty($showPrices))
                            <span class="text-black font-black text-sm">{{ $prod->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-black font-black text-xs uppercase">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                        <a href="{{ $detailUrl }}" class="btn-brutalist px-2.5 py-1 text-[10px]">
                            {{ $viewDetailsText }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
