@extends('themes::ia-practica.layouts.app')

@php
    $cartTitle = match($currentLocale) {
        'ca' => 'Cistella de Compra',
        'en' => 'Shopping Basket',
        default => 'Cesta de Compra',
    };
    $cartSubtitle = match($currentLocale) {
        'ca' => 'Revisa els exemplars seleccionats abans de finalitzar la comanda.',
        'en' => 'Review your selected items before completing your order.',
        default => 'Revisa los ejemplares seleccionados antes de finalizar el pedido.',
    };
    $continueShopping = match($currentLocale) {
        'ca' => 'Continuar Descobrint',
        'en' => 'Continue Exploring',
        default => 'Continuar Explorando',
    };
    $emptyTitle = match($currentLocale) {
        'ca' => 'La teva cistella està buida',
        'en' => 'Your basket is empty',
        default => 'Tu cesta está vacía',
    };
    $emptySubtitle = match($currentLocale) {
        'ca' => 'Descobreix les nostres obres i publicacions al catàleg.',
        'en' => 'Discover our curated works and editions in the catalog.',
        default => 'Descubre nuestras obras y publicaciones en el catálogo.',
    };
    $exploreShop = match($currentLocale) {
        'ca' => 'Explorar Catàleg',
        'en' => 'Explore Catalog',
        default => 'Explorar Catálogo',
    };
    $summaryTitle = match($currentLocale) {
        'ca' => 'Resum de la Comanda',
        'en' => 'Order Summary',
        default => 'Resumen del Pedido',
    };
    $subtotalLabel = match($currentLocale) {
        'ca' => 'Subtotal:',
        'en' => 'Subtotal:',
        default => 'Subtotal:',
    };
    $taxLabel = match($currentLocale) {
        'ca' => 'Impostos (IVA):',
        'en' => 'Taxes (VAT):',
        default => 'Impuestos (IVA):',
    };
    $shippingLabel = match($currentLocale) {
        'ca' => 'Enviament:',
        'en' => 'Shipping:',
        default => 'Envío:',
    };
    $freeLabel = match($currentLocale) {
        'ca' => 'Gratuït',
        'en' => 'Free',
        default => 'Gratis',
    };
    $checkoutLabel = match($currentLocale) {
        'ca' => 'Tramitar Comanda',
        'en' => 'Proceed to Checkout',
        default => 'Tramitar Pedido',
    };
    $securePayment = match($currentLocale) {
        'ca' => 'Pagament Segur & Garantit',
        'en' => 'Secure & Encrypted Checkout',
        default => 'Pago Seguro & Garantizado',
    };
@endphp

@section('title', $cartTitle . ' - IA en práctica')

@section('content')
<div class="max-w-5xl mx-auto space-y-8 font-sans" x-data="{
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
        if (!confirm('¿Eliminar de la cesta?')) return;
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

    <div class="flex items-center justify-between border-b border-stone-200 pb-4">
        <div>
            <h1 class="text-3xl font-serif font-bold text-stone-900">
                {{ $cartTitle }}
            </h1>
            <p class="text-xs text-stone-500 mt-1">{{ $cartSubtitle }}</p>
        </div>
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="text-stone-500 hover:text-stone-900 text-xs">
            &larr; {{ $continueShopping }}
        </a>
    </div>

    @if(empty($items))
        <div class="p-16 text-center ia-card space-y-4">
            <span class="text-4xl block text-stone-400"><i class="fa-solid fa-bag-shopping"></i></span>
            <h3 class="text-xl font-serif font-bold text-stone-900">{{ $emptyTitle }}</h3>
            <p class="text-xs text-stone-500 max-w-sm mx-auto">{{ $emptySubtitle }}</p>
            <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="px-5 py-2.5 bg-stone-900 text-white rounded-lg text-xs font-medium inline-block">{{ $exploreShop }} &rarr;</a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Cart Items Table (2 cols) -->
            <div class="lg:col-span-2 ia-card overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-stone-50 border-b border-stone-200 text-stone-500 uppercase text-[10px]">
                        <tr>
                            <th class="p-4">{{ __('Edición') }}</th>
                            <th class="p-4 text-center">{{ __('Cantidad') }}</th>
                            <th class="p-4 text-right">Subtotal</th>
                            <th class="p-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-stone-100">
                        @foreach($items as $item)
                            @php
                                $p = $item['product'];
                                $trans = $p->translations->where('locale', $currentLocale)->first() ?? $p->translations->first();
                            @endphp
                            <tr class="hover:bg-stone-50/60">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        @if($p->featured_image)
                                            <img src="{{ $p->featured_image }}" class="w-12 h-12 object-cover rounded-lg border border-stone-200 bg-stone-50" loading="lazy" decoding="async">
                                        @else
                                            <div class="w-12 h-12 rounded-lg bg-stone-100 flex items-center justify-center text-lg text-stone-400"><i class="fa-solid fa-book-open"></i></div>
                                        @endif
                                        <div>
                                            <div class="font-serif font-bold text-stone-900">{{ $trans?->name }}</div>
                                            <div class="text-[10px] text-stone-400">{{ $item['formatted_unit_price'] }} / u</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center border border-stone-300 rounded-lg bg-white">
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] - 1 }})" class="px-2 py-1 text-stone-500 hover:text-stone-900">-</button>
                                        <span class="px-2 text-stone-900 font-bold">{{ $item['quantity'] }}</span>
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] + 1 }})" class="px-2 py-1 text-stone-500 hover:text-stone-900">+</button>
                                    </div>
                                </td>
                                <td class="p-4 text-right font-serif font-bold text-stone-900">
                                    {{ $item['formatted_subtotal'] }}
                                </td>
                                <td class="p-4 text-right">
                                    <button type="button" @click="removeItem({{ $p->id }})" class="text-stone-400 hover:text-red-600 p-1 text-base font-bold">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Order Summary Card (1 col) -->
            <div class="ia-card p-6 space-y-4 h-fit">
                <h3 class="font-serif font-bold text-stone-900 uppercase text-xs pb-2 border-b border-stone-200">
                    {{ $summaryTitle }}
                </h3>

                <div class="space-y-2 text-xs">
                    <div class="flex justify-between text-stone-600">
                        <span>{{ $subtotalLabel }}</span>
                        <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-stone-600">
                        <span>{{ $taxLabel }}</span>
                        <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-stone-600">
                        <span>{{ $shippingLabel }}</span>
                        <span class="text-emerald-700 font-bold">{{ $freeLabel }}</span>
                    </div>
                    <div class="flex justify-between text-stone-900 font-serif font-bold text-base pt-3 border-t border-stone-200">
                        <span>{{ __('Total:') }}</span>
                        <span>{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="{{ route('shop.checkout', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="w-full py-3.5 bg-stone-900 hover:bg-stone-800 text-white rounded-xl text-center text-xs font-medium block transition-colors">
                        {{ $checkoutLabel }} &rarr;
                    </a>
                </div>

                <div class="text-[10px] text-stone-400 text-center flex items-center justify-center gap-1 font-sans">
                    <span><i class="fa-solid fa-lock mr-1"></i></span><span>{{ $securePayment }}</span>
                </div>
            </div>

        </div>
    @endif

</div>
@endsection
