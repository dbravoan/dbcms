@extends('themes.default-clean.layouts.app')

@section('title', __('Order Confirmed'))

@section('content')
<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center space-y-4">
    <h1 class="text-3xl font-bold">{{ __('Order Confirmed Successfully!') }}</h1>
    <p>{{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.</p>
    <div class="text-sm text-slate-600">{{ __('Order Number:') }} <strong>{{ $order->order_number ?? '' }}</strong></div>
</div>
@endsection
