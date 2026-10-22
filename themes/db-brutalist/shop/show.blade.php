@extends('themes::db-brutalist.layouts.app')

@section('title', ($translation->meta_title ?? $translation->name) . ' - dbCMS Brutalist')
@section('meta_description', $translation->meta_description ?? $translation->summary ?? '')

@section('content')
<div class="max-w-7xl mx-auto space-y-16 font-mono" x-data="{
    activeImage: @js($product->featured_image ?? ''),
    qty: 1,
    adding: false,
    addedMessage: '',
    showInquiryModal: false,

    async addToCart(productId) {
        this.adding = true;
        try {
            const res = await fetch('/api/cart/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || ''
                },
                body: JSON.stringify({ product_id: productId, quantity: this.qty })
            });
            const data = await res.json();
            if (data.success) {
                this.addedMessage = '¡AÑADIDO AL CARRITO!';
                setTimeout(() => window.location.href = '{{ route('shop.cart', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}', 800);
            } else {
                alert(data.message || 'Error al añadir.');
            }
        } catch(e) {
            console.error(e);
        } finally {
            this.adding = false;
        }
    }
}">

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-black font-black uppercase">
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="hover:bg-[#FFE600] px-1">{{ __(__('Catálogo')) }}</a>
        <span>/</span>
        @if($product->category)
            <span>{{ $product->category->localizedName() }}</span>
            <span>/</span>
        @endif
        <span class="bg-[#00F0FF] px-1 border border-black">{{ $translation->name }}</span>
    </nav>

    <!-- Product Overview Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

        <!-- Left: Image Gallery -->
        <div class="space-y-4">
            <div class="brutalist-card h-96 sm:h-[480px] bg-black overflow-hidden flex items-center justify-center p-4">
                <template x-if="activeImage">
                    <img :src="activeImage" alt="{{ $translation->name }}" class="max-h-full max-w-full object-contain" loading="lazy" decoding="async">
                </template>
                <template x-if="!activeImage">
                    <div class="text-[#FFE600] text-6xl font-black">[ PROD ]</div>
                </template>
            </div>

            <!-- Thumbnail Switcher -->
            @if(!empty($product->gallery_images) && count($product->gallery_images) > 0)
                <div class="flex items-center gap-3 overflow-x-auto pb-2">
                    @if($product->featured_image)
                        <button type="button" @click="activeImage = @js($product->featured_image)" class="w-16 h-16 rounded overflow-hidden border-2 bg-black shrink-0 transition-all" :class="activeImage === @js($product->featured_image) ? 'border-[#FFE600] shadow-[3px_3px_0px_0px_#000]' : 'border-black opacity-60 hover:opacity-100'">
                            <img src="{{ $product->featured_image }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endif
                    @foreach($product->gallery_images as $gImg)
                        <button type="button" @click="activeImage = @js($gImg)" class="w-16 h-16 rounded overflow-hidden border-2 bg-black shrink-0 transition-all" :class="activeImage === @js($gImg) ? 'border-[#FFE600] shadow-[3px_3px_0px_0px_#000]' : 'border-black opacity-60 hover:opacity-100'">
                            <img src="{{ $gImg }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right: Specs, Pricing & Purchase / Quote Action Box -->
        <div class="brutalist-card p-6 sm:p-8 space-y-6">
            <div class="space-y-2 border-b-2 border-black pb-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase bg-[#FFE600] px-2 py-0.5 border border-black">
                        {{ $product->category?->localizedName() ?? 'Hardware' }}
                    </span>
                    <span class="text-xs text-black font-black">
                        SKU: {{ $product->sku }}
                    </span>
                </div>
                <h1 class="text-2xl sm:text-4xl font-black text-black leading-tight uppercase">
                    {{ $translation->name }}
                </h1>
            </div>

            <!-- Price & Availability -->
            <div class="flex items-baseline justify-between border-b-2 border-black pb-5">
                <div>
                    @if(!empty($showPrices))
                        <span class="text-3xl sm:text-4xl font-black text-black">
                            {{ $product->getFormattedPrice($currentMarket) }}
                        </span>
                        @if($product->compare_price)
                            <span class="text-sm text-black/50 line-through ml-2">
                                {{ number_format($product->compare_price, 2) }} €
                            </span>
                        @endif
                    @else
                        <span class="text-xl font-black text-black uppercase">
                            {{ $currentLocale === 'ca' ? 'Preu a consultar' : ($currentLocale === 'en' ? 'Pricing upon request' : 'Precio a consultar') }}
                        </span>
                    @endif
                </div>

                <div>
                    @if($product->isInStock())
                        <span class="px-3 py-1 bg-[#00F0FF] text-black border-2 border-black font-black text-xs uppercase shadow-[2px_2px_0px_0px_#000]">
                            <i class="fa-solid fa-check mr-1"></i> EN STOCK
                        </span>
                    @else
                        <span class="px-3 py-1 bg-[#FF5A5F] text-white border-2 border-black font-black text-xs uppercase shadow-[2px_2px_0px_0px_#000]">
                            <i class="fa-solid fa-xmark mr-1"></i> AGOTADO
                        </span>
                    @endif
                </div>
            </div>

            <!-- Short Summary -->
            <p class="text-black font-semibold text-sm leading-relaxed bg-[#FFE600]/20 p-3 border-2 border-black">
                {{ $translation->summary ?? 'Diseño técnico neo-brutalista de alto rendimiento y arquitectura robusta.' }}
            </p>

            <!-- Actions: Cart vs Quote -->
            @if($isShop)
                <!-- Mode: Full E-Commerce Shop -->
                <div class="space-y-4 pt-2">
                    <div class="flex items-center gap-4 text-xs font-black">
                        <label class="uppercase">{{ __('CANTIDAD:') }}</label>
                        <div class="flex items-center border-2 border-black bg-white shadow-[2px_2px_0px_0px_#000]">
                            <button type="button" @click="if(qty > 1) qty--" class="px-3 py-1.5 text-black hover:bg-[#FFE600] font-black text-sm">-</button>
                            <input type="number" x-model="qty" min="1" max="99" class="w-12 text-center bg-transparent border-0 text-black font-black text-xs focus:ring-0">
                            <button type="button" @click="qty++" class="px-3 py-1.5 text-black hover:bg-[#FFE600] font-black text-sm">+</button>
                        </div>
                    </div>

                    <button type="button"
                            @click="addToCart({{ $product->id }})"
                            :disabled="adding || !{{ $product->isInStock() ? 'true' : 'false' }}"
                            class="btn-brutalist w-full py-4 text-sm flex items-center justify-center gap-2 disabled:opacity-40">
                        <span><i class="fa-solid fa-cart-shopping"></i></span>
                        <span x-text="adding ? 'PROCESANDO...' : 'AÑADIR AL CARRITO'"></span>
                    </button>

                    <div x-show="addedMessage" x-cloak class="p-3 bg-[#00F0FF] border-2 border-black text-black text-xs text-center font-black shadow-[3px_3px_0px_0px_#000]">
                        <span x-text="addedMessage"></span>
                    </div>
                </div>
            @else
                <!-- Mode: Catalog Only (Quote / Inquiry) -->
                <div class="space-y-4 pt-2">
                    <button type="button"
                            @click="showInquiryModal = true"
                            class="btn-brutalist w-full py-4 text-sm flex items-center justify-center gap-2">
                        <span><i class="fa-solid fa-bolt"></i></span>
                        <span>SOLICITAR PRESUPUESTO &rarr;</span>
                    </button>
                    <p class="text-[11px] text-black font-bold text-center">
                        Respuesta garantizada en menos de 24 horas.
                    </p>
                </div>
            @endif

            <!-- Technical Specifications -->
            @if(!empty($product->specs_json))
                <div class="pt-4 border-t-2 border-black space-y-2">
                    <span class="text-xs font-black uppercase">ESPECIFICACIONES:</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        @foreach($product->specs_json as $k => $v)
                            <div class="p-2 bg-white border-2 border-black shadow-[2px_2px_0px_0px_#000]">
                                <span class="text-black/60 block text-[9px] uppercase font-black">{{ $k }}</span>
                                <span class="text-black font-black">{{ is_array($v) ? implode(', ', $v) : $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

    </div>

    <!-- Modular Sections & Detailed Description -->
    @if(isset($renderedSections) && count($renderedSections) > 0)
        <section class="space-y-12">
            <h2 class="text-2xl font-black text-black uppercase border-b-3 border-black pb-3">{{ __('DETALLES DEL PRODUCTO') }}</h2>
            @foreach($renderedSections as $sectionHtml)
                <div>
                    {!! $sectionHtml !!}
                </div>
            @endforeach
        </section>
    @elseif(!empty($translation->description))
        <section class="brutalist-card p-8 sm:p-10 text-black font-medium text-sm leading-relaxed max-w-none">
            {!! app(\App\Services\Media\MarkdownRendererService::class)->render((string) $translation->description) !!}
        </section>
    @endif

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <section class="space-y-6">
            <h2 class="text-2xl font-black text-black uppercase border-b-3 border-black pb-3">
                PRODUCTOS RELACIONADOS
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach($relatedProducts as $rel)
                    @php
                        $relTrans = $rel->translations->where('locale', $currentLocale)->first();
                        $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                        $relUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$relTrans?->slug}", $currentMarket);
                    @endphp
                    <a href="{{ $relUrl }}" class="brutalist-card p-4 hover:bg-[#FFE600]/20 transition-all block space-y-3 group">
                        <div class="h-32 bg-black rounded overflow-hidden flex items-center justify-center">
                            @if($rel->featured_image)
                                <img src="{{ $rel->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                            @else
                                <span class="text-3xl text-[#FFE600] font-black">[ PROD ]</span>
                            @endif
                        </div>
                        <h4 class="font-black text-black group-hover:text-[#FF5A5F] transition-colors truncate text-sm uppercase">{{ $relTrans?->name }}</h4>
                        @if(!empty($showPrices))
                            <span class="text-black font-black text-xs block">{{ $rel->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-black font-black text-xs block uppercase">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Inquiry Modal -->
    <div x-show="showInquiryModal"
         @click.outside="showInquiryModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80"
         style="display: none;">
        <div class="bg-[#FFFDF8] border-3 border-black max-w-lg w-full p-6 space-y-4 shadow-[8px_8px_0px_0px_#FFE600]">
            <div class="flex items-center justify-between border-b-2 border-black pb-3">
                <h3 class="font-black text-black text-base uppercase">{{ __('SOLICITAR PRESUPUESTO') }}</h3>
                <button type="button" @click="showInquiryModal = false" class="text-black hover:bg-[#FF5A5F] px-2 py-0.5 border border-black font-black text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('shop.inquiry', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-4 text-xs font-black">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label class="block text-black uppercase text-[10px] mb-1">{{ __('Producto') }}</label>
                    <input type="text" readonly value="{{ $translation->name }} ({{ $product->sku }})" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-black uppercase text-[10px] mb-1">{{ __('Nombre *') }}</label>
                        <input type="text" name="name" required class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                    <div>
                        <label class="block text-black uppercase text-[10px] mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="phone" class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                    </div>
                </div>

                <div>
                    <label class="block text-black uppercase text-[10px] mb-1">{{ __('Email *') }}</label>
                    <input type="email" name="email" required class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20">
                </div>

                <div>
                    <label class="block text-black uppercase text-[10px] mb-1">Mensaje *</label>
                    <textarea name="message" rows="3" required class="w-full bg-white border-2 border-black rounded px-3 py-2 text-black focus:bg-[#FFE600]/20"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-3">
                    <button type="button" @click="showInquiryModal = false" class="px-4 py-2 border-2 border-black bg-white text-black font-black uppercase">Cancelar</button>
                    <button type="submit" class="btn-brutalist py-2 px-5 text-xs">Enviar Solicitud &rarr;</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Schema.org Product JSON-LD -->
@endsection
