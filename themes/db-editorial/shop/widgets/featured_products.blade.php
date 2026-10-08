@php
    $defaultTitle = $currentLocale === 'ca' ? 'Edicions & Obras Destacades' : ($currentLocale === 'en' ? 'Featured Editions & Works' : 'Ediciones & Obras Destacadas');
    $displayTitle = !empty($title) ? $title : $defaultTitle;
    $defaultSubtitle = $currentLocale === 'ca' ? 'Selecció editorial cuidada per a lectors exigents.' : ($currentLocale === 'en' ? 'Curated editorial selection for discerning readers.' : 'Selección editorial cuidada para lectores exigentes.');
    $displaySubtitle = !empty($subtitle) ? $subtitle : $defaultSubtitle;
    $viewAllText = $currentLocale === 'ca' ? 'Veure Totes' : ($currentLocale === 'en' ? 'View All' : 'Ver Todas');
    $viewDetailsText = $currentLocale === 'ca' ? 'Descobrir' : ($currentLocale === 'en' ? 'Discover' : 'Descubrir');
    $shopIndexUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url('/shop', $currentMarket ?? null);
@endphp

<div class="w-full my-12 editorial-card p-8 sm:p-10">
    <div class="flex items-center justify-between border-b border-stone-200 pb-4 mb-6">
        <div>
            <h3 class="text-2xl font-serif font-bold text-stone-900">
                {{ $displayTitle }}
            </h3>
            <p class="text-xs text-stone-500 font-serif mt-1">{{ $displaySubtitle }}</p>
        </div>
        <a href="{{ $shopIndexUrl }}" class="text-xs text-amber-800 hover:underline font-semibold font-sans">
            {{ $viewAllText }} &rarr;
        </a>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 font-sans">
        @foreach($products as $prod)
            @php
                $trans = $prod->translations->where('locale', $currentLocale)->first() ?? $prod->translations->first();
                $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                $detailUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$trans?->slug}", $currentMarket);
            @endphp
            <div class="bg-white border border-stone-200 rounded-xl overflow-hidden flex flex-col justify-between transition-all hover:shadow-md group">
                <div class="h-44 bg-stone-100 overflow-hidden flex items-center justify-center">
                    @if($prod->featured_image)
                        <img src="{{ $prod->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                    @else
                        <span class="text-3xl text-stone-300"><i class="fa-solid fa-book-open"></i></span>
                    @endif
                </div>

                <div class="p-4 space-y-2 text-xs">
                    <h4 class="font-serif font-bold text-stone-900 group-hover:text-amber-800 transition-colors truncate text-sm">
                        {{ $trans?->name }}
                    </h4>
                    <div class="flex items-center justify-between pt-2 border-t border-stone-100 font-sans">
                        @if(!empty($showPrices))
                            <span class="font-serif font-bold text-stone-900 text-sm">{{ $prod->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="font-serif italic text-stone-600 font-semibold text-xs">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                        <a href="{{ $detailUrl }}" class="px-2.5 py-1 bg-stone-100 hover:bg-stone-900 hover:text-white text-stone-800 rounded text-[11px] font-medium transition-colors">
                            {{ $viewDetailsText }} &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
