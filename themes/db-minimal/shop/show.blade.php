@extends('themes::db-minimal.layouts.app')

@section('title', ($translation->meta_title ?? $translation->name) . ' - dbCMS Minimal')
@section('meta_description', $translation->meta_description ?? $translation->summary ?? '')

@section('content')
<div class="space-y-16" x-data="{
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
                this.addedMessage = 'Añadido a la cesta';
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
    <nav class="flex items-center gap-2 text-xs font-mono text-neutral-400">
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="hover:text-neutral-900">{{ __(__('Catálogo')) }}</a>
        <span>/</span>
        @if($product->category)
            <span>{{ $product->category->localizedName() }}</span>
            <span>/</span>
        @endif
        <span class="text-neutral-900 font-medium">{{ $translation->name }}</span>
    </nav>

    <!-- Product Overview Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

        <!-- Left: Image Gallery -->
        <div class="space-y-4">
            <div class="minimal-card h-96 sm:h-[480px] bg-neutral-50 overflow-hidden flex items-center justify-center p-4">
                <template x-if="activeImage">
                    <img :src="activeImage" alt="{{ $translation->name }}" class="max-h-full max-w-full object-contain" loading="lazy" decoding="async">
                </template>
                <template x-if="!activeImage">
                    <div class="text-neutral-300 text-6xl font-light">◻</div>
                </template>
            </div>

            <!-- Thumbnail Switcher -->
            @if(!empty($product->gallery_images) && count($product->gallery_images) > 0)
                <div class="flex items-center gap-2 overflow-x-auto pb-2">
                    @if($product->featured_image)
                        <button type="button" @click="activeImage = @js($product->featured_image)" class="w-16 h-16 rounded overflow-hidden border bg-neutral-50 shrink-0 transition-all" :class="activeImage === @js($product->featured_image) ? 'border-neutral-900' : 'border-neutral-200 opacity-60 hover:opacity-100'">
                            <img src="{{ $product->featured_image }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endif
                    @foreach($product->gallery_images as $gImg)
                        <button type="button" @click="activeImage = @js($gImg)" class="w-16 h-16 rounded overflow-hidden border bg-neutral-50 shrink-0 transition-all" :class="activeImage === @js($gImg) ? 'border-neutral-900' : 'border-neutral-200 opacity-60 hover:opacity-100'">
                            <img src="{{ $gImg }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right: Specs, Pricing & Purchase / Quote Action Box -->
        <div class="minimal-card p-6 sm:p-8 space-y-6">
            <div class="space-y-2 border-b border-neutral-100 pb-5">
                <div class="flex items-center justify-between font-mono text-[10px] uppercase tracking-widest text-neutral-400">
                    <span>{{ $product->category?->localizedName() ?? 'General' }}</span>
                    <span>SKU: {{ $product->sku }}</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-light text-neutral-900 leading-tight">
                    {{ $translation->name }}
                </h1>
            </div>

            <!-- Price & Availability -->
            <div class="flex items-baseline justify-between border-b border-neutral-100 pb-5 font-mono">
                <div>
                    @if(!empty($showPrices))
                        <span class="text-2xl font-semibold text-neutral-900">
                            {{ $product->getFormattedPrice($currentMarket) }}
                        </span>
                        @if($product->compare_price)
                            <span class="text-xs text-neutral-400 line-through ml-2">
                                {{ number_format($product->compare_price, 2) }} €
                            </span>
                        @endif
                    @else
                        <span class="text-lg font-semibold text-neutral-700">
                            {{ $currentLocale === 'ca' ? 'Preu a consultar' : ($currentLocale === 'en' ? 'Pricing upon request' : 'Precio a consultar') }}
                        </span>
                    @endif
                </div>

                <div>
                    @if($product->isInStock())
                        <span class="text-xs font-mono text-neutral-700 uppercase tracking-widest">
                            Disponible
                        </span>
                    @else
                        <span class="text-xs font-mono text-neutral-400 uppercase tracking-widest">
                            Agotado
                        </span>
                    @endif
                </div>
            </div>

            <!-- Short Summary -->
            <p class="text-neutral-600 text-sm font-light leading-relaxed">
                {{ $translation->summary ?? 'Diseño escandinavo de líneas puras y funcionalidad esencial.' }}
            </p>

            <!-- Actions: Cart vs Quote -->
            @if($isShop)
                <!-- Mode: Full E-Commerce Shop -->
                <div class="space-y-4 pt-2">
                    <div class="flex items-center gap-4 text-xs font-mono">
                        <label class="text-neutral-500 uppercase tracking-wider text-[10px]">{{ __('Cantidad:') }}</label>
                        <div class="flex items-center border border-neutral-200 rounded">
                            <button type="button" @click="if(qty > 1) qty--" class="px-3 py-1.5 text-neutral-500 hover:text-neutral-900">-</button>
                            <input type="number" x-model="qty" min="1" max="99" class="w-12 text-center bg-transparent border-0 text-neutral-900 font-medium text-xs focus:ring-0">
                            <button type="button" @click="qty++" class="px-3 py-1.5 text-neutral-500 hover:text-neutral-900">+</button>
                        </div>
                    </div>

                    <button type="button"
                            @click="addToCart({{ $product->id }})"
                            :disabled="adding || !{{ $product->isInStock() ? 'true' : 'false' }}"
                            class="w-full py-3 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-xs font-mono uppercase tracking-widest flex items-center justify-center gap-2 transition-colors disabled:opacity-40">
                        <span x-text="adding ? 'Procesando...' : 'Añadir a la Cesta'"></span>
                    </button>

                    <div x-show="addedMessage" x-cloak class="p-2 border border-neutral-200 text-neutral-900 text-xs text-center font-mono">
                        <span x-text="addedMessage"></span>
                    </div>
                </div>
            @else
                <!-- Mode: Catalog Only (Quote / Inquiry) -->
                <div class="space-y-4 pt-2">
                    <button type="button"
                            @click="showInquiryModal = true"
                            class="w-full py-3 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-xs font-mono uppercase tracking-widest flex items-center justify-center gap-2 transition-colors">
                        <span>Consultar Disponibilidad &rarr;</span>
                    </button>
                </div>
            @endif

            <!-- Technical Specifications -->
            @if(!empty($product->specs_json))
                <div class="pt-4 border-t border-neutral-100 space-y-2 font-mono text-xs">
                    <span class="text-[10px] text-neutral-400 uppercase tracking-widest block">Especificaciones:</span>
                    <div class="grid grid-cols-2 gap-2">
                        @foreach($product->specs_json as $k => $v)
                            <div class="p-2 bg-neutral-50 border border-neutral-100 rounded">
                                <span class="text-neutral-400 block text-[9px] uppercase">{{ $k }}</span>
                                <span class="text-neutral-900 font-medium text-[11px]">{{ is_array($v) ? implode(', ', $v) : $v }}</span>
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
            <h2 class="text-xl font-light text-neutral-900 border-b border-neutral-200 pb-3">
                Detalles del Objeto
            </h2>
            @foreach($renderedSections as $sectionHtml)
                <div>
                    {!! $sectionHtml !!}
                </div>
            @endforeach
        </section>
    @elseif(!empty($translation->description))
        <section class="minimal-card p-8 font-light text-neutral-700 text-sm leading-relaxed max-w-none">
            {!! app(\App\Services\Media\MarkdownRendererService::class)->render((string) $translation->description) !!}
        </section>
    @endif

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <section class="space-y-6">
            <h2 class="text-base font-mono uppercase tracking-widest text-neutral-900 border-b border-neutral-200 pb-3">
                Relacionados
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 font-mono text-xs">
                @foreach($relatedProducts as $rel)
                    @php
                        $relTrans = $rel->translations->where('locale', $currentLocale)->first() ?? $rel->translations->first();
                        $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                        $relUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$relTrans?->slug}", $currentMarket);
                    @endphp
                    <a href="{{ $relUrl }}" class="minimal-card p-4 hover:border-neutral-400 transition-all block space-y-2 group">
                        <div class="h-32 bg-neutral-50 rounded overflow-hidden flex items-center justify-center">
                            @if($rel->featured_image)
                                <img src="{{ $rel->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                            @else
                                <span class="text-2xl text-neutral-300">◻</span>
                            @endif
                        </div>
                        <h4 class="font-medium text-neutral-900 group-hover:underline truncate">{{ $relTrans?->name }}</h4>
                        @if(!empty($showPrices))
                            <span class="text-neutral-900 block font-semibold">{{ $rel->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-neutral-600 block text-xs font-semibold">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
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
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/70 backdrop-blur-sm"
         style="display: none;">
        <div class="bg-white max-w-md w-full rounded p-6 font-sans space-y-4 shadow-xl">
            <div class="flex items-center justify-between border-b border-neutral-100 pb-3">
                <h3 class="font-mono text-xs uppercase tracking-widest text-neutral-900">Consulta</h3>
                <button type="button" @click="showInquiryModal = false" class="text-neutral-400 hover:text-neutral-900 text-lg">&times;</button>
            </div>

            <form method="POST" action="{{ route('shop.inquiry', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-4 text-xs font-mono">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label class="block text-neutral-400 text-[10px] uppercase mb-1">Objeto</label>
                    <input type="text" readonly value="{{ $translation->name }} ({{ $product->sku }})" class="w-full bg-neutral-50 border border-neutral-200 rounded px-3 py-2 text-neutral-900">
                </div>

                <div>
                    <label class="block text-neutral-400 text-[10px] uppercase mb-1">{{ __('Nombre *') }}</label>
                    <input type="text" name="name" required class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900">
                </div>

                <div>
                    <label class="block text-neutral-400 text-[10px] uppercase mb-1">{{ __('Email *') }}</label>
                    <input type="email" name="email" required class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900">
                </div>

                <div>
                    <label class="block text-neutral-400 text-[10px] uppercase mb-1">Mensaje *</label>
                    <textarea name="message" rows="3" required class="w-full bg-white border border-neutral-200 rounded px-3 py-2 text-neutral-900"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-3">
                    <button type="button" @click="showInquiryModal = false" class="px-4 py-2 border border-neutral-200 text-neutral-600 rounded">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded font-mono uppercase tracking-wider">Enviar &rarr;</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Schema.org Product JSON-LD -->
@endsection
