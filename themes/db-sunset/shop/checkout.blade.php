@extends('themes::db-sunset.layouts.app')

@section('title', __('Finalizar Compra - dbCMS Sunset'))

@section('content')
<div class="max-w-5xl mx-auto space-y-8">

    <div class="border-b border-white/10 pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">
            <span class="text-rose-400"><i class="fa-solid fa-sun"></i></span>
            <span>Finalizar Compra</span>
        </h1>
        <p class="text-xs text-slate-400 mt-1">{{ __('Completa los datos de entrega y selecciona tu método de pago.') }}</p>
    </div>

    <form method="POST" action="{{ route('shop.checkout.process', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf

        <!-- Customer & Shipping Form (2 cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Step 1: Customer Contact -->
            <div class="sunset-card p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-white/10 pb-2 flex items-center gap-2">
                    <span class="text-rose-400">1.</span><span>{{ __('Datos de Contacto') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Nombre Completo *') }}</label>
                        <input type="text" name="customer_name" required value="{{ old('customer_name', auth()->user()?->name ?? '') }}" placeholder="{{ __('Nombre completo') }}" class="w-full bg-white/5 border border-white/10 focus:border-rose-500 rounded-xl px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Email *') }}</label>
                        <input type="email" name="customer_email" required value="{{ old('customer_email', auth()->user()?->email ?? '') }}" placeholder="tu@email.com" class="w-full bg-white/5 border border-white/10 focus:border-rose-500 rounded-xl px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="+00 000 000 000" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                </div>
            </div>

            <!-- Step 2: Shipping Address -->
            <div class="sunset-card p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-white/10 pb-2 flex items-center gap-2">
                    <span class="text-rose-400">2.</span><span>{{ __('Dirección de Envío') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Dirección *') }}</label>
                        <input type="text" name="address" required value="{{ old('address') }}" placeholder="{{ __('Calle y número') }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Ciudad *') }}</label>
                        <input type="text" name="city" required value="{{ old('city') }}" placeholder="Valencia" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Código Postal *') }}</label>
                        <input type="text" name="postal_code" required value="{{ old('postal_code') }}" placeholder="46001" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('País *') }}</label>
                        <input type="text" name="country" required value="{{ old('country', $currentMarket->name) }}" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment Method -->
            <div class="sunset-card p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-white/10 pb-2 flex items-center gap-2">
                    <span class="text-rose-400">3.</span><span>{{ __('Método de Pago') }}</span>
                </h3>

                <div class="space-y-2 text-xs">

                    <label class="flex items-center gap-3 p-3.5 bg-white/5 border border-white/10 rounded-xl hover:border-rose-500 cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="bank_transfer" checked class="text-rose-500 focus:ring-rose-500">
                        <span class="font-semibold text-white flex-1"><i class="fa-solid fa-building-columns mr-2"></i>{{ __('Transferencia Bancaria Directa') }}</span>
                        <span class="text-[10px] text-slate-400">{{ __('Manual') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 bg-white/5 border border-white/10 rounded-xl hover:border-rose-500 cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="cod" class="text-rose-500 focus:ring-rose-500">
                        <span class="font-semibold text-white flex-1"><i class="fa-solid fa-money-bill-wave mr-2"></i>{{ __('Pago Contra Reembolso') }}</span>
                        <span class="text-[10px] text-slate-400">{{ __('En entrega') }}</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Checkout Summary (1 col) -->
        <div class="sunset-card p-6 space-y-4 h-fit">
            <h3 class="font-bold text-white uppercase text-xs pb-2 border-b border-white/10 flex items-center gap-1.5">
                <span class="text-rose-400"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                <span>{{ __('Artículos') }} ({{ count($items) }})</span>
            </h3>

            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                @foreach($items as $item)
                    @php
                        $p = $item['product'];
                        $trans = $p->translations->where('locale', $currentLocale)->first() ?? $p->translations->first();
                    @endphp
                    <div class="flex items-center justify-between text-xs">
                        <div class="truncate mr-2">
                            <span class="text-white font-semibold block truncate">{{ $trans?->name }}</span>
                            <span class="text-[10px] text-slate-400">x{{ $item['quantity'] }}</span>
                        </div>
                        <span class="text-rose-400 font-bold shrink-0">{{ $item['formatted_subtotal'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-white/10 space-y-2 text-xs">
                <div class="flex justify-between text-slate-400">
                    <span>Subtotal:</span>
                    <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>Impuestos:</span>
                    <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-white font-bold text-base pt-2 border-t border-white/10">
                    <span>{{ __('Total a Pagar:') }}</span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-rose-400 to-amber-300">{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-sunset w-full py-3.5 text-xs">
                    Confirmar Compra &rarr;
                </button>
            </div>
        </div>

    </form>

</div>
@endsection
