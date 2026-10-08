@php
    $layout = app(\App\Services\Cms\FrontViewResolver::class)->layout();
@endphp
@extends($layout)

@section('title', ($isShop ? __('Tienda Online') : __('Catálogo de Productos')) . ' - dbCMS')
@section('meta_description', __('Explora nuestro catálogo de productos técnicos con arquitectura modular y optimización multi-mercado.'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-12">

    <!-- Hero Header -->
    <header class="text-center max-w-3xl mx-auto space-y-4 font-mono" data-scroll-magic>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#00FF99]/10 border border-[#00FF99]/30 text-[#00FF99] text-xs font-bold uppercase tracking-wider">
            <span>
                @if($isShop)
                    <i class="fa-solid fa-cart-shopping mr-1"></i>{{ $currentLocale === 'ca' ? 'Botiga E-Commerce' : ($currentLocale === 'en' ? 'E-Commerce Shop' : 'Tienda E-Commerce') }}
                @else
                    <i class="fa-solid fa-book-open mr-1"></i>{{ $currentLocale === 'ca' ? 'Catàleg Oficial' : ($currentLocale === 'en' ? 'Official Catalog' : 'Catálogo Oficial') }}
                @endif
            </span>
            <span>•</span>
            <span>{{ strtoupper($currentMarket->code) }} ({{ $currentMarket->currency }})</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedName($currentLocale) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Equipament & Productes' : ($currentLocale === 'en' ? 'Hardware & Products' : 'Equipamiento & Productos') }}
            @else
                {{ $currentLocale === 'ca' ? 'Catàleg de Solucions' : ($currentLocale === 'en' ? 'Solutions Catalog' : 'Catálogo de Soluciones') }}
            @endif
        </h1>
        <p class="text-gray-400 text-sm sm:text-base leading-relaxed">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedDescription($currentLocale) ?? ($isShop ? ($currentLocale === 'ca' ? 'Explora els nostres productes i solucions per a aquesta categoria tècnica.' : ($currentLocale === 'en' ? 'Explore our technical products and solutions for this category.' : 'Explora nuestros productos y soluciones para esta categoría técnica.')) : ($currentLocale === 'ca' ? 'Consulta especificacions i solucions per a aquesta categoria.' : ($currentLocale === 'en' ? 'Check specifications and solutions for this category.' : 'Consulta especificaciones y soluciones para esta categoría.'))) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Hardware, llicències i mòduls per a arquitectures modernes. Pagaments segurs i lliuraments immediats.' : ($currentLocale === 'en' ? 'Hardware, licenses and modules for modern architectures. Secure payments and instant delivery.' : 'Hardware, licencias y módulos para arquitecturas modernas. Pagos seguros y entregas inmediatas.') }}
            @else
                {{ $currentLocale === 'ca' ? 'Consulta les nostres solucions tècniques i sol·licita pressupost personalitzat amb especificacions detallades.' : ($currentLocale === 'en' ? 'Check our technical solutions and request a custom quote with detailed specifications.' : 'Consulta nuestras soluciones técnicas y solicita presupuesto personalizado con especificaciones detalladas.') }}
            @endif
        </p>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="cyber-card p-5 font-mono text-xs" data-scroll-magic>
        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center justify-between gap-4">
            <!-- Category Pills -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ url()->current() }}"
                   class="px-3 py-1.5 rounded-lg border transition-all {{ empty($selectedCategory) ? 'bg-[#00FF99] text-black font-bold border-[#00FF99]' : 'bg-[#181818] border-gray-800 text-gray-400 hover:text-white hover:border-gray-700' }}">
                    {{ $currentLocale === 'ca' ? 'Tots' : ($currentLocale === 'en' ? 'All' : 'Todos') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ url()->current() }}?category={{ $cat->id }}"
                       class="px-3 py-1.5 rounded-lg border transition-all flex items-center gap-1.5 {{ $selectedCategory === $cat->id ? 'bg-[#00FF99] text-black font-bold border-[#00FF99]' : 'bg-[#181818] border-gray-800 text-gray-400 hover:text-white hover:border-gray-700' }}">
                        @if($cat->icon && str_contains($cat->icon, 'fa-'))
                            <i class="{{ $cat->icon }}"></i>
                        @elseif($cat->icon && !preg_match('/[\x{10000}-\x{10FFFF}]/u', $cat->icon))
                            <span>{{ $cat->icon }}</span>
                        @else
                            <i class="fa-solid fa-tag"></i>
                        @endif
                        <span>{{ $cat->localizedName($currentLocale) }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Search input -->
            <div class="flex items-center gap-2">
                <input type="text" name="q" value="{{ $searchQuery }}" placeholder="{{ $currentLocale === 'ca' ? 'Cercar producte...' : ($currentLocale === 'en' ? 'Search product...' : 'Buscar producto...') }}" class="bg-[#181818] border border-gray-700 focus:border-[#00FF99] rounded-lg px-3 py-1.5 text-white text-xs w-48">
                <button type="submit" class="btn-neon text-xs py-1.5 px-3">{{ $currentLocale === 'ca' ? 'Cercar' : ($currentLocale === 'en' ? 'Search' : 'Buscar') }}</button>
            </div>
        </form>
    </div>

    <!-- Products Grid -->
    {{-- Curated intro (CMS block manager) --}}
@if(!empty($filterIntro))
    <div class="cms-filter-intro space-y-6 mb-8">
        {!! $filterIntro !!}
    </div>
@endif

<x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" variant="light" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($products as $prod)
            @php
                $trans = $prod->translations->where('locale', $currentLocale)->first() ?? $prod->translations->first();
                $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                $detailUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$trans?->slug}", $currentMarket);
            @endphp
            <div class="cyber-card flex flex-col justify-between overflow-hidden group hover:border-[#00FF99]/60 transition-all duration-300" data-scroll-magic>
                <div>
                    <!-- Product Image -->
                    <div class="relative h-56 bg-black overflow-hidden border-b border-gray-800">
                        @if($prod->featured_image)
                            <img src="{{ $prod->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-gray-600 bg-[#121212]">
                                <i class="fa-solid fa-box-open"></i>
                            </div>
                        @endif

                        @if($prod->is_featured)
                            <span class="absolute top-3 left-3 px-2 py-0.5 rounded bg-[#00FF99] text-black font-bold text-[10px] font-mono">
                                {{ $currentLocale === 'ca' ? 'DESTACAT' : ($currentLocale === 'en' ? 'FEATURED' : 'DESTACADO') }}
                            </span>
                        @endif

                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded bg-black/80 border border-gray-700 text-gray-300 text-[10px] font-mono">
                            {{ $prod->sku }}
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-6 space-y-3 font-mono">
                        <div class="text-[11px] text-[#00FF99] uppercase font-bold">
                            {{ $prod->category?->localizedName($currentLocale) ?? 'General' }}
                        </div>

                        <h3 class="text-lg font-bold text-white group-hover:text-[#00FF99] transition-colors leading-snug">
                            <a href="{{ $detailUrl }}">
                                {{ $trans?->name }}
                            </a>
                        </h3>

                        <p class="text-gray-400 text-xs line-clamp-2 leading-relaxed">
                            {{ $trans?->summary ?? ($currentLocale === 'ca' ? 'Especificacions tècniques disponibles a la fitxa del producte.' : ($currentLocale === 'en' ? 'Technical specifications available on the product page.' : 'Especificaciones técnicas disponibles en la ficha del producto.')) }}
                        </p>
                    </div>
                </div>

                <!-- Card Footer & Pricing -->
                <div class="p-6 pt-0 border-t border-gray-800/80 mt-4 flex items-center justify-between font-mono">
                    <div>
                        @if(!empty($showPrices))
                            <span class="text-lg font-bold text-white">
                                {{ $prod->getFormattedPrice($currentMarket) }}
                            </span>
                            @if($prod->compare_price)
                                <span class="text-xs text-gray-500 line-through ml-1.5">
                                    {{ number_format($prod->compare_price, 2) }} €
                                </span>
                            @endif
                        @else
                            <span class="text-xs text-[#00FF99] font-bold">
                                {{ $currentLocale === 'ca' ? 'Consultar preu' : ($currentLocale === 'en' ? 'Pricing on request' : 'Consultar disponibilidad') }}
                            </span>
                        @endif
                    </div>

                    @php
                        $buyBtnText = $isShop
                            ? ($currentLocale === 'ca' ? 'Comprar' : ($currentLocale === 'en' ? 'Buy' : 'Comprar'))
                            : ($currentLocale === 'ca' ? 'Veure Fitxa' : ($currentLocale === 'en' ? 'View Details' : 'Ver Ficha'));
                    @endphp
                    <a href="{{ $detailUrl }}" class="btn-neon text-xs py-1.5 px-3">
                        <span>{{ $buyBtnText }} &rarr;</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-16 text-center cyber-card font-mono text-gray-400 space-y-3">
                <span class="text-4xl block text-gray-600"><i class="fa-solid fa-magnifying-glass"></i></span>
                <p class="text-base text-white font-bold">
                    {{ $currentLocale === 'ca' ? 'No s\'han trobat productes en aquesta categoria o cerca.' : ($currentLocale === 'en' ? 'No products found in this category or search.' : 'No se encontraron productos en esta categoría o búsqueda.') }}
                </p>
                <a href="{{ url()->current() }}" class="btn-neon text-xs inline-block mt-2">
                    {{ $currentLocale === 'ca' ? 'Veure Tots els Productes' : ($currentLocale === 'en' ? 'View All Products' : 'Ver Todos los Productos') }}
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($products->hasPages())
        <div class="pt-6 font-mono text-xs flex justify-center">
            {{ $products->links() }}
        </div>
    @endif

</div>
@endsection
