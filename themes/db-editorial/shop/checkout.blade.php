@extends('themes::db-editorial.layouts.app')

@section('title', __('Finalizar Pedido - Editorial dbCMS'))

@section('content')
<div class="max-w-5xl mx-auto space-y-8 font-sans">

    <div class="border-b border-stone-200 pb-4">
        <h1 class="text-3xl font-serif font-bold text-stone-900">{{ __('Finalizar Pedido') }}</h1>
        <p class="text-xs text-stone-500 mt-1">{{ __('Introduce los datos de envío y confirma tu forma de pago preferida.') }}</p>
    </div>

    <form method="POST" action="{{ route('shop.checkout.process', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf

        <!-- Customer & Shipping Form (2 cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Step 1: Customer Contact -->
            <div class="editorial-card p-6 space-y-4">
                <h3 class="font-serif font-bold text-stone-900 text-sm border-b border-stone-200 pb-2 flex items-center gap-2">
                    <span class="text-amber-800">1.</span><span>Datos del Comprador</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-stone-600 text-[10px] uppercase font-bold mb-1">{{ __('Nombre Completo *') }}</label>
                        <input type="text" name="customer_name" required value="{{ old('customer_name', auth()->user()?->name ?? '') }}" placeholder="{{ __('Nombre completo') }}" class="w-full bg-white border border-stone-300 focus:border-stone-500 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                    <div>
                        <label class="block text-stone-600 text-[10px] uppercase font-bold mb-1">{{ __('Email de Confirmación *') }}</label>
                        <input type="email" name="customer_email" required value="{{ old('customer_email', auth()->user()?->email ?? '') }}" placeholder="tu@email.com" class="w-full bg-white border border-stone-300 focus:border-stone-500 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                    <div>
                        <label class="block text-stone-600 text-[10px] uppercase font-bold mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="+00 000 000 000" class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                </div>
            </div>

            <!-- Step 2: Shipping Address -->
            <div class="editorial-card p-6 space-y-4">
                <h3 class="font-serif font-bold text-stone-900 text-sm border-b border-stone-200 pb-2 flex items-center gap-2">
                    <span class="text-amber-800">2.</span><span>{{ __('Dirección de Envío') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-stone-600 text-[10px] uppercase font-bold mb-1">{{ __('Dirección / Calle y Número *') }}</label>
                        <input type="text" name="address" required value="{{ old('address') }}" placeholder="{{ __('Calle y número') }}" class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                    <div>
                        <label class="block text-stone-600 text-[10px] uppercase font-bold mb-1">{{ __('Ciudad *') }}</label>
                        <input type="text" name="city" required value="{{ old('city') }}" placeholder="Madrid" class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                    <div>
                        <label class="block text-stone-600 text-[10px] uppercase font-bold mb-1">{{ __('Código Postal *') }}</label>
                        <input type="text" name="postal_code" required value="{{ old('postal_code') }}" placeholder="28014" class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-stone-600 text-[10px] uppercase font-bold mb-1">{{ __('País *') }}</label>
                        <input type="text" name="country" required value="{{ old('country', $currentMarket->name) }}" class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment Method -->
            <div class="editorial-card p-6 space-y-4">
                <h3 class="font-serif font-bold text-stone-900 text-sm border-b border-stone-200 pb-2 flex items-center gap-2">
                    <span class="text-amber-800">3.</span><span>{{ __('Método de Pago') }}</span>
                </h3>

                <div class="space-y-2 text-xs">

                    <label class="flex items-center gap-3 p-3.5 bg-white border border-stone-200 rounded-xl hover:border-stone-400 cursor-pointer">
                        <input type="radio" name="payment_method" value="bank_transfer" checked class="text-stone-900 focus:ring-stone-900">
                        <span class="font-medium text-stone-900 flex-1"><i class="fa-solid fa-building-columns mr-2"></i>{{ __('Transferencia Bancaria Directa') }}</span>
                        <span class="text-[10px] text-stone-500">{{ __('Recepción manual') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 bg-white border border-stone-200 rounded-xl hover:border-stone-400 cursor-pointer">
                        <input type="radio" name="payment_method" value="cod" class="text-stone-900 focus:ring-stone-900">
                        <span class="font-medium text-stone-900 flex-1"><i class="fa-solid fa-money-bill-wave mr-2"></i>{{ __('Pago Contra Reembolso') }}</span>
                        <span class="text-[10px] text-stone-500">Al recibir paquete</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Checkout Summary (1 col) -->
        <div class="editorial-card p-6 space-y-4 h-fit">
            <h3 class="font-serif font-bold text-stone-900 uppercase text-xs pb-2 border-b border-stone-200">
                Resumen ({{ count($items) }} obras)
            </h3>

            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                @foreach($items as $item)
                    @php
                        $p = $item['product'];
                        $trans = $p->translations->where('locale', $currentLocale)->first();
                    @endphp
                    <div class="flex items-center justify-between text-xs">
                        <div class="truncate mr-2">
                            <span class="text-stone-900 font-medium block truncate">{{ $trans?->name }}</span>
                            <span class="text-[10px] text-stone-400">x{{ $item['quantity'] }}</span>
                        </div>
                        <span class="font-serif font-bold text-stone-900 shrink-0">{{ $item['formatted_subtotal'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-stone-200 space-y-2 text-xs">
                <div class="flex justify-between text-stone-600">
                    <span>Subtotal:</span>
                    <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-stone-600">
                    <span>Impuestos (IVA):</span>
                    <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-stone-900 font-serif font-bold text-base pt-2 border-t border-stone-200">
                    <span>{{ __('Total a Pagar:') }}</span>
                    <span>{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3.5 bg-stone-900 hover:bg-stone-800 text-white rounded-xl text-xs font-medium transition-colors">
                    Confirmar Pedido & Proceder al Pago &rarr;
                </button>
            </div>
        </div>

    </form>

</div>
@endsection
