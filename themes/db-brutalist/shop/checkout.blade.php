@extends('themes::db-brutalist.layouts.app')

@section('title', __('Finalizar Compra - dbCMS Brutalist'))

@section('content')
<div class="max-w-5xl mx-auto space-y-8 font-mono">

    <div class="border-b-3 border-black pb-4">
        <h1 class="text-3xl sm:text-4xl font-black text-black uppercase flex items-center gap-2">
            <span>CHECKOUT RADICAL</span>
        </h1>
        <p class="text-xs text-black font-bold mt-1">{{ __('Completa los datos de despacho y selecciona tu método de pago.') }}</p>
    </div>

    <form method="POST" action="{{ route('shop.checkout.process', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        @csrf

        <!-- Customer & Shipping Form (2 cols) -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Step 1: Customer Contact -->
            <div class="brutalist-card p-6 space-y-4">
                <h3 class="font-black text-black text-xs uppercase border-b-2 border-black pb-2 flex items-center gap-2">
                    <span class="bg-[#FFE600] px-1.5 py-0.5 border border-black">1</span><span>DATOS DEL COMPRADOR</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-black">
                    <div class="sm:col-span-2">
                        <label class="block uppercase text-[10px] mb-1">{{ __('Nombre Completo *') }}</label>
                        <input type="text" name="customer_name" required value="{{ old('customer_name', auth()->user()?->name ?? '') }}" placeholder="Marco V." class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                    <div>
                        <label class="block uppercase text-[10px] mb-1">{{ __('Email *') }}</label>
                        <input type="email" name="customer_email" required value="{{ old('customer_email', auth()->user()?->email ?? '') }}" placeholder="tu@email.com" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                    <div>
                        <label class="block uppercase text-[10px] mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="customer_phone" value="{{ old('customer_phone') }}" placeholder="+00 000 000 000" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                </div>
            </div>

            <!-- Step 2: Shipping Address -->
            <div class="brutalist-card p-6 space-y-4">
                <h3 class="font-black text-black text-xs uppercase border-b-2 border-black pb-2 flex items-center gap-2">
                    <span class="bg-[#FFE600] px-1.5 py-0.5 border border-black">2</span><span>{{ __('DIRECCIÓN DE ENVÍO') }}</span>
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-black">
                    <div class="sm:col-span-2">
                        <label class="block uppercase text-[10px] mb-1">{{ __('Calle y Número *') }}</label>
                        <input type="text" name="address" required value="{{ old('address') }}" placeholder="{{ __('Calle y número') }}" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                    <div>
                        <label class="block uppercase text-[10px] mb-1">{{ __('Ciudad *') }}</label>
                        <input type="text" name="city" required value="{{ old('city') }}" placeholder="Bilbao" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                    <div>
                        <label class="block uppercase text-[10px] mb-1">{{ __('Código Postal *') }}</label>
                        <input type="text" name="postal_code" required value="{{ old('postal_code') }}" placeholder="48001" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block uppercase text-[10px] mb-1">{{ __('País *') }}</label>
                        <input type="text" name="country" required value="{{ old('country', $currentMarket->name) }}" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                </div>
            </div>

            <!-- Step 3: Payment Method -->
            <div class="brutalist-card p-6 space-y-4">
                <h3 class="font-black text-black text-xs uppercase border-b-2 border-black pb-2 flex items-center gap-2">
                    <span class="bg-[#FFE600] px-1.5 py-0.5 border border-black">3</span><span>{{ __('MÉTODO DE PAGO') }}</span>
                </h3>

                <div class="space-y-2 text-xs font-black">

                    <label class="flex items-center gap-3 p-3.5 bg-white border-2 border-black hover:bg-[#FFE600] cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="bank_transfer" checked class="text-black focus:ring-black">
                        <span class="flex-1"><i class="fa-solid fa-building-columns mr-2"></i>{{ __('Transferencia Bancaria Directa') }}</span>
                        <span class="bg-[#FFE600] px-1 border border-black text-[10px]">{{ __('Manual') }}</span>
                    </label>

                    <label class="flex items-center gap-3 p-3.5 bg-white border-2 border-black hover:bg-[#FFE600] cursor-pointer transition-colors">
                        <input type="radio" name="payment_method" value="cod" class="text-black focus:ring-black">
                        <span class="flex-1"><i class="fa-solid fa-money-bill-wave mr-2"></i>{{ __('Pago en Entrega') }}</span>
                        <span class="bg-[#FF5A5F] text-white px-1 border border-black text-[10px]">{{ __('Reembolso') }}</span>
                    </label>
                </div>
            </div>

        </div>

        <!-- Checkout Summary (1 col) -->
        <div class="brutalist-card p-6 space-y-4 h-fit font-mono">
            <h3 class="font-black text-black uppercase text-xs pb-2 border-b-2 border-black">
                RESUMEN ({{ count($items) }} ITEMS)
            </h3>

            <div class="space-y-3 max-h-60 overflow-y-auto pr-1">
                @foreach($items as $item)
                    @php
                        $p = $item['product'];
                        $trans = $p->translations->where('locale', $currentLocale)->first() ?? $p->translations->first();
                    @endphp
                    <div class="flex items-center justify-between text-xs font-bold">
                        <div class="truncate mr-2">
                            <span class="text-black block truncate uppercase">{{ $trans?->name }}</span>
                            <span class="text-[10px] text-black/60">x{{ $item['quantity'] }}</span>
                        </div>
                        <span class="font-black shrink-0">{{ $item['formatted_subtotal'] }}</span>
                    </div>
                @endforeach
            </div>

            <div class="pt-3 border-t-2 border-black space-y-2 text-xs font-black">
                <div class="flex justify-between text-black">
                    <span>Subtotal:</span>
                    <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-black">
                    <span>IVA:</span>
                    <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
                <div class="flex justify-between text-black font-black text-base pt-2 border-t-2 border-black">
                    <span>{{ __('Total a Pagar:') }}</span>
                    <span class="bg-[#FFE600] px-1 border border-black">{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                </div>
            </div>

            <div class="pt-2">
                <button type="submit" class="btn-brutalist w-full py-4 text-xs">
                    Confirmar & Pagar Pedido &rarr;
                </button>
            </div>
        </div>

    </form>

</div>
@endsection
