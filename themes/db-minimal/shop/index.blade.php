@extends('themes::db-minimal.layouts.app')

@section('title', ($isShop ? __('Tienda Minimal') : __('Catálogo')) . ' - dbCMS')
@section('meta_description', __('Objetos, herramientas y arquitectura digital con diseño minimalista.'))

@section('content')
<div class="space-y-16">

    <!-- Hero Header -->
    <header class="text-center max-w-2xl mx-auto space-y-3">
        <span class="text-[10px] font-mono uppercase tracking-widest text-neutral-400">
            @if($isShop)
                Tienda &middot; {{ strtoupper($currentMarket->code) }} ({{ $currentMarket->currency }})
            @else
                Catálogo &middot; {{ strtoupper($currentMarket->code) }}
            @endif
        </span>

        <h1 class="text-3xl sm:text-4xl font-light text-neutral-900 tracking-tight">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedName($currentLocale) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Col·lecció & Objectes' : ($currentLocale === 'en' ? 'Collection & Objects' : 'Colección & Objetos') }}
            @else
                {{ $currentLocale === 'ca' ? 'Catàleg de Solucions' : ($currentLocale === 'en' ? 'Solutions Catalog' : 'Catálogo de Soluciones') }}
            @endif
        </h1>

        <p class="text-neutral-500 text-sm leading-relaxed">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedDescription($currentLocale) ?? ($isShop ? ($currentLocale === 'ca' ? 'Explora la selecció per a aquesta categoria.' : ($currentLocale === 'en' ? 'Explore the selection for this category.' : 'Explora la selección para esta categoría.')) : ($currentLocale === 'ca' ? 'Consulta especificacions per a aquesta línia.' : ($currentLocale === 'en' ? 'Review specifications for this line.' : 'Consulta especificaciones para esta línea.'))) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Productes seleccionats, minimalisme funcional i enviaments directes.' : ($currentLocale === 'en' ? 'Selected products, functional minimalism, and prompt delivery.' : 'Productos seleccionados, minimalismo funcional y envíos directos.') }}
            @else
                {{ $currentLocale === 'ca' ? 'Consulta el nostre catàleg i demana proposta personalitzada.' : ($currentLocale === 'en' ? 'Browse our catalog and request a custom quote.' : 'Consulta nuestro catálogo y solicita propuesta personalizada.') }}
            @endif
        </p>
    </header>

    <!-- Products Grid -->
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($products as $prod)
            @php
                $trans = $prod->translations->where('locale', $currentLocale)->first();
                $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                $detailUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$trans?->slug}", $currentMarket);
            @endphp
            <div class="minimal-card flex flex-col justify-between overflow-hidden group hover:border-neutral-400 transition-colors">
                <div>
                    <!-- Product Image -->
                    <div class="relative h-56 bg-neutral-100 overflow-hidden border-b border-neutral-100">
                        @if($prod->featured_image)
                            <img src="{{ $prod->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-3xl text-neutral-300">
                                ◻
                            </div>
                        @endif

                        @if($prod->is_featured)
                            <span class="absolute top-2.5 left-2.5 px-2 py-0.5 bg-neutral-900 text-white font-mono text-[9px] uppercase tracking-widest">
                                {{ $currentLocale === 'ca' ? 'DESTACAT' : ($currentLocale === 'en' ? 'FEATURED' : 'DESTACADO') }}
                            </span>
                        @endif

                        <span class="absolute top-2.5 right-2.5 px-2 py-0.5 bg-white border border-neutral-200 text-neutral-600 text-[9px] font-mono">
                            {{ $prod->sku }}
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-6 space-y-3">
                        <div class="text-[10px] text-neutral-400 uppercase font-mono tracking-widest">
                            {{ $prod->category?->localizedName($currentLocale) ?? 'Design' }}
                        </div>

                        <h3 class="text-base font-medium text-neutral-900 group-hover:underline leading-snug">
                            <a href="{{ $detailUrl }}">
                                {{ $trans?->name }}
                            </a>
                        </h3>

                        <p class="text-neutral-500 text-xs line-clamp-2 leading-relaxed font-light">
                            {{ $trans?->summary ?? ($currentLocale === 'ca' ? 'Disseny acurat i materials seleccionats.' : ($currentLocale === 'en' ? 'Carefully designed with selected materials.' : 'Diseño cuidado y materiales seleccionados.')) }}
                        </p>
                    </div>
                </div>

                <!-- Card Footer & Pricing -->
                <div class="p-6 pt-0 border-t border-neutral-100 mt-4 flex items-center justify-between font-mono">
                    <div>
                        @if(!empty($showPrices))
                            <span class="text-base font-semibold text-neutral-900">
                                {{ $prod->getFormattedPrice($currentMarket) }}
                            </span>
                            @if($prod->compare_price)
                                <span class="text-xs text-neutral-400 line-through ml-1.5">
                                    {{ number_format($prod->compare_price, 2) }} €
                                </span>
                            @endif
                        @else
                            <span class="text-xs font-semibold text-neutral-500">
                                {{ $currentLocale === 'ca' ? 'Consultar disponibilitat' : ($currentLocale === 'en' ? 'Check availability' : 'Consultar disponibilidad') }}
                            </span>
                        @endif
                    </div>

                    @php
                        $buyBtnText = $isShop
                            ? ($currentLocale === 'ca' ? 'Comprar' : ($currentLocale === 'en' ? 'Buy' : 'Comprar'))
                            : ($currentLocale === 'ca' ? 'Detalls' : ($currentLocale === 'en' ? 'Details' : 'Detalles'));
                    @endphp
                    <a href="{{ $detailUrl }}" class="px-3 py-1.5 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-[11px] uppercase tracking-wider transition-colors">
                        <span>{{ $buyBtnText }} &rarr;</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-16 text-center minimal-card font-mono text-neutral-400 text-xs uppercase tracking-widest">{{ __('Sin productos en esta selección') }}</div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($products->hasPages())
        <div class="pt-6 flex justify-center font-mono text-xs">
            {{ $products->links() }}
        </div>
    @endif

</div>
@endsection
