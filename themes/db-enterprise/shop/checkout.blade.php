@extends('themes::db-enterprise.layouts.app')

@section('title', __('Finalizar Contratación - dbCMS Enterprise'))

@section('content')
<div class="max-w-5xl mx-auto space-y-8">

    <div class="border-b border-slate-800 pb-4">
        <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">
            <span class="text-indigo-400"><i class="fa-solid fa-bolt"></i></span>
            <span>{{ __('Finalizar Contratación & Facturación Enterprise') }}</span>
        </h1>
        <p class="text-xs text-slate-400 mt-1">{{ __('Completa los datos de facturación empresarial y selecciona la modalidad de pago.') }}</p>
    </div>

    <form method="POST" action="{{ route('shop.checkout.process', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf

        <!-- Customer & Shipping Form (2 cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Step 1: Customer Contact -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-slate-800 pb-2 flex items-center gap-2">
                    <span class="text-indigo-400">1.</span><span>{{ __('Razón Social & Contacto Corporativo') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Empresa / Razón Social *') }}</label>
                        <input type="text" name="customer_name" required value="{{ old('customer_name', auth()->user()?->name ?? '') }}" placeholder="Acme Technologies Inc." class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Email de Facturación *') }}</label>
                        <input type="email" name="customer_email" required value="{{ old('customer_email', auth()->user()?->email ?? '') }}" placeholder="tu@email.com" class="w-full bg-slate-950 border border-slate-700 focus:border-indigo-500 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Teléfono Directo') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="+00 000 000 000" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white">
                    </div>
                </div>
            </div>

            <!-- Step 2: Billing Address -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-slate-800 pb-2 flex items-center gap-2">
                    <span class="text-indigo-400">2.</span><span>{{ __('Domicilio Fiscal & Facturación') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Dirección Fiscal Completa *') }}</label>
                        <input type="text" name="address" required value="{{ old('address') }}" placeholder="{{ __('Calle y número') }}" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Ciudad *') }}</label>
                        <input type="text" name="city" required value="{{ old('city') }}" placeholder="Barcelona" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('Código Postal *') }}</label>
                        <input type="text" name="postal_code" required value="{{ old('postal_code') }}" placeholder="08005" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-slate-400 text-[10px] uppercase font-bold mb-1">{{ __('País *') }}</label>
                        <input type="text" name="country" required value="{{ old('country', $currentMarket->name) }}" class="w-full bg-slate-950 border border-slate-700 rounded-lg px-3 py-2 text-white">
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment Method -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4">
                <h3 class="font-bold text-white text-xs uppercase border-b border-slate-800 pb-2 flex items-center gap-2">
                    <span class="text-indigo-400">3.</span><span>{{ __('Método de Liquidación') }}</span>
                </h3>

                <div class="space-y-2 text-xs">

                    <label class="flex items-center gap-3 p-3.5 bg-slate-950 border border-slate-800 rounded-xl hover:border-indigo-500 cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="bank_transfer" checked class="text-indigo-600 focus:ring-indigo-500">
                        <span class="font-semibold text-white flex-1"><i class="fa-solid fa-building-columns mr-2"></i>{{ __('Transferencia SEPA / Facturación a 30 Días') }}</span>
                        <span class="text-[10px] text-slate-400">{{ __('Vía IBAN') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 bg-slate-950 border border-slate-800 rounded-xl hover:border-indigo-500 cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="cod" class="text-indigo-600 focus:ring-indigo-500">
                        <span class="font-semibold text-white flex-1"><i class="fa-solid fa-money-bill-wave mr-2"></i>{{ __('Liquidación al Despliegue') }}</span>
                        <span class="text-[10px] text-slate-400">Bajo acta</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Checkout Summary (1 col) -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 h-fit">
            <h3 class="font-bold text-white uppercase text-xs pb-2 border-b border-slate-800 flex items-center gap-1.5">
                <span class="text-indigo-400"><i class="fa-solid fa-bolt"></i></span>
                <span>Módulos ({{ count($items) }})</span>
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
                        <span class="text-indigo-400 font-bold shrink-0">{{ $item['formatted_subtotal'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t border-slate-800 space-y-2 text-xs">
                <div class="flex justify-between text-slate-400">
                    <span>Base Imponible:</span>
                    <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-slate-400">
                    <span>Impuestos (IVA):</span>
                    <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-white font-bold text-base pt-2 border-t border-slate-800">
                    <span>{{ __('Importe Total:') }}</span>
                    <span class="text-indigo-400">{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-xs font-semibold shadow-lg transition-colors">
                    Confirmar Contratación & Generar Factura &rarr;
                </button>
            </div>
        </div>

    </form>

</div>
@endsection
