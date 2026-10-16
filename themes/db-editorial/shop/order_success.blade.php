@extends('themes::db-editorial.layouts.app')

@section('title', __('¡Pedido Confirmado! - Editorial dbCMS'))

@section('content')
<div class="max-w-3xl mx-auto py-16 space-y-8 font-sans">

    <div class="editorial-card p-8 sm:p-12 text-center space-y-6">
        <div class="w-16 h-16 rounded-full bg-stone-100 border border-stone-300 flex items-center justify-center mx-auto text-2xl text-stone-900">
            <i class="fa-solid fa-circle-check text-[#00FF99]"></i>
        </div>

        <div class="space-y-2">
            <h1 class="text-3xl font-serif font-bold text-stone-900">{{ __('¡Pedido Recibido con Éxito!') }}</h1>
            <p class="text-stone-600 text-sm max-w-md mx-auto">
                {{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.
            </p>
        </div>

        <!-- Order Reference Box -->
        <div class="p-6 bg-stone-50 border border-stone-200 rounded-xl inline-block text-left text-xs space-y-1.5 min-w-[280px]">
            <div class="text-stone-500">{{ __('Número de Pedido:') }}<strong class="text-stone-900 font-mono text-sm ml-1">{{ $order->order_number }}</strong></div>
            <div class="text-stone-500">Fecha: <span class="text-stone-900 font-medium">{{ $order->created_at?->format('d/m/Y H:i') }}</span></div>
            <div class="text-stone-500">{{ __('Estado de Pago:') }}<span class="text-emerald-800 font-bold uppercase">{{ $order->payment_status }}</span></div>
            <div class="text-stone-500">{{ __('Total:') }}<span class="text-stone-900 font-serif font-bold text-sm">{{ $order->getFormattedGrandTotal() }}</span></div>
        </div>

        <!-- Items Overview -->
        <div class="text-left border-t border-stone-200 pt-6 space-y-3">
            <h3 class="text-xs font-serif font-bold text-stone-900 uppercase">{{ __('Resumen de Artículos') }}</h3>
            <div class="space-y-2 text-xs divide-y divide-stone-100">
                @foreach($order->items as $item)
                    <div class="pt-2 flex justify-between items-center">
                        <div>
                            <span class="text-stone-900 font-medium">{{ $item->product_name }}</span>
                            <span class="text-stone-400 text-[10px] block">REF: {{ $item->sku ?? '-' }} (x{{ $item->quantity }})</span>
                        </div>
                        <span class="font-serif font-bold text-stone-900">{{ number_format($item->total, 2) }} {{ $order->currency }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $mCode = $order->market?->code ?? 'es';
            $loc = app()->getLocale();
        @endphp
        <div class="pt-6 border-t border-stone-200 flex justify-center gap-4">
            <a href="{{ route('shop.index', ['market' => $mCode, 'locale' => $loc]) }}" class="px-6 py-2.5 bg-stone-900 hover:bg-stone-800 text-white rounded-lg text-xs font-medium transition-colors">
                Volver al Catálogo &rarr;
            </a>
        </div>
    </div>

</div>
@endsection
