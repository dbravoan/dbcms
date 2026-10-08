@extends('themes.db-brutalist.layouts.app')

@section('title', ($isShop ? 'Tienda Radical' : 'Catálogo Radical') . ' - dbCMS Brutalist')
@section('meta_description', __('Catálogo de hardware, piezas técnicas y licencias con diseño neo-brutalista de alto impacto.'))

@section('content')
<div class="space-y-12 font-mono">

    <!-- Hero Header -->
    <header class="text-center max-w-3xl mx-auto space-y-4">
        <div class="inline-block px-3 py-1 bg-[#FFE600] border-2 border-black shadow-[3px_3px_0px_0px_#000] text-black font-black text-xs uppercase tracking-wider">
            <span>
                @if($isShop)
                    {{ $currentLocale === 'ca' ? 'BOTIGA E-COMMERCE' : ($currentLocale === 'en' ? 'RADICAL SHOP' : 'TIENDA E-COMMERCE') }}
                @else
                    {{ $currentLocale === 'ca' ? 'CATÀLEG OFICIAL' : ($currentLocale === 'en' ? 'RADICAL CATALOG' : 'CATÁLOGO OFICIAL') }}
                @endif
            </span>
            <span>&bull;</span>
            <span>{{ strtoupper($currentMarket->code) }} ({{ $currentMarket->currency }})</span>
        </div>

        <h1 class="text-4xl sm:text-6xl font-black text-black tracking-tight uppercase">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedName($currentLocale) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'EQUIPAMENT & STOCK' : ($currentLocale === 'en' ? 'GEAR & HARDWARE' : 'EQUIPAMIENTO & STOCK') }}
            @else
                {{ $currentLocale === 'ca' ? 'SOLUCIONS DE HARDWARE' : ($currentLocale === 'en' ? 'HARDWARE SOLUTIONS' : 'SOLUCIONES DE HARDWARE') }}
            @endif
        </h1>

        <p class="text-black font-bold text-sm leading-relaxed bg-white p-3 border-2 border-black shadow-[4px_4px_0px_0px_#000]">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedDescription($currentLocale) ?? ($isShop ? ($currentLocale === 'ca' ? 'Explora la selecció per a aquesta categoria.' : ($currentLocale === 'en' ? 'Explore items in this category.' : 'Explora la selección para esta categoría.')) : ($currentLocale === 'ca' ? 'Consulta especificacions per a aquesta categoria.' : ($currentLocale === 'en' ? 'Review specifications for this category.' : 'Consulta especificaciones para esta categoría.'))) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Peces seleccionades, llicències i maquinària digital amb expedició immediata.' : ($currentLocale === 'en' ? 'Curated items, digital licenses, and hardware with immediate dispatch.' : 'Piezas seleccionadas, licencias y maquinaria digital con expedición inmediata.') }}
            @else
                {{ $currentLocale === 'ca' ? 'Revisa les especificacions del catàleg i demana pressupost immediat.' : ($currentLocale === 'en' ? 'Review catalog specifications and request an instant quote.' : 'Revisa las especificaciones del catálogo y solicita presupuesto inmediato.') }}
            @endif
        </p>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="brutalist-card p-4">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center justify-between gap-4">
            <!-- Category Pills -->
            <div class="flex flex-wrap items-center gap-2 text-xs font-black uppercase">
                <a href="{{ url()->current() }}"
                   class="px-4 py-2 border-2 border-black transition-all {{ empty($selectedCategory) ? 'bg-black text-white shadow-[3px_3px_0px_0px_#FFE600]' : 'bg-white text-black shadow-[2px_2px_0px_0px_#000] hover:bg-[#FFE600]' }}">
                    {{ $currentLocale === 'ca' ? 'TOTS' : ($currentLocale === 'en' ? 'ALL' : 'TODOS') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ url()->current() }}?category={{ $cat->id }}"
                       class="px-4 py-2 border-2 border-black transition-all flex items-center gap-1.5 {{ $selectedCategory === $cat->id ? 'bg-black text-white shadow-[3px_3px_0px_0px_#FFE600]' : 'bg-white text-black shadow-[2px_2px_0px_0px_#000] hover:bg-[#00F0FF]' }}">
                        <span>{{ $cat->icon ?? '' }}</span>
                        <span>{{ $cat->localizedName($currentLocale) }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Search input -->
            <div class="flex items-center gap-2">
                <input type="text" name="q" value="{{ $searchQuery }}" placeholder="{{ $currentLocale === 'ca' ? 'Cercar...' : ($currentLocale === 'en' ? 'Search...' : 'Buscar...') }}" class="bg-white border-2 border-black rounded px-3 py-1.5 text-black text-xs w-48 font-bold">
                <button type="submit" class="btn-brutalist px-4 py-1.5 text-xs">{{ $currentLocale === 'ca' ? 'Cercar' : ($currentLocale === 'en' ? 'Search' : 'Buscar') }}</button>
            </div>
        </form>
    </div>

    <!-- Products Grid -->
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($products as $prod)
            @php
                $trans = $prod->translations->where('locale', $currentLocale)->first() ?? $prod->translations->first();
                $shopPrefix = app(\App\Contracts\Services\LocalizationServiceInterface::class)->getModulePrefix('shop', $currentLocale) ?: 'shop';
                $detailUrl = app(\App\Contracts\Services\LocalizationServiceInterface::class)->url("/{$shopPrefix}/{$trans?->slug}", $currentMarket);
            @endphp
            <div class="brutalist-card flex flex-col justify-between overflow-hidden group">
                <div>
                    <!-- Product Image -->
                    <div class="relative h-56 bg-black overflow-hidden border-b-3 border-black">
                        @if($prod->featured_image)
                            <img src="{{ $prod->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-[#FFE600] font-black">
                                [ PROD ]
                            </div>
                        @endif

                        @if($prod->is_featured)
                            <span class="absolute top-3 left-3 px-2.5 py-1 bg-[#FFE600] text-black font-black text-[10px] border-2 border-black shadow-[2px_2px_0px_0px_#000]">
                                {{ $currentLocale === 'ca' ? 'TOP' : ($currentLocale === 'en' ? 'TOP' : 'TOP') }}
                            </span>
                        @endif

                        <span class="absolute top-3 right-3 px-2 py-0.5 bg-white text-black font-black text-[10px] border-2 border-black">
                            {{ $prod->sku }}
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-6 space-y-3">
                        <div class="text-[10px] text-black font-black uppercase tracking-wider bg-[#00F0FF] inline-block px-2 py-0.5 border border-black">
                            {{ $prod->category?->localizedName($currentLocale) ?? 'Stock' }}
                        </div>

                        <h3 class="text-xl font-black text-black group-hover:text-[#FF5A5F] transition-colors leading-snug uppercase">
                            <a href="{{ $detailUrl }}">
                                {{ $trans?->name }}
                            </a>
                        </h3>

                        <p class="text-black font-semibold text-xs line-clamp-2 leading-relaxed">
                            {{ $trans?->summary ?? ($currentLocale === 'ca' ? 'Especificacions disponibles a la fitxa.' : ($currentLocale === 'en' ? 'Specifications available on product file.' : 'Especificaciones disponibles en la ficha.')) }}
                        </p>
                    </div>
                </div>

                <!-- Card Footer & Pricing -->
                <div class="p-6 pt-0 border-t-2 border-black mt-4 flex items-center justify-between">
                    <div>
                        @if(!empty($showPrices))
                            <span class="text-xl font-black text-black">
                                {{ $prod->getFormattedPrice($currentMarket) }}
                            </span>
                            @if($prod->compare_price)
                                <span class="text-xs text-black/50 line-through ml-1.5">
                                    {{ number_format($prod->compare_price, 2) }} €
                                </span>
                            @endif
                        @else
                            <span class="text-xs font-black text-black uppercase">
                                {{ $currentLocale === 'ca' ? 'Consultar disponibilitat' : ($currentLocale === 'en' ? 'Check availability' : 'Consultar disponibilidad') }}
                            </span>
                        @endif
                    </div>

                    @php
                        $buyBtnText = $isShop
                            ? ($currentLocale === 'ca' ? 'COMPRAR' : ($currentLocale === 'en' ? 'BUY' : 'COMPRAR'))
                            : ($currentLocale === 'ca' ? 'VEURE' : ($currentLocale === 'en' ? 'VIEW' : 'VER'));
                    @endphp
                    <a href="{{ $detailUrl }}" class="btn-brutalist px-4 py-2 text-xs">
                        <span>{{ $buyBtnText }} &rarr;</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-16 text-center brutalist-card font-black text-black space-y-3">
                <span class="text-4xl block opacity-40"><i class="fa-solid fa-magnifying-glass"></i></span>
                <p class="text-base font-black uppercase">
                    {{ $currentLocale === 'ca' ? 'Sense productes trobats.' : ($currentLocale === 'en' ? 'No items found.' : 'Sin productos encontrados.') }}
                </p>
                <a href="{{ url()->current() }}" class="btn-brutalist text-xs inline-block mt-2 px-4 py-2">
                    {{ $currentLocale === 'ca' ? 'Veure Tots' : ($currentLocale === 'en' ? 'View All' : 'Ver Todos') }}
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if($products->hasPages())
        <div class="pt-6 flex justify-center">
            {{ $products->links() }}
        </div>
    @endif

</div>
@endsection
