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
                    <span>{{ $item['name'] ?? '' }}</span>
                    <span>{{ $item['quantity'] ?? 1 }} × {{ number_format((float) ($item['price'] ?? 0), 2) }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection