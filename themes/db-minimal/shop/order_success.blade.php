@extends('themes::db-minimal.layouts.app')

@section('title', __('Pedido Confirmado - dbCMS Minimal'))

@section('content')
<div class="max-w-2xl mx-auto py-16 space-y-8 font-sans">

    <div class="minimal-card p-8 sm:p-12 text-center space-y-6">
        <div class="w-12 h-12 rounded-full border border-neutral-300 flex items-center justify-center mx-auto text-xl text-neutral-900 font-mono">
            <i class="fa-solid fa-circle-check text-[#00FF99]"></i>
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl font-light text-neutral-900">{{ __('Pedido Confirmado') }}</h1>
            <p>{{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.</p>
        </div>

        <!-- Order Reference Box -->
        <div class="p-4 bg-neutral-50 border border-neutral-200 rounded text-left text-xs font-mono space-y-1 inline-block min-w-[260px]">
            <div class="text-neutral-400">Referencia: <strong class="text-neutral-900 ml-1">{{ $order->order_number }}</strong></div>
            <div class="text-neutral-400">Fecha: <span class="text-neutral-700">{{ $order->created_at?->format('Y.m.d H:i') }}</span></div>
            <div class="text-neutral-400">{{ __('Pago:') }}<span class="text-neutral-900 uppercase font-medium">{{ $order->payment_status }}</span></div>
            <div class="text-neutral-400">{{ __('Total:') }}<span class="text-neutral-900 font-semibold">{{ $order->getFormattedGrandTotal() }}</span></div>
        </div>

        <!-- Items Overview -->
        <div class="text-left border-t border-neutral-100 pt-6 space-y-3 font-mono text-xs">
            <h3 class="text-[10px] text-neutral-400 uppercase tracking-widest">{{ __('Resumen') }}</h3>
            <div class="space-y-2 divide-y divide-neutral-100">
                @foreach($order->items as $item)
                    <div class="pt-2 flex justify-between items-center">
                        <div>
                            <span class="text-neutral-900 font-sans font-medium">{{ $item->product_name }}</span>
                            <span class="text-neutral-400 text-[10px] block">x{{ $item->quantity }}</span>
                        </div>
                        <span class="font-medium text-neutral-900">{{ number_format($item->total, 2) }} {{ $order->currency }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $mCode = $order->market?->code ?? 'es';
            $loc = app()->getLocale();
        @endphp
        <div class="pt-6 border-t border-neutral-100 flex justify-center">
            <a href="{{ route('shop.index', ['market' => $mCode, 'locale' => $loc]) }}" class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-xs font-mono uppercase tracking-widest transition-colors">
                Volver al Catálogo &rarr;
            </a>
        </div>
    </div>

</div>
@endsection
