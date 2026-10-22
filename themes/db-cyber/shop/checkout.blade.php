@extends('themes::db-cyber.layouts.app')

@section('title', __('Finalizar Compra - dbCMS'))

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8 font-mono">

    <div class="border-b border-gray-800 pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">
            <span class="text-[#00FF99]">&gt;</span>
            <span>Finalizar Compra (Checkout)</span>
        </h1>
        <p class="text-xs text-gray-400 mt-1">{{ __('Completa los datos de envío y selecciona tu método de pago preferido.') }}</p>
    </div>

    <form method="POST" action="{{ route('shop.checkout.process', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf

        <!-- Customer & Shipping Form (2 cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Step 1: Customer Contact -->
            <div class="cyber-card p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-gray-800 pb-2 flex items-center gap-2">
                    <span class="text-[#00FF99]">1.</span><span>Datos del Comprador</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('Nombre Completo / Empresa *') }}</label>
                        <input type="text" name="customer_name" required value="{{ old('customer_name', auth()->user()?->name ?? '') }}" placeholder="Alex Mercer" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('Email de Contacto *') }}</label>
                        <input type="email" name="customer_email" required value="{{ old('customer_email', auth()->user()?->email ?? '') }}" placeholder="tu@email.com" class="w-full bg-[#1A1A1A] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="+00 000 000 000" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                    </div>
                </div>
            </div>

            <!-- Step 2: Shipping Address -->
            <div class="cyber-card p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-gray-800 pb-2 flex items-center gap-2">
                    <span class="text-[#00FF99]">2.</span><span>{{ __('Dirección de Envío') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('Dirección / Calle y Número *') }}</label>
                        <input type="text" name="address" required value="{{ old('address') }}" placeholder="{{ __('Calle y número') }}" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('Ciudad *') }}</label>
                        <input type="text" name="city" required value="{{ old('city') }}" placeholder="Madrid" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('Código Postal *') }}</label>
                        <input type="text" name="postal_code" required value="{{ old('postal_code') }}" placeholder="28001" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-gray-400 text-[10px] uppercase font-bold mb-1">{{ __('País *') }}</label>
                        <input type="text" name="country" required value="{{ old('country', $currentMarket->name) }}" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment Method -->
            <div class="cyber-card p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-gray-800 pb-2 flex items-center gap-2">
                    <span class="text-[#00FF99]">3.</span><span>{{ __('Método de Pago') }}</span>
                </h3>

                <div class="space-y-2 text-xs">

                    <label class="flex items-center gap-3 p-3 bg-[#1A1A1A] border border-gray-800 rounded-lg hover:border-[#00FF99] cursor-pointer">
                        <input type="radio" name="payment_method" value="bank_transfer" checked class="text-[#00FF99] focus:ring-[#00FF99]">
                        <span class="font-bold text-white flex-1"><i class="fa-solid fa-building-columns mr-2"></i>{{ __('Transferencia Bancaria Directa') }}</span>
                        <span class="text-[10px] text-gray-400">{{ __('Manual') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3 bg-[#1A1A1A] border border-gray-800 rounded-lg hover:border-[#00FF99] cursor-pointer">
                        <input type="radio" name="payment_method" value="cod" class="text-[#00FF99] focus:ring-[#00FF99]">
                        <span class="font-bold text-white flex-1"><i class="fa-solid fa-money-bill-wave mr-2"></i>{{ __('Pago Contra Reembolso') }}</span>
                        <span class="text-[10px] text-gray-400">Al recibir</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Checkout Summary (1 col) -->
        <div class="cyber-card p-6 space-y-4 h-fit">
            <h3 class="font-bold text-white uppercase text-xs pb-2 border-b border-gray-800">
                &gt; {{ __('Artículos') }} ({{ count($items) }})
            </h3>

            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                @foreach($items as $item)
                    @php
                        $p = $item['product'];
                        $trans = $p->translations->where('locale', $currentLocale)->first();
                    @endphp
                    <div class="flex items-center justify-between text-xs">
                        <div class="truncate mr-2">
                            <span class="text-white font-bold block truncate">{{ $trans?->name }}</span>
                            <span class="text-[10px] text-gray-400">x{{ $item['quantity'] }}</span>
                        </div>
                        <span class="text-[#00FF99] font-bold shrink-0">{{ $item['formatted_subtotal'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-gray-800 space-y-2 text-xs">
                <div class="flex justify-between text-gray-400">
                    <span>Subtotal:</span>
                    <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-gray-400">
                    <span>Impuestos (IVA):</span>
                    <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-white font-bold text-sm pt-2 border-t border-gray-800">
                    <span>{{ __('Total a Pagar:') }}</span>
                    <span class="text-[#00FF99]">{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-neon w-full py-3.5 text-xs">
                    Confirmar & Pagar Pedido &rarr;
                </button>
            </div>
        </div>

    </form>

</div>
@endsection
