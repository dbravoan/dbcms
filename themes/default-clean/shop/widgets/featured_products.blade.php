<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
    @foreach($products ?? [] as $product)
        @php $trans = $product->translations->where('locale', app()->getLocale())->first() ?? $product->translations->first(); @endphp
        <a href="{{ $localization->url('/shop/'.$trans?->slug, $currentMarket) }}" class="border border-slate-200 rounded-xl overflow-hidden">
            @if($product->featured_image)
                <img src="{{ $product->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-40 object-cover" loading="lazy" decoding="async">
            @endif
            <div class="p-3 text-sm font-semibold">{{ $trans?->name }}</div>
        </a>
    @endforeach
</div>