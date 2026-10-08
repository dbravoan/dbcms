@extends('themes.db-sunset.layouts.app')

@section('title', __('¡Pedido Confirmado! - dbCMS Sunset'))

@section('content')
<div class="max-w-3xl mx-auto py-16 space-y-8">

    <div class="sunset-card p-8 sm:p-12 text-center space-y-6">
        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-rose-500 to-amber-500 flex items-center justify-center mx-auto text-2xl text-white font-bold shadow-xl shadow-rose-500/30">
            <i class="fa-solid fa-circle-check text-[#00FF99]"></i>
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ __('¡Compra Confirmada con Éxito!') }}</h1>
            <p>{{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.</p>
        </div>

        <!-- Order Reference Box -->
        <div class="p-6 bg-white/5 border border-white/10 rounded-2xl inline-block text-left text-xs space-y-1.5 min-w-[300px]">
            <div class="text-slate-400">Referencia: <strong class="text-rose-400 font-mono text-sm ml-1">{{ $order->order_number }}</strong></div>
            <div class="text-slate-400">Fecha: <span class="text-white">{{ $order->created_at?->format('d/m/Y H:i') }}</span></div>
            <div class="text-slate-400">{{ __('Estado de Pago:') }}<span class="text-emerald-400 font-bold uppercase">{{ $order->payment_status }}</span></div>
            <div class="text-slate-400">{{ __('Importe Total:') }}<span class="text-white font-bold text-sm">{{ $order->getFormattedGrandTotal() }}</span></div>
        </div>

        <!-- Items Overview -->
        <div class="text-left border-t border-white/10 pt-6 space-y-3">
            <h3 class="text-xs font-bold text-white uppercase flex items-center gap-2">
                <span class="text-rose-400"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                <span>{{ __('Artículos Adquiridos') }}</span>
            </h3>
            <div class="space-y-2 text-xs divide-y divide-white/5">
                @foreach($order->items as $item)
                    <div class="pt-2 flex justify-between items-center">
                        <div>
                            <span class="text-white font-semibold">{{ $item->product_name }}</span>
                            <span class="text-slate-500 text-[10px] block">SKU: {{ $item->sku ?? '-' }} (x{{ $item->quantity }})</span>
                        </div>
                        <span class="text-rose-400 font-bold">{{ number_format($item->total, 2) }} {{ $order->currency }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $mCode = $order->market?->code ?? 'es';
            $loc = app()->getLocale();
        @endphp
        <div class="pt-6 border-t border-white/10 flex justify-center gap-4">
            <a href="{{ route('shop.index', ['market' => $mCode, 'locale' => $loc]) }}" class="btn-sunset text-xs py-2.5 px-6">
                Volver a la Tienda &rarr;
            </a>
        </div>
    </div>

</div>
@endsection
