@php
    $displayTitle = $title;
    $displaySubtitle = $subtitle;
    $viewAllText = $currentLocale === 'ca' ? 'Veure Tots' : ($currentLocale === 'en' ? 'View All' : 'Ver Todos');
    $viewDetailsText = $currentLocale === 'ca' ? 'Veure' : ($currentLocale === 'en' ? 'View' : 'Ver');
    $shopIndexUrl = $indexUrl;
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

    <div class="grid {{ $gridClass }} gap-6">
        @foreach($items as $item)
            <div class="border border-neutral-200 hover:border-neutral-400 rounded overflow-hidden flex flex-col justify-between transition-colors group">
                <div class="h-36 bg-neutral-50 overflow-hidden flex items-center justify-center">
                    @if($item['image'])
                        <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                    @else
                        <span class="text-2xl text-neutral-300">◻</span>
                    @endif
                </div>

                <div class="p-4 space-y-2 text-xs">
                    <h4 class="font-medium text-neutral-900 truncate">
                        {{ $item['title'] }}
                    </h4>
                    <div class="flex items-center justify-between pt-2 border-t border-neutral-100 font-mono">
                        @if(!empty($showPrices))
                            <span class="font-semibold text-neutral-900">{{ $item['price'] }}</span>
                        @else
                            <span class="text-xs font-semibold text-neutral-600">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                        <a href="{{ $item['url'] }}" class="px-2 py-0.5 border border-neutral-300 hover:border-neutral-900 hover:bg-neutral-900 hover:text-white text-neutral-700 rounded text-[10px] uppercase tracking-wider transition-colors">
                            {{ $viewDetailsText }}
                        </a>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>
