@extends('themes::db-brutalist.layouts.app')

@php
    $cartTitle = match($currentLocale) {
        'ca' => 'CARRETÓ DE COMPRA',
        'en' => 'SHOPPING CART',
        default => 'CARRITO DE COMPRAS',
    };
    $cartSubtitle = match($currentLocale) {
        'ca' => 'Revisa els teus articles abans del pagament segur.',
        'en' => 'Review your items before proceeding to secure checkout.',
        default => 'Revisa tus artículos antes del pago seguro.',
    };
    $continueShopping = match($currentLocale) {
        'ca' => 'CONTINUAR COMPRANT',
        'en' => 'CONTINUE SHOPPING',
        default => 'CONTINUAR COMPRANDO',
    };
    $emptyTitle = match($currentLocale) {
        'ca' => 'EL CARRETÓ ESTÀ BUIT',
        'en' => 'YOUR CART IS EMPTY',
        default => 'EL CARRITO ESTÁ VACÍO',
    };
    $exploreShop = match($currentLocale) {
        'ca' => 'EXPLORAR BOTIGA',
        'en' => 'EXPLORE SHOP',
        default => 'EXPLORAR TIENDA',
    };
    $summaryTitle = match($currentLocale) {
        'ca' => 'RESUM DE LA COMANDA',
        'en' => 'ORDER SUMMARY',
        default => 'RESUMEN DEL PEDIDO',
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
        'ca' => 'Enviament:',
        'en' => 'Shipping:',
        default => 'Envío:',
    };
    $freeLabel = match($currentLocale) {
        'ca' => 'GRATIS',
        'en' => 'FREE',
        default => 'GRATIS',
    };
    $checkoutLabel = match($currentLocale) {
        'ca' => 'FINALITZAR COMPRA',
        'en' => 'PROCEED TO CHECKOUT',
        default => 'FINALIZAR COMPRA',
    };
@endphp

@section('title', $cartTitle . ' - dbCMS Brutalist')

@section('content')
<div class="max-w-5xl mx-auto space-y-8 font-mono" x-data="{
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
        if (!confirm('¿Eliminar producto?')) return;
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

    <div class="flex items-center justify-between border-b-3 border-black pb-4">
        <div>
            <h1 class="text-3xl sm:text-4xl font-black text-black uppercase">
                {{ $cartTitle }}
            </h1>
            <p class="text-xs text-black font-bold mt-1">{{ $cartSubtitle }}</p>
        </div>
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="btn-brutalist px-3 py-1 text-xs">
            &larr; {{ $continueShopping }}
        </a>
    </div>

    @if(empty($items))
        <div class="p-16 text-center brutalist-card space-y-4">
            <span class="text-5xl block text-black"><i class="fa-solid fa-cart-shopping"></i></span>
            <h3 class="text-xl font-black uppercase text-black">{{ $emptyTitle }}</h3>
            <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="btn-brutalist text-xs inline-block px-5 py-2.5">{{ $exploreShop }} &rarr;</a>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Cart Items Table (2 cols) -->
            <div class="lg:col-span-2 brutalist-card overflow-hidden">
                <table class="w-full text-left text-xs">
                    <thead class="bg-[#FFE600] border-b-2 border-black text-black font-black uppercase text-[10px]">
                        <tr>
                            <th class="p-4">{{ __('Producto') }}</th>
                            <th class="p-4 text-center">{{ __('Cantidad') }}</th>
                            <th class="p-4 text-right">Subtotal</th>
                            <th class="p-4 text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y-2 divide-black">
                        @foreach($items as $item)
                            @php
                                $p = $item['product'];
                                $trans = $p->translations->where('locale', $currentLocale)->first() ?? $p->translations->first();
                            @endphp
                            <tr class="hover:bg-[#00F0FF]/10">
                                <td class="p-4">
                                    <div class="flex items-center gap-3">
                                        @if($p->featured_image)
                                            <img src="{{ $p->featured_image }}" class="w-12 h-12 object-cover border-2 border-black bg-black" loading="lazy" decoding="async">
                                        @else
                                            <div class="w-12 h-12 border-2 border-black bg-white flex items-center justify-center font-black"><i class="fa-solid fa-box"></i></div>
                                        @endif
                                        <div>
                                            <div class="font-black text-black uppercase">{{ $trans?->name }}</div>
                                            <div class="text-[10px] text-black font-bold">{{ $item['formatted_unit_price'] }} / u</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="p-4 text-center">
                                    <div class="inline-flex items-center border-2 border-black bg-white">
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] - 1 }})" class="px-2.5 py-1 text-black font-black hover:bg-[#FFE600]">-</button>
                                        <span class="px-2 text-black font-black">{{ $item['quantity'] }}</span>
                                        <button type="button" @click="updateQty({{ $p->id }}, {{ $item['quantity'] + 1 }})" class="px-2.5 py-1 text-black font-black hover:bg-[#FFE600]">+</button>
                                    </div>
                                </td>
                                <td class="p-4 text-right font-black text-black text-sm">
                                    {{ $item['formatted_subtotal'] }}
                                </td>
                                <td class="p-4 text-right">
                                    <button type="button" @click="removeItem({{ $p->id }})" class="hover:bg-[#FF5A5F] px-2 py-0.5 border border-black font-black text-base">&times;</button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <!-- Order Summary Card (1 col) -->
            <div class="brutalist-card p-6 space-y-4 h-fit">
                <h3 class="font-black text-black uppercase text-xs pb-2 border-b-2 border-black">
                    {{ $summaryTitle }}
                </h3>

                <div class="space-y-2 text-xs font-bold">
                    <div class="flex justify-between text-black">
                        <span>{{ $subtotalLabel }}</span>
                        <span>{{ number_format($subtotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-black">
                        <span>{{ $taxLabel }}</span>
                        <span>{{ number_format($taxTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                    <div class="flex justify-between text-black">
                        <span>{{ $shippingLabel }}</span>
                        <span class="bg-[#00F0FF] px-1 border border-black">{{ $freeLabel }}</span>
                    </div>
                    <div class="flex justify-between text-black font-black text-base pt-3 border-t-2 border-black">
                        <span>{{ __('Total:') }}</span>
                        <span>{{ number_format($grandTotal, 2) }} {{ $currentMarket->currency }}</span>
                    </div>
                </div>

                <div class="pt-2">
                    <a href="{{ route('shop.checkout', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="btn-brutalist w-full py-4 text-center text-xs block">
                        {{ $checkoutLabel }} &rarr;
                    </a>
                </div>
            </div>

        </div>
    @endif

</div>
@endsection
