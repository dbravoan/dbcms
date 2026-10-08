@extends('themes.db-sunset.layouts.app')

@section('title', ($translation->meta_title ?? $translation->name) . ' - dbCMS Sunset')
@section('meta_description', $translation->meta_description ?? $translation->summary ?? '')

@section('content')
<div class="max-w-7xl mx-auto space-y-16" x-data="{
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
                this.addedMessage = '¡Añadido al carrito!';
                setTimeout(() => window.location.href = '{{ route('shop.cart', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}', 800);
            } else {
                alert(data.message || 'Error al añadir al carrito.');
            }
        } catch(e) {
            console.error(e);
        } finally {
            this.adding = false;
        }
    }
}">

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs text-slate-400">
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="hover:text-rose-400">{{ __(__('Catálogo')) }}</a>
        <span>/</span>
        @if($product->category)
            <span>{{ $product->category->localizedName() }}</span>
            <span>/</span>
        @endif
        <span class="text-white font-semibold">{{ $translation->name }}</span>
    </nav>

    <!-- Product Overview Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

        <!-- Left: Image Gallery -->
        <div class="space-y-4">
            <div class="sunset-card h-96 sm:h-[480px] bg-slate-900/60 overflow-hidden flex items-center justify-center p-4">
                <template x-if="activeImage">
                    <img :src="activeImage" alt="{{ $translation->name }}" class="max-h-full max-w-full object-contain" loading="lazy" decoding="async">
                </template>
                <template x-if="!activeImage">
                    <div class="text-slate-700 text-6xl"><i class="fa-solid fa-box"></i></div>
                </template>
            </div>

            <!-- Thumbnail Switcher -->
            @if(!empty($product->gallery_images) && count($product->gallery_images) > 0)
                <div class="flex items-center gap-3 overflow-x-auto pb-2">
                    @if($product->featured_image)
                        <button type="button" @click="activeImage = @js($product->featured_image)" class="w-16 h-16 rounded-xl overflow-hidden border-2 bg-slate-900 shrink-0 transition-all" :class="activeImage === @js($product->featured_image) ? 'border-rose-500' : 'border-white/10 opacity-60 hover:opacity-100'">
                            <img src="{{ $product->featured_image }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endif
                    @foreach($product->gallery_images as $gImg)
                        <button type="button" @click="activeImage = @js($gImg)" class="w-16 h-16 rounded-xl overflow-hidden border-2 bg-slate-900 shrink-0 transition-all" :class="activeImage === @js($gImg) ? 'border-rose-500' : 'border-white/10 opacity-60 hover:opacity-100'">
                            <img src="{{ $gImg }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right: Specs, Pricing & Purchase / Quote Action Box -->
        <div class="sunset-card p-6 sm:p-8 space-y-6">
            <div class="space-y-2 border-b border-white/10 pb-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-rose-400 uppercase font-semibold tracking-wider">
                        {{ $product->category?->localizedName() ?? 'General' }}
                    </span>
                    <span class="text-xs text-slate-500 font-mono">
                        SKU: {{ $product->sku }}
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-white leading-tight">
                    {{ $translation->name }}
                </h1>
            </div>

            <!-- Price & Availability -->
            <div class="flex items-baseline justify-between border-b border-white/10 pb-5">
                <div>
                    @if(!empty($showPrices))
                        <span class="text-3xl font-black text-transparent bg-clip-text bg-gradient-to-r from-rose-400 to-amber-300">
                            {{ $product->getFormattedPrice($currentMarket) }}
                        </span>
                        @if($product->compare_price)
                            <span class="text-sm text-slate-500 line-through ml-2">
                                {{ number_format($product->compare_price, 2) }} €
                            </span>
                        @endif
                    @else
                        <span class="text-xl font-bold text-rose-400">
                            {{ $currentLocale === 'ca' ? 'Preu a consultar' : ($currentLocale === 'en' ? 'Pricing upon request' : 'Precio a consultar') }}
                        </span>
                    @endif
                </div>

                <div>
                    @if($product->isInStock())
                        <span class="px-3 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-xs font-semibold">
                            <i class="fa-solid fa-check mr-1"></i> En Stock
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full bg-rose-500/10 text-rose-400 border border-rose-500/30 text-xs font-semibold">
                            <i class="fa-solid fa-xmark mr-1"></i> Agotado
                        </span>
                    @endif
                </div>
            </div>

            <!-- Short Summary -->
            <p class="text-slate-300 text-sm leading-relaxed">
                {{ $translation->summary ?? 'Solución técnica de alto rendimiento y arquitectura moderna.' }}
            </p>

            <!-- Actions: Cart vs Quote -->
            @if($isShop)
                <!-- Mode: Full E-Commerce Shop -->
                <div class="space-y-4 pt-2">
                    <div class="flex items-center gap-4 text-xs">
                        <label class="text-slate-400 font-semibold uppercase">{{ __('Cantidad:') }}</label>
                        <div class="flex items-center border border-white/10 rounded-xl bg-white/5">
                            <button type="button" @click="if(qty > 1) qty--" class="px-3 py-1.5 text-slate-400 hover:text-white">-</button>
                            <input type="number" x-model="qty" min="1" max="99" class="w-12 text-center bg-transparent border-0 text-white font-bold text-xs focus:ring-0">
                            <button type="button" @click="qty++" class="px-3 py-1.5 text-slate-400 hover:text-white">+</button>
                        </div>
                    </div>

                    <button type="button"
                            @click="addToCart({{ $product->id }})"
                            :disabled="adding || !{{ $product->isInStock() ? 'true' : 'false' }}"
                            class="btn-sunset w-full py-3.5 text-sm flex items-center justify-center gap-2 disabled:opacity-40">
                        <span><i class="fa-solid fa-cart-shopping"></i></span>
                        <span x-text="adding ? 'Añadiendo...' : 'Añadir al Carrito'"></span>
                    </button>

                    <div x-show="addedMessage" x-cloak class="p-3 bg-rose-500/10 border border-rose-500/30 text-rose-300 rounded-xl text-xs text-center font-semibold">
                        <span x-text="addedMessage"></span>
                    </div>
                </div>
            @else
                <!-- Mode: Catalog Only (Quote / Inquiry) -->
                <div class="space-y-4 pt-2">
                    <button type="button"
                            @click="showInquiryModal = true"
                            class="btn-sunset w-full py-3.5 text-sm flex items-center justify-center gap-2">
                        <span><i class="fa-solid fa-clipboard-list"></i></span>
                        <span>Solicitar Información / Contactar &rarr;</span>
                    </button>
                    <p class="text-[11px] text-slate-500 text-center">
                        Respuesta garantizada en menos de 24 horas laborables.
                    </p>
                </div>
            @endif

            <!-- Technical Specifications -->
            @if(!empty($product->specs_json))
                <div class="pt-4 border-t border-white/10 space-y-2">
                    <span class="text-xs text-slate-400 font-semibold uppercase">Especificaciones:</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        @foreach($product->specs_json as $k => $v)
                            <div class="p-2.5 bg-white/5 border border-white/10 rounded-xl">
                                <span class="text-slate-500 block text-[10px] uppercase">{{ $k }}</span>
                                <span class="text-white font-semibold">{{ is_array($v) ? implode(', ', $v) : $v }}</span>
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
            <h2 class="text-xl font-bold text-white border-b border-white/10 pb-3 flex items-center gap-2">
                <span class="text-rose-400"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
                <span>{{ __('Características Detalladas') }}</span>
            </h2>
            @foreach($renderedSections as $sectionHtml)
                <div>
                    {!! $sectionHtml !!}
                </div>
            @endforeach
        </section>
    @elseif(!empty($translation->description))
        <section class="sunset-card p-8 sm:p-10 text-slate-300 text-sm leading-relaxed max-w-none">
            {!! app(\App\Services\Media\MarkdownRendererService::class)->render((string) $translation->description) !!}
        </section>
    @endif

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <section class="space-y-6">
            <h2 class="text-xl font-bold text-white border-b border-white/10 pb-3">
                Productos Relacionados
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach($relatedProducts as $rel)
                    @php
                        $relTrans = $rel->translations->where('locale', $currentLocale)->first() ?? $rel->translations->first();
                        $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                        $relUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$relTrans?->slug}", $currentMarket);
                    @endphp
                    <a href="{{ $relUrl }}" class="sunset-card p-4 hover:border-rose-500/40 transition-all block space-y-3 group">
                        <div class="h-32 bg-slate-900 rounded-xl overflow-hidden flex items-center justify-center">
                            @if($rel->featured_image)
                                <img src="{{ $rel->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                            @else
                                <span class="text-3xl text-slate-700"><i class="fa-solid fa-box"></i></span>
                            @endif
                        </div>
                        <h4 class="font-semibold text-white group-hover:text-rose-400 transition-colors truncate text-sm">{{ $relTrans?->name }}</h4>
                        @if(!empty($showPrices))
                            <span class="text-rose-400 font-semibold text-xs block">{{ $rel->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-rose-400 font-semibold text-xs block">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
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
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md"
         style="display: none;">
        <div class="bg-[#120c22] border border-white/15 max-w-lg w-full rounded-2xl p-6 space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-white/10 pb-3">
                <h3 class="font-bold text-white text-base">{{ __('Solicitar Información') }}</h3>
                <button type="button" @click="showInquiryModal = false" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
            </div>

            <form method="POST" action="{{ route('shop.inquiry', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label class="block text-slate-400 text-[10px] font-bold uppercase mb-1">{{ __('Producto') }}</label>
                    <input type="text" readonly value="{{ $translation->name }} ({{ $product->sku }})" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-rose-300">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-slate-400 text-[10px] font-bold uppercase mb-1">{{ __('Nombre Completo *') }}</label>
                        <input type="text" name="name" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-slate-400 text-[10px] font-bold uppercase mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="phone" class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-slate-400 text-[10px] font-bold uppercase mb-1">{{ __('Email *') }}</label>
                    <input type="email" name="email" required class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white">
                </div>

                <div>
                    <label class="block text-slate-400 text-[10px] font-bold uppercase mb-1">Mensaje / Consulta *</label>
                    <textarea name="message" rows="3" required placeholder="Indica detalles de tu consulta..." class="w-full bg-white/5 border border-white/10 rounded-xl px-3 py-2 text-white"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-3">
                    <button type="button" @click="showInquiryModal = false" class="px-4 py-2 bg-white/10 text-slate-300 rounded-xl">Cancelar</button>
                    <button type="submit" class="btn-sunset py-2 px-5 text-xs">Enviar Consulta &rarr;</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Schema.org Product JSON-LD -->
@endsection
