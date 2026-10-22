<div class="space-y-4">
    @if($title !== '')<h2 class="text-xl font-semibold">{{ $title }}</h2>@endif
    @if($subtitle !== '')<p>{{ $subtitle }}</p>@endif
    <div class="grid {{ $gridClass }} gap-6">
        @foreach($items as $item)
            <a href="{{ $item['url'] }}" class="border border-slate-200 rounded-xl overflow-hidden">
                @if($item['image'] !== '')
                    <img src="{{ $item['image'] }}" alt="{{ $item['title'] }}" class="w-full h-40 object-cover" loading="lazy" decoding="async">
                @endif
                <div class="p-3 text-sm font-semibold">{{ $item['title'] }}</div>
                @if($showPrices)<div class="p-3">{{ $item['price'] }}</div>@endif
            </a>
        @endforeach
    </div>
</div>
