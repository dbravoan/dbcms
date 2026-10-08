@extends('themes.db-enterprise.layouts.app')

@php
    $cartTitle = match($currentLocale) {
        'ca' => 'Comanda de Llicències & Subscripcions',
        'en' => 'Enterprise Order & Subscriptions',
        default => 'Pedido de Licencias & Suscripciones',
    };
    $cartSubtitle = match($currentLocale) {
        'ca' => 'Revisa les llicències i mòduls seleccionats abans de procedir a la facturació.',
        'en' => 'Review your selected enterprise modules and licenses before proceeding to billing.',
        default => 'Revisa las licencias y módulos seleccionados antes de proceder a la facturación.',
    };
    $continueShopping = match($currentLocale) {
        'ca' => 'Continuar al Catàleg',
        'en' => 'Continue to Solutions',
        default => 'Continuar al Catálogo',
    };
    $emptyTitle = match($currentLocale) {
        'ca' => 'No hi ha llicències a la comanda',
        'en' => 'Your enterprise cart is empty',
        default => 'No hay licencias en el pedido',
    };
    $emptySubtitle = match($currentLocale) {
        'ca' => 'Explora les nostres solucions de programari i mòduls empresarials.',
        'en' => 'Explore our enterprise software suites and infrastructure modules.',
        default => 'Explora nuestras soluciones de software y módulos empresariales.',
    };
    $exploreShop = match($currentLocale) {
        'ca' => 'Explorar Solucions',
        'en' => 'Explore Solutions',
        default => 'Explorar Soluciones',
    };
    $summaryTitle = match($currentLocale) {
        'ca' => 'Resum de Facturació',
        'en' => 'Billing Summary',
        default => 'Resumen de Facturación',
    };
    $subtotalLabel = match($currentLocale) {
        'ca' => 'Base Imposable:',
        'en' => 'Taxable Subtotal:',
        default => 'Base Imponible:',
    };
    $taxLabel = match($currentLocale) {
        'ca' => 'Impostos (IVA):',
        'en' => 'Taxes (VAT):',
        default => 'Impuestos (IVA):',
    };
    $shippingLabel = match($currentLocale) {
        'ca' => 'Desplegament / Alta:',
        'en' => 'Deployment / Provisioning:',
        default => 'Despliegue / Provisión:',
    };
    $freeLabel = match($currentLocale) {
        'ca' => 'Inclòs',
        'en' => 'Included',
        default => 'Incluido',
    };
    $checkoutLabel = match($currentLocale) {
        'ca' => 'Tramitar Facturació Enterprise',
        'en' => 'Proceed to Enterprise Checkout',
        default => 'Tramitar Facturación Enterprise',
    };
    $securePayment = match($currentLocale) {
        'ca' => 'Connexió Xifrada & Compliment RGPD',
        'en' => 'Encrypted Gateway & GDPR Compliant',
        default => 'Conexión Cifrada & Cumplimiento RGPD',
    };
@endphp

@section('title', $cartTitle . ' - dbCMS Enterprise')

@section('content')
<div class="max-w-5xl mx-auto space-y-8" x-data="{
    async updateQty(productId, qty) {
        await fetch('/api/cart/update', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ product_id: productId, quantity: qty })
        });
        window.location.reload();
    },
    async removeItem(productId) {
        if (!confirm('¿Eliminar solución del pedido?')) return;
        await fetch('/api/cart/remove', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ product_id: productId })
        });
        window.location.reload();
    }
}">

    <div class="flex items-center justify-between border-b border-slate-800 pb-4">
        <div>
            <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center gap-2">
                <span class="text-indigo-400"><i class="fa-solid fa-bolt"></i></span>
                <span>{{ $cartTitle }}</span>
            </h1>
            <p class="text-xs text-slate-400 mt-1">{{ $cartSubtitle }}</p>
        </div>
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="text-slate-400 hover:text-indigo-400 text-xs">
            &larr; {{ $continueShopping }}
        </a>
    </div>

    @if(empty($items))
        <div class="p-16 text-center bg-slate-900 border border-slate-800 rounded-2xl space-y-4">
            <span class="text-4xl block text-slate-600"><i class="fa-solid fa-building"></i></span>
            <h3 class="text-lg font-bold text-white">{{ $emptyTitle }}</h3>
            <p class="text-xs text-slate-400 max-w-sm mx-auto">{{ $emptySubtitle }}</p>
            <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold inline-block transition-colors shadow">{{ $exploreShop }} &rarr;</a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Cart Items Table (2 cols) -->
            <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-slate-950 border-b border-slate-800 text-slate-400 uppercase text-[10px]">
                        <tr>
                            <th class="p-4">{{ __('Solución / Módulo') }}</th>
                            <th class="p-4 text-center">Unidades</th>
                            <th class="p-4 text-right">Subtotal</th>
                            <th class="p-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        @foreach($items as $item)
                            @php
                                $p = $item['product'];
                                $trans = $p->translations->where('locale', $currentLocale)->first() ?? $p->translations->first();
                            @endphp
                            <tr class="hover:bg-slate-800/30">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        @if($p->featured_image)
                                            <img src="{{ $p->featured_image }}" class="w-12 h-12 object-cover rounded-lg border border-slate-700 bg-slate-950" loading="lazy" decoding="async">
                                        @else
                                            <div class="w-12 h-12 rounded-lg bg-slate-950 border border-slate-800 flex items-center justify-center text-lg text-slate-500"><i class="fa-solid fa-desktop"></i></div>
                                        @endif
                                        <div>
                                            <div class="font-bold text-white">{{ $trans?->name }}</div>
                                            <div class="text-[10px] text-indigo-400">{{ $item['formatted_unit_price'] }} / lic.</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center border border-slate-700 rounded-lg bg-slate-950">
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] - 1 }})" class="px-2.5 py-1 text-slate-400 hover:text-white">-</button>
                                        <span class="px-2 text-white font-bold">{{ $item['quantity'] }}</span>
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] + 1 }})" class="px-2.5 py-1 text-slate-400 hover:text-white">+</button>
                                    </div>
                                </td>
                                <td class="p-4 text-right font-bold text-white">
                                    {{ $item['formatted_subtotal'] }}
                                </td>
                                <td class="p-4 text-right">
                                    <button type="button" @click="removeItem({{ $p->id }})" class="text-slate-500 hover:text-red-400 p-1 text-base">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Order Summary Card (1 col) -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 space-y-4 h-fit">
                <h3 class="font-bold text-white uppercase text-xs pb-2 border-b border-slate-800 flex items-center gap-1.5">
                    <span class="text-indigo-400"><i class="fa-solid fa-bolt"></i></span>
                    <span>{{ $summaryTitle }}</span>
                </h3>

                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>{{ $subtotalLabel }}</span>
                        <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>{{ $taxLabel }}</span>
                        <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>{{ $shippingLabel }}</span>
                        <span class="text-emerald-400 font-semibold">{{ $freeLabel }}</span>
                    </div>
                    <div class="flex justify-between text-white font-bold text-base pt-3 border-t border-slate-800">
                        <span>{{ __('Total:') }}</span>
                        <span class="text-indigo-400">{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="{{ route('shop.checkout', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-500 text-white rounded-xl text-center text-xs font-semibold block transition-colors shadow-lg">
                        {{ $checkoutLabel }} &rarr;
                    </a>
                </div>

                <div class="text-[10px] text-slate-500 text-center flex items-center justify-center gap-1">
                    <span><i class="fa-solid fa-lock mr-1"></i></span><span>{{ $securePayment }}</span>
                </div>
            </div>

        </div>
    @endif

</div>
@endsection
