@php
    $defaultTitle = $currentLocale === 'ca' ? 'Productes Aurora Destacats' : ($currentLocale === 'en' ? 'Featured Aurora Products' : 'Productos Aurora Destacados');
    $displayTitle = !empty($title) ? $title : $defaultTitle;
    $defaultSubtitle = $currentLocale === 'ca' ? 'Disseny innovador i tecnologia d\'última generació.' : ($currentLocale === 'en' ? 'Innovative design and next-generation technology.' : 'Diseño innovador y tecnología de última generación.');
    $displaySubtitle = !empty($subtitle) ? $subtitle : $defaultSubtitle;
    $viewAllText = $currentLocale === 'ca' ? 'Veure Tots' : ($currentLocale === 'en' ? 'View All' : 'Ver Todos');
    $viewDetailsText = $currentLocale === 'ca' ? 'Veure' : ($currentLocale === 'en' ? 'View' : 'Ver');
    $shopIndexUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url('/shop', $currentMarket ?? null);
@endphp

<div class="w-full my-12 sunset-card p-6 sm:p-10">
    <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-6">
        <div>
            <h3 class="text-xl font-bold text-white flex items-center gap-2">
                <span class="text-rose-400"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                <span>{{ $displayTitle }}</span>
            </h3>
            <p class="text-xs text-slate-400 mt-1">{{ $displaySubtitle }}</p>
        </div>
        <a href="{{ $shopIndexUrl }}" class="text-xs text-rose-400 hover:text-amber-300 hover:underline font-semibold">
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
            <div class="bg-white/5 border border-white/10 hover:border-rose-500/40 rounded-2xl overflow-hidden flex flex-col justify-between transition-all group">
                <div class="h-36 bg-slate-900 overflow-hidden flex items-center justify-center">
                    @if($prod->featured_image)
                        <img src="{{ $prod->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                    @else
                        <span class="text-3xl text-slate-700"><i class="fa-solid fa-box"></i></span>
                    @endif
                </div>

                <div class="p-4 space-y-2 text-xs">
                    <h4 class="font-semibold text-white group-hover:text-rose-400 transition-colors truncate">
                        {{ $trans?->name }}
                    </h4>
                    <div class="flex items-center justify-between pt-2 border-t border-white/5">
                        @if(!empty($showPrices))
                            <span class="font-bold text-white">{{ $prod->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-xs text-rose-400 font-semibold">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                        <a href="{{ $detailUrl }}" class="px-2.5 py-1 bg-white/10 hover:bg-gradient-to-r hover:from-rose-500 hover:to-amber-500 text-slate-200 hover:text-white rounded-lg text-[10px] font-semibold transition-all">
                            {{ $viewDetailsText }} &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
