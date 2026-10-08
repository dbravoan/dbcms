@extends('themes.default-clean.layouts.app')

@section('title', $isShop ?? false ? __('Online Store') : __('Catalog'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ $isShop ?? false ? __('Online Store') : __('Catalog') }}</h1>
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        @forelse($products as $product)
            @php $trans = $product->translations->where('locale', $currentLocale)->first() ?? $product->translations->first(); @endphp
            <a href="{{ $localization->url('/shop/'.$trans?->slug, $currentMarket) }}" class="border border-slate-200 rounded-xl overflow-hidden group">
                @if($product->featured_image)
                    <img src="{{ $product->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-44 object-cover" loading="lazy" decoding="async">
                @endif
                <div class="p-4 space-y-1">
                    <div class="font-semibold">{{ $trans?->name }}</div>
                    @if(($showPrices ?? true) && $product->price)
                        <div class="text-sm text-slate-500">{{ number_format((float) $product->price, 2) }} {{ $currentMarket->currency ?? '' }}</div>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No products in this selection') }}</p>
        @endforelse
    </div>
    <div>{{ $products->links() }}</div>
</div>
@endsection