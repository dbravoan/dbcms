@extends('themes::db-editorial.layouts.app')

@section('title', ($translation->meta_title ?? $translation->name) . ' - Editorial dbCMS')
@section('meta_description', $translation->meta_description ?? $translation->summary ?? '')

@section('content')
<div class="max-w-6xl mx-auto space-y-16" x-data="{
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
                this.addedMessage = '¡Añadido a la cesta!';
                setTimeout(() => window.location.href = '{{ route('shop.cart', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}', 800);
            } else {
                alert(data.message || 'Error al añadir a la cesta.');
            }
        } catch(e) {
            console.error(e);
        } finally {
            this.adding = false;
        }
    }
}">

    <!-- Breadcrumbs -->
    <nav class="flex items-center gap-2 text-xs font-sans text-stone-500">
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="hover:text-stone-900">{{ __(__('Catálogo')) }}</a>
        <span>/</span>
        @if($product->category)
            <span>{{ $product->category->localizedName() }}</span>
            <span>/</span>
        @endif
        <span class="text-stone-900 font-semibold">{{ $translation->name }}</span>
    </nav>

    <!-- Product Overview Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start">

        <!-- Left: Image Gallery -->
        <div class="space-y-4">
            <div class="editorial-card h-96 sm:h-[480px] bg-stone-100 rounded-2xl overflow-hidden flex items-center justify-center p-4">
                <template x-if="activeImage">
                    <img :src="activeImage" alt="{{ $translation->name }}" class="max-h-full max-w-full object-contain" loading="lazy" decoding="async">
                </template>
                <template x-if="!activeImage">
                    <div class="text-stone-300 text-6xl font-serif italic">dbCMS</div>
                </template>
            </div>

            <!-- Thumbnail Switcher -->
            @if(!empty($product->gallery_images) && count($product->gallery_images) > 0)
                <div class="flex items-center gap-3 overflow-x-auto pb-2">
                    @if($product->featured_image)
                        <button type="button" @click="activeImage = @js($product->featured_image)" class="w-16 h-16 rounded-lg overflow-hidden border-2 bg-stone-50 shrink-0 transition-all" :class="activeImage === @js($product->featured_image) ? 'border-stone-900' : 'border-stone-200 opacity-60 hover:opacity-100'">
                            <img src="{{ $product->featured_image }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endif
                    @foreach($product->gallery_images as $gImg)
                        <button type="button" @click="activeImage = @js($gImg)" class="w-16 h-16 rounded-lg overflow-hidden border-2 bg-stone-50 shrink-0 transition-all" :class="activeImage === @js($gImg) ? 'border-stone-900' : 'border-stone-200 opacity-60 hover:opacity-100'">
                            <img src="{{ $gImg }}" class="w-full h-full object-cover" loading="lazy" decoding="async">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right: Specs, Pricing & Purchase / Quote Action Box -->
        <div class="editorial-card p-8 sm:p-10 space-y-6">
            <div class="space-y-2 border-b border-stone-200 pb-5">
                <div class="flex items-center justify-between font-sans">
                    <span class="text-xs text-amber-800 uppercase font-semibold tracking-wider">
                        {{ $product->category?->localizedName() ?? 'Editorial' }}
                    </span>
                    <span class="text-xs text-stone-400 font-mono">
                        REF: {{ $product->sku }}
                    </span>
                </div>
                <h1 class="text-3xl sm:text-4xl font-serif font-bold text-stone-900 leading-tight">
                    {{ $translation->name }}
                </h1>
            </div>

            <!-- Price & Availability -->
            <div class="flex items-baseline justify-between border-b border-stone-200 pb-5 font-sans">
                <div>
                    @if(!empty($showPrices))
                        <span class="text-3xl font-serif font-bold text-stone-900">
                            {{ $product->getFormattedPrice($currentMarket) }}
                        </span>
                        @if($product->compare_price)
                            <span class="text-sm text-stone-400 line-through ml-2">
                                {{ number_format($product->compare_price, 2) }} €
                            </span>
                        @endif
                    @else
                        <span class="text-xl font-serif italic text-stone-700 font-bold">
                            {{ $currentLocale === 'ca' ? 'Preu a consultar' : ($currentLocale === 'en' ? 'Pricing upon request' : 'Precio a consultar') }}
                        </span>
                    @endif
                </div>

                <div>
                    @if($product->isInStock())
                        <span class="px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 border border-emerald-200 text-xs font-medium">
                            <i class="fa-solid fa-check mr-1"></i> En Stock
                        </span>
                    @else
                        <span class="px-3 py-1 rounded-full bg-red-50 text-red-800 border border-red-200 text-xs font-medium">
                            <i class="fa-solid fa-xmark mr-1"></i> Agotado
                        </span>
                    @endif
                </div>
            </div>

            <!-- Short Summary -->
            <p class="text-stone-600 font-serif text-base leading-relaxed">
                {{ $translation->summary ?? 'Edición especial cuidada y disponible en distribución internacional.' }}
            </p>

            <!-- Actions: Cart vs Quote -->
            @if($isShop)
                <!-- Mode: Full E-Commerce Shop -->
                <div class="space-y-4 pt-2 font-sans">
                    <div class="flex items-center gap-4 text-xs">
                        <label class="text-stone-600 font-semibold uppercase">{{ __('Cantidad:') }}</label>
                        <div class="flex items-center border border-stone-300 rounded-lg bg-white">
                            <button type="button" @click="if(qty > 1) qty--" class="px-3 py-1.5 text-stone-600 hover:text-stone-900">-</button>
                            <input type="number" x-model="qty" min="1" max="99" class="w-12 text-center bg-transparent border-0 text-stone-900 font-bold text-xs focus:ring-0">
                            <button type="button" @click="qty++" class="px-3 py-1.5 text-stone-600 hover:text-stone-900">+</button>
                        </div>
                    </div>

                    <button type="button"
                            @click="addToCart({{ $product->id }})"
                            :disabled="adding || !{{ $product->isInStock() ? 'true' : 'false' }}"
                            class="w-full py-3.5 bg-stone-900 hover:bg-stone-800 text-white rounded-xl text-sm font-medium flex items-center justify-center gap-2 transition-colors disabled:opacity-40">
                        <span><i class="fa-solid fa-bag-shopping"></i></span>
                        <span x-text="adding ? 'Añadiendo...' : 'Añadir a la Cesta'"></span>
                    </button>

                    <div x-show="addedMessage" x-cloak class="p-3 bg-stone-100 border border-stone-300 text-stone-800 rounded-lg text-xs text-center font-semibold">
                        <span x-text="addedMessage"></span>
                    </div>
                </div>
            @else
                <!-- Mode: Catalog Only (Quote / Inquiry) -->
                <div class="space-y-4 pt-2 font-sans">
                    <button type="button"
                            @click="showInquiryModal = true"
                            class="w-full py-3.5 bg-stone-900 hover:bg-stone-800 text-white rounded-xl text-sm font-medium flex items-center justify-center gap-2 transition-colors">
                        <span><i class="fa-solid fa-clipboard-list"></i></span>
                        <span>Solicitar Información / Contactar &rarr;</span>
                    </button>
                    <p class="text-[11px] text-stone-400 text-center font-serif italic">{{ __('Atención personalizada por el equipo editorial en menos de 24 horas.') }}</p>
                </div>
            @endif

            <!-- Technical Specifications Quick Preview -->
            @if(!empty($product->specs_json))
                <div class="pt-4 border-t border-stone-200 space-y-2 font-sans">
                    <span class="text-xs text-stone-500 font-semibold uppercase">{{ __('Ficha Técnica & Detalles:') }}</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        @foreach($product->specs_json as $k => $v)
                            <div class="p-2.5 bg-stone-50 border border-stone-200 rounded-lg">
                                <span class="text-stone-400 block text-[10px] uppercase">{{ $k }}</span>
                                <span class="text-stone-900 font-semibold">{{ is_array($v) ? implode(', ', $v) : $v }}</span>
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
            <h2 class="text-2xl font-serif font-bold text-stone-900 border-b border-stone-200 pb-3">
                Detalles de la Obra & Especificaciones
            </h2>
            @foreach($renderedSections as $sectionHtml)
                <div>
                    {!! $sectionHtml !!}
                </div>
            @endforeach
        </section>
    @elseif(!empty($translation->description))
        <section class="editorial-card p-8 sm:p-10 font-serif text-base text-stone-800 leading-relaxed max-w-none">
            {!! app(\App\Services\Media\MarkdownRendererService::class)->render((string) $translation->description) !!}
        </section>
    @endif

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <section class="space-y-6">
            <h2 class="text-2xl font-serif font-bold text-stone-900 border-b border-stone-200 pb-3">{{ __('Títulos Recomendados') }}</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach($relatedProducts as $rel)
                    @php
                        $relTrans = $rel->translations->where('locale', $currentLocale)->first();
                        $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                        $relUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$relTrans?->slug}", $currentMarket);
                    @endphp
                    <a href="{{ $relUrl }}" class="editorial-card p-4 hover:shadow-md transition-all block space-y-3 group">
                        <div class="h-36 bg-stone-100 rounded-lg overflow-hidden flex items-center justify-center">
                            @if($rel->featured_image)
                                <img src="{{ $rel->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                            @else
                                <span class="text-3xl text-stone-300"><i class="fa-solid fa-book-open"></i></span>
                            @endif
                        </div>
                        <h4 class="font-serif font-bold text-stone-900 group-hover:text-amber-800 transition-colors truncate text-sm">{{ $relTrans?->name }}</h4>
                        @if(!empty($showPrices))
                            <span class="text-sm font-sans font-semibold text-stone-900 block">{{ $rel->getFormattedPrice($currentMarket) }}</span>
                        @else
                            <span class="text-xs font-serif italic text-stone-600 font-semibold block">{{ $currentLocale === 'ca' ? 'Consultar' : ($currentLocale === 'en' ? 'Inquire' : 'Consultar') }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Catalog Mode: Inquiry Modal -->
    <div x-show="showInquiryModal"
         @click.outside="showInquiryModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-950/80 backdrop-blur-sm"
         style="display: none;">
        <div class="bg-white border border-stone-200 max-w-lg w-full rounded-2xl p-6 space-y-4 shadow-2xl font-sans">
            <div class="flex items-center justify-between border-b border-stone-200 pb-3">
                <h3 class="font-serif font-bold text-stone-900 text-lg">{{ __('Solicitar Información') }}</h3>
                <button type="button" @click="showInquiryModal = false" class="text-stone-400 hover:text-stone-900 text-xl font-bold">&times;</button>
            </div>

            <form method="POST" action="{{ route('shop.inquiry', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label class="block text-stone-600 text-[10px] font-bold uppercase mb-1">{{ __('Título') }}</label>
                    <input type="text" readonly value="{{ $translation->name }} ({{ $product->sku }})" class="w-full bg-stone-50 border border-stone-200 rounded-lg px-3 py-2 text-stone-900">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-stone-600 text-[10px] font-bold uppercase mb-1">{{ __('Nombre Completo *') }}</label>
                        <input type="text" name="name" required class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                    <div>
                        <label class="block text-stone-600 text-[10px] font-bold uppercase mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="phone" class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                    </div>
                </div>

                <div>
                    <label class="block text-stone-600 text-[10px] font-bold uppercase mb-1">{{ __('Email *') }}</label>
                    <input type="email" name="email" required class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900">
                </div>

                <div>
                    <label class="block text-stone-600 text-[10px] font-bold uppercase mb-1">Consulta o Requerimientos *</label>
                    <textarea name="message" rows="3" required placeholder="{{ __('Indica detalles de pedido o consulta...') }}" class="w-full bg-white border border-stone-300 rounded-lg px-3 py-2 text-stone-900"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-3">
                    <button type="button" @click="showInquiryModal = false" class="px-4 py-2 bg-stone-100 text-stone-700 rounded-lg">Cancelar</button>
                    <button type="submit" class="px-5 py-2 bg-stone-900 hover:bg-stone-800 text-white rounded-lg font-medium">Enviar Consulta &rarr;</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Schema.org Product JSON-LD -->
@endsection
