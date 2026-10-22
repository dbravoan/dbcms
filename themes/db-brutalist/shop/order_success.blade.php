@extends('themes::db-brutalist.layouts.app')

@section('title', __('¡Pedido Confirmado! - dbCMS Brutalist'))

@section('content')
<div class="max-w-3xl mx-auto py-16 space-y-8 font-mono">

    <div class="brutalist-card p-8 sm:p-12 text-center space-y-6">
        <div class="w-16 h-16 bg-[#FFE600] border-3 border-black shadow-[4px_4px_0px_0px_#000] flex items-center justify-center mx-auto text-3xl font-black text-black">
            <i class="fa-solid fa-circle-check text-[#00FF99]"></i>
        </div>

        <div class="space-y-2">
            <h1 class="text-3xl sm:text-4xl font-black text-black uppercase">{{ __('¡PEDIDO CONFIRMADO CON ÉXITO!') }}</h1>
            <p class="text-black font-bold text-xs sm:text-sm max-w-md mx-auto">
                {{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.
            </p>
        </div>

        <!-- Order Reference Box -->
        <div class="p-6 bg-white border-3 border-black shadow-[4px_4px_0px_0px_#000] inline-block text-left text-xs space-y-2 min-w-[300px] font-black">
            <div>REFERENCIA: <strong class="bg-[#FFE600] px-1 border border-black ml-1">{{ $order->order_number }}</strong></div>
            <div>FECHA: <span>{{ $order->created_at?->format('d/m/Y H:i') }}</span></div>
            <div>{{ __('ESTADO:') }}<span class="bg-[#00F0FF] px-1 border border-black uppercase">{{ $order->payment_status }}</span></div>
            <div>{{ __('TOTAL:') }}<span class="text-base">{{ $order->getFormattedGrandTotal() }}</span></div>
        </div>

        <!-- Items Overview -->
        <div class="text-left border-t-2 border-black pt-6 space-y-3 font-black">
            <h3 class="text-xs uppercase bg-[#FFE600] inline-block px-2 py-0.5 border border-black">{{ __('ARTÍCULOS COMPRADOS') }}</h3>
            <div class="space-y-2 text-xs divide-y-2 divide-black/20">
                @foreach($order->items as $item)
                    <div class="pt-2 flex justify-between items-center">
                        <div>
                            <span class="text-black uppercase">{{ $item->product_name }}</span>
                            <span class="text-black/60 text-[10px] block">SKU: {{ $item->sku ?? '-' }} (x{{ $item->quantity }})</span>
                        </div>
                        <span class="font-black text-sm">{{ number_format($item->total, 2) }} {{ $order->currency }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $mCode = $order->market?->code ?? 'es';
            $loc = app()->getLocale();
        @endphp
        <div class="pt-6 border-t-2 border-black flex justify-center gap-4">
            <a href="{{ route('shop.index', ['market' => $mCode, 'locale' => $loc]) }}" class="btn-brutalist text-xs py-3 px-8">
                VOLVER A LA TIENDA &rarr;
            </a>
        </div>
    </div>

</div>
@endsection
