@extends('themes.db-minimal.layouts.app')

@php
    $cartTitle = match($currentLocale) {
        'ca' => 'Cistella',
        'en' => 'Basket',
        default => 'Cesta',
    };
    $cartSubtitle = match($currentLocale) {
        'ca' => 'Revisió d\'articles abans de procedir.',
        'en' => 'Review items before proceeding.',
        default => 'Revisión de artículos antes de proceder.',
    };
    $continueShopping = match($currentLocale) {
        'ca' => 'Continuar',
        'en' => 'Continue',
        default => 'Continuar',
    };
    $emptyTitle = match($currentLocale) {
        'ca' => 'Cistella buida',
        'en' => 'Empty basket',
        default => 'Cesta vacía',
    };
    $exploreShop = match($currentLocale) {
        'ca' => 'Explorar',
        'en' => 'Explore',
        default => 'Explorar',
    };
    $summaryTitle = match($currentLocale) {
        'ca' => 'Resum',
        'en' => 'Summary',
        default => 'Resumen',
    };
    $subtotalLabel = match($currentLocale) {
        'ca' => 'Subtotal:',
        'en' => 'Subtotal:',
        default => 'Subtotal:',
    };
    $taxLabel = match($currentLocale) {
        'ca' => 'IVA:',
        'en' => 'VAT:',
        default => 'IVA:',
    };
    $shippingLabel = match($currentLocale) {
        'ca' => 'Enviament:',
        'en' => 'Shipping:',
        default => 'Envío:',
    };
    $freeLabel = match($currentLocale) {
        'ca' => 'Gratis',
        'en' => 'Free',
        default => 'Gratis',
    };
    $checkoutLabel = match($currentLocale) {
        'ca' => 'Finalitzar Compra',
        'en' => 'Checkout',
        default => 'Finalizar Compra',
    };
@endphp

@section('title', $cartTitle . ' - dbCMS Minimal')

@section('content')
<div class="max-w-4xl mx-auto space-y-8 font-sans" x-data="{
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
        if (!confirm('¿Eliminar artículo?')) return;
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

    <div class="flex items-center justify-between border-b border-neutral-200/80 pb-4">
        <div>
            <h1 class="text-3xl font-light text-neutral-900 tracking-tight">
                {{ $cartTitle }}
            </h1>
            <p class="text-xs text-neutral-400 mt-0.5 font-mono">{{ $cartSubtitle }}</p>
        </div>
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="text-neutral-500 hover:text-neutral-900 text-xs font-mono uppercase tracking-wider">
            &larr; {{ $continueShopping }}
        </a>
    </div>

    @if(empty($items))
        <div class="p-20 text-center minimal-card space-y-4">
            <span class="text-4xl block">◻</span>
            <h3 class="text-lg font-light text-neutral-900">{{ $emptyTitle }}</h3>
            <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="px-5 py-2 bg-neutral-900 text-white rounded text-xs font-mono uppercase tracking-widest inline-block">{{ $exploreShop }} &rarr;</a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Items Table (2 cols) -->
            <div class="lg:col-span-2 minimal-card overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-neutral-50 border-b border-neutral-200 text-neutral-400 font-mono uppercase text-[9px] tracking-wider">
                        <tr>
                            <th class="p-4">{{ __('Artículo') }}</th>
                            <th class="p-4 text-center">{{ __('Cantidad') }}</th>
                            <th class="p-4 text-right">Subtotal</th>
                            <th class="p-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100 font-mono">
                        @foreach($items as $item)
                            @php
                                $p = $item['product'];
                                $trans = $p->translations->where('locale', $currentLocale)->first() ?? $p->translations->first();
                            @endphp
                            <tr class="hover:bg-neutral-50/50">
                                <td class="p-4 font-sans">
                                    <div class="flex items-center gap-3">
                                        @if($p->featured_image)
                                            <img src="{{ $p->featured_image }}" class="w-12 h-12 object-cover rounded border border-neutral-200 bg-neutral-50" loading="lazy" decoding="async">
                                        @else
                                            <div class="w-12 h-12 rounded bg-neutral-100 flex items-center justify-center text-sm">◻</div>
                                        @endif
                                        <div>
                                            <div class="font-medium text-neutral-900">{{ $trans?->name }}</div>
                                            <div class="text-[10px] text-neutral-400 font-mono">{{ $item['formatted_unit_price'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center border border-neutral-200 rounded">
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] - 1 }})" class="px-2 py-1 text-neutral-400 hover:text-neutral-900">-</button>
                                        <span class="px-2 text-neutral-900 font-medium">{{ $item['quantity'] }}</span>
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] + 1 }})" class="px-2 py-1 text-neutral-400 hover:text-neutral-900">+</button>
                                    </div>
                                </td>
                                <td class="p-4 text-right font-medium text-neutral-900">
                                    {{ $item['formatted_subtotal'] }}
                                </td>
                                <td class="p-4 text-right">
                                    <button type="button" @click="removeItem({{ $p->id }})" class="text-neutral-400 hover:text-neutral-900 p-1">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Order Summary (1 col) -->
            <div class="minimal-card p-6 space-y-4 h-fit font-mono text-xs">
                <h3 class="font-medium text-neutral-900 uppercase tracking-wider text-[11px] pb-2 border-b border-neutral-100">
                    {{ $summaryTitle }}
                </h3>

                <div class="space-y-2">
                    <div class="flex justify-between text-neutral-500">
                        <span>{{ $subtotalLabel }}</span>
                        <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-neutral-500">
                        <span>{{ $taxLabel }}</span>
                        <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-neutral-500">
                        <span>{{ $shippingLabel }}</span>
                        <span>{{ $freeLabel }}</span>
                    </div>
                    <div class="flex justify-between text-neutral-900 font-semibold text-sm pt-3 border-t border-neutral-100">
                        <span>{{ __('Total:') }}</span>
                        <span>{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="{{ route('shop.checkout', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="w-full py-3 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-center text-xs uppercase tracking-widest block transition-colors">
                        {{ $checkoutLabel }} &rarr;
                    </a>
                </div>
            </div>

        </div>
    @endif

</div>
@endsection
