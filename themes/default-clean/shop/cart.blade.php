@extends('themes::default-clean.layouts.app')

@section('title', __('Shopping Cart'))

@section('content')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
    <h1 class="text-3xl font-bold">{{ __('Shopping Cart') }}</h1>
    @if(empty($items))
        <p class="text-slate-500">{{ __('Your cart is empty.') }}</p>
    @else
        <ul class="divide-y divide-slate-200">
            @foreach($items as $item)
                <li class="py-3 flex items-center justify-between text-sm">
                    <span>{{ $item['product']->translations->firstWhere('locale', $currentLocale)?->name ?? $item['product']->sku }}</span>
                    <span>{{ $item['quantity'] ?? 1 }} × {{ $item['formatted_unit_price'] }}</span>
                </li>
            @endforeach
        </ul>
        <p>{{ __('Total:') }} {{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</p>
        <a class="btn-neon" href="{{ route('shop.checkout', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}">{{ __('Complete Order') }}</a>
    @endif
</div>
@endsection