@php
    $layout = app(\App\Services\Cms\FrontViewResolver::class)->layout();
@endphp
@extends($layout)

@section('title', __('¡Pedido Confirmado! - dbCMS'))

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-16 font-mono space-y-8">

    <div class="cyber-card p-8 sm:p-12 text-center space-y-6" data-scroll-magic>
        <div class="w-16 h-16 rounded-full bg-[#00FF99]/20 border-2 border-[#00FF99] flex items-center justify-center mx-auto text-2xl text-[#00FF99]">
            <i class="fa-solid fa-check"></i>
        </div>

        <div class="space-y-2">
            <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ __('¡Pedido Confirmado con Éxito!') }}</h1>
            <p>{{ __('Hemos registrado tu pedido. Contacto:') }} {{ $order->customer_email }}.</p>
        </div>

        <!-- Order Reference Box -->
        <div class="p-4 bg-[#181818] border border-gray-800 rounded-xl inline-block text-left text-xs space-y-1">
            <div class="text-gray-400">{{ __('Número de Pedido:') }}<strong class="text-[#00FF99] font-mono text-sm ml-1">{{ $order->order_number }}</strong></div>
            <div class="text-gray-400">Fecha: <span class="text-white">{{ $order->created_at?->format('d/m/Y H:i') }}</span></div>
            <div class="text-gray-400">{{ __('Estado de Pago:') }}<span class="text-emerald-400 font-bold uppercase">{{ $order->payment_status }}</span></div>
            <div class="text-gray-400">{{ __('Total:') }}<span class="text-white font-bold">{{ $order->getFormattedGrandTotal() }}</span></div>
        </div>

        <!-- Items Overview -->
        <div class="text-left border-t border-gray-800 pt-6 space-y-3">
            <h3 class="text-xs font-bold text-white uppercase">&gt; Artículos Comprados</h3>
            <div class="space-y-2 text-xs divide-y divide-gray-800/60">
                @foreach($order->items as $item)
                    <div class="pt-2 flex justify-between items-center">
                        <div>
                            <span class="text-white font-bold">{{ $item->product_name }}</span>
                            <span class="text-gray-500 text-[10px] block">SKU: {{ $item->sku ?? '-' }} (x{{ $item->quantity }})</span>
                        </div>
                        <span class="text-[#00FF99] font-bold">{{ number_format($item->total, 2) }} {{ $order->currency }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        @php
            $mCode = $order->market?->code ?? 'es';
            $loc = app()->getLocale();
        @endphp
        <div class="pt-6 border-t border-gray-800 flex justify-center gap-4">
            <a href="{{ route('shop.index', ['market' => $mCode, 'locale' => $loc]) }}" class="btn-neon text-xs py-2 px-6">
                Volver a la Tienda &rarr;
            </a>
        </div>
    </div>

</div>
@endsection
