@extends('themes::default-clean.layouts.app')

@section('title', $translation?->meta_title ?? $translation?->name ?? '')
@section('meta_description', $translation?->meta_description ?? '')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-2 gap-10">
    <div>
        @if($product->featured_image)
            <img src="{{ $product->featured_image }}" alt="{{ $translation?->name }}" class="w-full rounded-2xl object-cover" loading="lazy" decoding="async">
        @endif
    </div>
    <div class="space-y-4">
        @if($errors->any())
            <div role="alert" class="text-red-700">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif
        <h1 class="text-3xl font-bold">{{ $translation?->name }}</h1>
        @if(($showPrices ?? true))
            <div class="text-2xl font-black">{{ $product->getFormattedPrice($currentMarket) }}</div>
        @endif
        @if(empty($renderedSections))
            <div class="prose max-w-none">{!! app(\App\Services\Media\MarkdownRendererService::class)->renderHtml((string) $translation?->description) !!}</div>
        @endif
        @if($isShop ?? false)
            <form method="POST" action="{{ route('shop.cart.add', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-3">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">
                <input type="number" name="quantity" value="1" min="1" class="w-24 border border-slate-300 rounded-lg px-3 py-2">
                <button type="submit" class="btn-neon">{{ __('Add to cart') }}</button>
            </form>
        @endif
    </div>
</div>
@foreach($renderedSections ?? [] as $sectionHtml)
    {!! $sectionHtml !!}
@endforeach
@endsection