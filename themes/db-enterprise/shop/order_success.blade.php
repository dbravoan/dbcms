@extends('themes.db-enterprise.layouts.app')

@section('title', __('¡Contratación Confirmada! - dbCMS Enterprise'))

@section('content')
<div class="max-w-3xl mx-auto py-16 space-y-8">

    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-8 sm:p-12 text-center space-y-6">
        <div class="w-16 h-16 rounded-2xl bg-indigo-600/20 border border-indigo-500/40 flex items-center justify-center mx-auto text-2xl text-indigo-400 font-bold">
            <i class="fa-solid fa-circle-check text-[#00FF99]"></i>
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ __('¡Contratación Enterprise Confirmada!') }}</h1>
            <p>{{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.</p>
        </div>

        <!-- Order Reference Box -->
        <div class="p-6 bg-slate-950 border border-slate-800 rounded-xl inline-block text-left text-xs space-y-1.5 min-w-[300px]">
            <div class="text-slate-400">Referencia de Expediente: <strong class="text-indigo-400 font-mono text-sm ml-1">{{ $order->order_number }}</strong></div>
            <div class="text-slate-400">Fecha de Registro: <span class="text-white">{{ $order->created_at?->format('d/m/Y H:i') }}</span></div>
            <div class="text-slate-400">{{ __('Estado de Liquidación:') }}<span class="text-emerald-400 font-bold uppercase">{{ $order->payment_status }}</span></div>
            <div class="text-slate-400">{{ __('Importe Liquidado:') }}<span class="text-white font-bold text-sm">{{ $order->getFormattedGrandTotal() }}</span></div>
        </div>

        <!-- Items Overview -->
        <div class="text-left border-t border-slate-800 pt-6 space-y-3">
            <h3 class="text-xs font-bold text-white uppercase flex items-center gap-2">
                <span class="text-indigo-400"><i class="fa-solid fa-bolt"></i></span>
                <span>{{ __('Módulos y Licencias Suscritas') }}</span>
            </h3>
            <div class="space-y-2 text-xs divide-y divide-slate-800/60">
                @foreach($order->items as $item)
                    <div class="pt-2 flex justify-between items-center">
                        <div>
                            <span class="text-white font-semibold">{{ $item->product_name }}</span>
                            <span class="text-slate-500 text-[10px] block">SKU: {{ $item->sku ?? '-' }} (x{{ $item->quantity }} lic.)</span>
                        </div>
                        <span class="text-indigo-400 font-bold">{{ number_format($item->total, 2) }} {{ $order->currency }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $mCode = $order->market?->code ?? 'es';
            $loc = app()->getLocale();
        @endphp
        <div class="pt-6 border-t border-slate-800 flex justify-center gap-4">
            <a href="{{ route('shop.index', ['market' => $mCode, 'locale' => $loc]) }}" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition-colors shadow">
                Volver a Soluciones &rarr;
            </a>
        </div>
    </div>

</div>
@endsection
