@php
    $layout = app(\App\Services\Cms\FrontViewResolver::class)->layout();
@endphp
@extends($layout)

@section('title', ($translation->meta_title ?? $translation->name) . ' - dbCMS')
@section('meta_description', $translation->meta_description ?? $translation->summary ?? '')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-16" x-data="{
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
                this.addedMessage = '¡Producto añadido al carrito!';
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
    <nav class="flex items-center gap-2 text-xs font-mono text-gray-500" data-scroll-magic>
        <a href="{{ route('shop.index', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="hover:text-[#00FF99]">{{ __(__('Catálogo')) }}</a>
        <span>/</span>
        @if($product->category)
            <span>{{ $product->category->localizedName() }}</span>
            <span>/</span>
        @endif
        <span class="text-gray-300 font-bold">{{ $translation->name }}</span>
    </nav>

    <!-- Product Overview Grid (Photo Gallery + Purchase / Quote Box) -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-start" data-scroll-magic>

        <!-- Left: Image Gallery -->
        <div class="space-y-4">
            <div class="cyber-card h-96 sm:h-[480px] bg-black rounded-2xl overflow-hidden border border-gray-800 flex items-center justify-center p-2">
                <template x-if="activeImage">
                    <img :src="activeImage" alt="{{ $translation->name }}" class="max-h-full max-w-full object-contain">
                </template>
                <template x-if="!activeImage">
                    <div class="text-gray-600 text-6xl"><i class="fa-solid fa-box-open"></i></div>
                </template>
            </div>

            <!-- Thumbnail Switcher -->
            @if(!empty($product->gallery_images) && count($product->gallery_images) > 0)
                <div class="flex items-center gap-3 overflow-x-auto pb-2">
                    @if($product->featured_image)
                        <button type="button" @click="activeImage = @js($product->featured_image)" class="w-16 h-16 rounded-lg overflow-hidden border-2 bg-black shrink-0 transition-all" :class="activeImage === @js($product->featured_image) ? 'border-[#00FF99]' : 'border-gray-800 opacity-60 hover:opacity-100'">
                            <img src="{{ $product->featured_image }}" class="w-full h-full object-cover">
                        </button>
                    @endif
                    @foreach($product->gallery_images as $gImg)
                        <button type="button" @click="activeImage = @js($gImg)" class="w-16 h-16 rounded-lg overflow-hidden border-2 bg-black shrink-0 transition-all" :class="activeImage === @js($gImg) ? 'border-[#00FF99]' : 'border-gray-800 opacity-60 hover:opacity-100'">
                            <img src="{{ $gImg }}" class="w-full h-full object-cover">
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right: Specs, Pricing & Purchase / Quote Action Box -->
        <div class="cyber-card p-6 sm:p-8 space-y-6 font-mono">
            <div class="space-y-2 border-b border-gray-800 pb-5">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-[#00FF99] uppercase font-bold tracking-wider">
                        {{ $product->category?->localizedName() ?? 'General' }}
                    </span>
                    <span class="text-xs text-gray-500 font-mono">
                        SKU: {{ $product->sku }}
                    </span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-white leading-tight">
                    {{ $translation->name }}
                </h1>
            </div>

            <!-- Price & Availability -->
            <div class="flex items-baseline justify-between border-b border-gray-800 pb-5">
                <div>
                    @if(!empty($showPrices))
                        <span class="text-3xl font-black text-[#00FF99]">
                            {{ $product->getFormattedPrice($currentMarket) }}
                        </span>
                        @if($product->compare_price)
                            <span class="text-sm text-gray-500 line-through ml-2">
                                {{ number_format($product->compare_price, 2) }} €
                            </span>
                        @endif
                    @else
                        <span class="text-xl font-bold text-[#00FF99]">
                            {{ $currentLocale === 'ca' ? 'Preu a consultar' : ($currentLocale === 'en' ? 'Pricing upon request' : 'Precio a consultar') }}
                        </span>
                    @endif
                </div>

                <div>
                    @if($product->isInStock())
                        <span class="px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 text-xs font-bold">
                            <i class="fa-solid fa-check mr-1"></i> En Stock
                        </span>
                    @else
                        <span class="px-2.5 py-1 rounded-full bg-red-500/10 text-red-400 border border-red-500/30 text-xs font-bold">
                            <i class="fa-solid fa-xmark mr-1"></i> Agotado
                        </span>
                    @endif
                </div>
            </div>

            <!-- Short Summary -->
            <p class="text-gray-300 text-sm leading-relaxed">
                {{ $translation->summary ?? 'Solución técnica de alto rendimiento para infraestructuras escalables.' }}
            </p>

            <!-- Actions: Cart vs Quote -->
            @if($isShop)
                <!-- Mode: Full E-Commerce Shop -->
                <div class="space-y-4 pt-2">
                    <div class="flex items-center gap-4">
                        <label class="text-xs text-gray-400 font-bold uppercase">{{ __('Cantidad:') }}</label>
                        <div class="flex items-center border border-gray-700 rounded-lg bg-[#141414]">
                            <button type="button" @click="if(qty > 1) qty--" class="px-3 py-1.5 text-gray-400 hover:text-white text-sm">-</button>
                            <input type="number" x-model="qty" min="1" max="99" class="w-12 text-center bg-transparent border-0 text-white font-bold text-xs focus:ring-0">
                            <button type="button" @click="qty++" class="px-3 py-1.5 text-gray-400 hover:text-white text-sm">+</button>
                        </div>
                    </div>

                    <button type="button"
                            @click="addToCart({{ $product->id }})"
                            :disabled="adding || !{{ $product->isInStock() ? 'true' : 'false' }}"
                            class="btn-neon w-full py-3.5 text-sm flex items-center justify-center gap-2 disabled:opacity-40">
                        <span><i class="fa-solid fa-cart-shopping"></i></span>
                        <span x-text="adding ? 'Añadiendo...' : 'Añadir al Carrito'"></span>
                    </button>

                    <div x-show="addedMessage" x-cloak class="p-3 bg-[#00FF99]/10 border border-[#00FF99]/40 text-[#00FF99] rounded-lg text-xs text-center font-bold">
                        <span x-text="addedMessage"></span>
                    </div>
                </div>
            @else
                <!-- Mode: Catalog Only (Quote / Inquiry) -->
                <div class="space-y-4 pt-2">
                    <button type="button"
                            @click="showInquiryModal = true"
                            class="btn-neon w-full py-3.5 text-sm flex items-center justify-center gap-2">
                        <span><i class="fa-solid fa-clipboard-list"></i></span>
                        <span>Solicitar Presupuesto / Contactar &rarr;</span>
                    </button>
                    <p class="text-[11px] text-gray-500 text-center">{{ __('Respuesta técnica garantizada en menos de 24 horas laborables.') }}</p>
                </div>
            @endif

            <!-- Technical Specifications Quick Preview -->
            @if(!empty($product->specs_json))
                <div class="pt-4 border-t border-gray-800 space-y-2">
                    <span class="text-xs text-gray-400 font-bold uppercase">Especificaciones:</span>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        @foreach($product->specs_json as $k => $v)
                            <div class="p-2 bg-[#181818] border border-gray-800 rounded">
                                <span class="text-gray-500 block text-[10px] uppercase">{{ $k }}</span>
                                <span class="text-white font-bold">{{ is_array($v) ? implode(', ', $v) : $v }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

    </div>

    <!-- Modular Sections & Detailed Description (Content Block Manager) -->
    @if(isset($renderedSections) && count($renderedSections) > 0)
        <section class="space-y-12">
            <h2 class="text-xl font-bold text-white font-mono flex items-center gap-2 border-b border-gray-800 pb-3" data-scroll-magic>
                <span class="text-[#00FF99]">&gt;</span>
                <span>{{ __('Detalles de Ingeniería & Características') }}</span>
            </h2>
            @foreach($renderedSections as $sectionHtml)
                <div data-scroll-magic>
                    {!! $sectionHtml !!}
                </div>
            @endforeach
        </section>
    @elseif(!empty($translation->description))
        <section class="cyber-card p-8 font-mono text-sm leading-relaxed prose prose-invert max-w-none" data-scroll-magic>
            {!! app(\App\Services\Media\MarkdownRendererService::class)->render((string) $translation->description) !!}
        </section>
    @endif

    <!-- Related Products -->
    @if($relatedProducts->isNotEmpty())
        <section class="space-y-6" data-scroll-magic>
            <h2 class="text-xl font-bold text-white font-mono flex items-center gap-2 border-b border-gray-800 pb-3">
                <span class="text-[#00FF99]">&gt;</span>
                <span>Soluciones Relacionadas</span>
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                @foreach($relatedProducts as $rel)
                    @php
                        $relTrans = $rel->translations->where('locale', $currentLocale)->first();
                        $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                        $relUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$relTrans?->slug}", $currentMarket);
                    @endphp
                    <a href="{{ $relUrl }}" class="cyber-card p-4 hover:border-[#00FF99] transition-all block space-y-2 group font-mono text-xs">
                        <div class="h-32 bg-black rounded-lg overflow-hidden flex items-center justify-center">
                            @if($rel->featured_image)
                                <img src="{{ $rel->featured_image }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                            @else
                                <span class="text-3xl text-gray-600"><i class="fa-solid fa-box"></i></span>
                            @endif
                        </div>
                        <h4 class="font-bold text-white group-hover:text-[#00FF99] transition-colors truncate">{{ $relTrans?->name }}</h4>
                        <span class="text-[#00FF99] font-bold block">{{ $rel->getFormattedPrice($currentMarket) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    <!-- Catalog Mode: Request Quote Modal -->
    <div x-show="showInquiryModal"
         @keydown.escape.window="showInquiryModal = false"
         @click.self="showInquiryModal = false"
         x-transition
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-md"
         style="display: none;">
        <div @click.stop
             @click.outside="showInquiryModal = false"
             class="bg-[#141414] border border-[#00FF99]/40 max-w-lg w-full rounded-2xl p-6 font-mono space-y-4 shadow-2xl">
            <div class="flex items-center justify-between border-b border-gray-800 pb-3">
                <h3 class="font-bold text-white text-base">{{ __('Solicitar Presupuesto Técnico') }}</h3>
                <button type="button" @click="showInquiryModal = false" class="text-gray-400 hover:text-white text-xl">&times;</button>
            </div>

            <form method="POST" action="{{ route('shop.inquiry', ['market' => $currentMarket->code, 'locale' => $currentLocale]) }}" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="product_id" value="{{ $product->id }}">

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('Producto') }}</label>
                    <input type="text" readonly value="{{ $translation->name }} ({{ $product->sku }})" class="w-full bg-[#181818] border border-gray-800 rounded-lg px-3 py-2 text-[#00FF99]">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('Nombre Completo *') }}</label>
                        <input type="text" name="name" required class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                    </div>
                    <div>
                        <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('Teléfono') }}</label>
                        <input type="text" name="phone" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                    </div>
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">{{ __('Email Profesional *') }}</label>
                    <input type="email" name="email" required class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white">
                </div>

                <div>
                    <label class="block text-gray-400 text-[10px] font-bold uppercase mb-1">Requerimientos / Consulta *</label>
                    <textarea name="message" rows="3" required placeholder="{{ __('Indica volumen estimado, casos de uso o requerimientos de integración...') }}" class="w-full bg-[#1A1A1A] border border-gray-700 rounded-lg px-3 py-2 text-white"></textarea>
                </div>

                <div class="pt-2 flex justify-end gap-3">
                    <button type="button" @click="showInquiryModal = false" class="px-4 py-2 bg-gray-800 text-gray-300 rounded-lg">Cancelar</button>
                    <button type="submit" class="btn-neon py-2 px-5">Enviar Solicitud &rarr;</button>
                </div>
            </form>
        </div>
    </div>

</div>

<!-- Schema.org Product JSON-LD -->
@endsection
