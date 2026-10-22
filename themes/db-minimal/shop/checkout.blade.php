@extends('themes::db-minimal.layouts.app')

@section('title', __('Finalizar Pedido - dbCMS Minimal'))

@section('content')
<div class="max-w-4xl mx-auto space-y-8 font-sans">

    <div class="border-b border-neutral-200/80 pb-4">
        <h1 class="text-3xl font-light text-neutral-900 tracking-tight">
            Finalizar Compra
        </h1>
        <p class="text-xs text-neutral-400 mt-1 font-mono">{{ __('Completa los datos esenciales para procesar el envío.') }}</p>
    </div>

    <form method="POST" action="{{ route('shop.checkout.process', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf

        <!-- Customer & Shipping Form (2 cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Step 1: Customer Contact -->
            <div class="minimal-card p-6 space-y-4">
                <h3 class="font-mono text-xs uppercase tracking-widest text-neutral-900 border-b border-neutral-100 pb-2 flex items-center gap-2">
                    <span>1.</span><span>{{ __('Contacto') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-neutral-400 font-mono text-[10px] uppercase mb-1">{{ __('Nombre Completo *') }}</label>
                        <input type="text" name="customer_name" required value="{{ old('customer_name', auth()->user()?->name ?? '') }}" placeholder="Astrid Lindgren" class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-neutral-400 font-mono text-[10px] uppercase mb-1">{{ __('Email *') }}</label>
                        <input type="email" name="customer_email" required value="{{ old('customer_email', auth()->user()?->email ?? '') }}" placeholder="tu@email.com" class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-neutral-400 font-mono text-[10px] uppercase mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="+00 000 000 000" class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900">
                    </div>
                </div>
            </div>

            <!-- Step 2: Shipping Address -->
            <div class="minimal-card p-6 space-y-4">
                <h3 class="font-mono text-xs uppercase tracking-widest text-neutral-900 border-b border-neutral-100 pb-2 flex items-center gap-2">
                    <span>2.</span><span>{{ __('Dirección') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-neutral-400 font-mono text-[10px] uppercase mb-1">{{ __('Calle y Número *') }}</label>
                        <input type="text" name="address" required value="{{ old('address') }}" placeholder="Drottninggatan 10" class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-neutral-400 font-mono text-[10px] uppercase mb-1">{{ __('Ciudad *') }}</label>
                        <input type="text" name="city" required value="{{ old('city') }}" placeholder="Stockholm" class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div>
                        <label class="block text-neutral-400 font-mono text-[10px] uppercase mb-1">{{ __('Código Postal *') }}</label>
                        <input type="text" name="postal_code" required value="{{ old('postal_code') }}" placeholder="111 51" class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-neutral-400 font-mono text-[10px] uppercase mb-1">{{ __('País *') }}</label>
                        <input type="text" name="country" required value="{{ old('country', $currentMarket->name) }}" class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900 focus:outline-none focus:border-neutral-900">
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment Method -->
            <div class="minimal-card p-6 space-y-4">
                <h3 class="font-mono text-xs uppercase tracking-widest text-neutral-900 border-b border-neutral-100 pb-2 flex items-center gap-2">
                    <span>3.</span><span>{{ __('Método de Pago') }}</span>
                </h3>

                <div class="space-y-2 text-xs">

                    <label class="flex items-center gap-3 p-3 bg-white border border-neutral-200 rounded hover:border-neutral-400 cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="bank_transfer" checked class="text-neutral-900 focus:ring-neutral-900">
                        <span class="font-medium text-neutral-900 flex-1">{{ __('Transferencia Directa') }}</span>
                        <span class="text-[10px] font-mono text-neutral-400 uppercase">{{ __('Manual') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 bg-white border border-neutral-200 rounded hover:border-neutral-400 cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="cod" class="text-neutral-900 focus:ring-neutral-900">
                        <span class="font-medium text-neutral-900 flex-1">{{ __('Pago en Entrega') }}</span>
                        <span class="text-[10px] font-mono text-neutral-400 uppercase">{{ __('Contra reembolso') }}</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Checkout Summary (1 col) -->
        <div class="minimal-card p-6 space-y-4 h-fit font-mono text-xs">
            <h3 class="font-medium text-neutral-900 uppercase tracking-widest text-[10px] pb-2 border-b border-neutral-100">
                {{ __('Artículos') }} ({{ count($items) }})
            </h3>

            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                @foreach($items as $item)
                    @php
                        $p = $item['product'];
                        $trans = $p->translations->where('locale', $currentLocale)->first();
                    @endphp
                    <div class="flex items-center justify-between text-xs">
                        <div class="truncate mr-2 font-sans">
                            <span class="text-neutral-900 font-medium block truncate">{{ $trans?->name }}</span>
                            <span class="text-[10px] text-neutral-400 font-mono">x{{ $item['quantity'] }}</span>
                        </div>
                        <span class="font-medium text-neutral-900 shrink-0">{{ $item['formatted_subtotal'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-neutral-100 space-y-2">
                <div class="flex justify-between text-neutral-500">
                    <span>Subtotal:</span>
                    <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-neutral-500">
                    <span>IVA:</span>
                    <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-neutral-900 font-semibold text-sm pt-2 border-t border-neutral-100 font-sans">
                    <span>{{ __('Total:') }}</span>
                    <span>{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-xs uppercase tracking-widest font-mono transition-colors">
                    Confirmar Pedido &rarr;
                </button>
            </div>
        </div>

    </form>

</div>
@endsection
