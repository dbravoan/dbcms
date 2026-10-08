@extends('themes.db-editorial.layouts.app')

@section('title', ($isShop ? 'Tienda & Publicaciones' : 'Catálogo de Ediciones') . ' - Editorial dbCMS')
@section('meta_description', __('Colección de productos editoriales, ediciones especiales y obras curadas.'))

@section('content')
<div class="max-w-6xl mx-auto space-y-12">

    <!-- Hero Header -->
    <header class="text-center max-w-3xl mx-auto space-y-4">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-stone-100 border border-stone-200 text-stone-700 text-xs font-sans font-medium uppercase tracking-wider">
            <span>
                @if($isShop)
                    {{ $currentLocale === 'ca' ? 'Botiga Editorial' : ($currentLocale === 'en' ? 'Editorial Store' : 'Tienda Editorial') }}
                @else
                    {{ $currentLocale === 'ca' ? 'Catàleg d\'Edicions' : ($currentLocale === 'en' ? 'Editions Catalog' : 'Catálogo de Ediciones') }}
                @endif
            </span>
            <span>&bull;</span>
            <span>{{ strtoupper($currentMarket->code) }} ({{ $currentMarket->currency }})</span>
        </div>

        <h1 class="text-4xl sm:text-5xl font-serif font-bold text-stone-900 tracking-tight">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedName($currentLocale) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Col·lecció & Publicacions' : ($currentLocale === 'en' ? 'Collection & Publications' : 'Colección & Publicaciones') }}
            @else
                {{ $currentLocale === 'ca' ? 'Catàleg d\'Obre i Projectes' : ($currentLocale === 'en' ? 'Works & Projects Catalog' : 'Catálogo de Obras y Proyectos') }}
            @endif
        </h1>

        <p class="text-stone-600 font-serif text-lg leading-relaxed">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedDescription($currentLocale) ?? ($isShop ? ($currentLocale === 'ca' ? 'Explora la nostra selecció per a aquesta categoria.' : ($currentLocale === 'en' ? 'Explore our curated selection for this category.' : 'Explora nuestra selección para esta categoría.')) : ($currentLocale === 'ca' ? 'Consulta informació detallada sobre aquesta categoria.' : ($currentLocale === 'en' ? 'Review detailed information for this category.' : 'Consulta información detallada sobre esta categoría.'))) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Edicions cuidades, exemplars impresos i solucions digitals. Enviament ràpid i pagament segur.' : ($currentLocale === 'en' ? 'Carefully curated editions, print copies and digital assets. Fast delivery and secure checkout.' : 'Ediciones cuidadas, ejemplares impresos y soluciones digitales. Envío rápido y pago seguro.') }}
            @else
                {{ $currentLocale === 'ca' ? 'Consulta el nostre catàleg d\'obres i sol·licita informació personalitzada per a comandes i distribució.' : ($currentLocale === 'en' ? 'Review our catalog of works and request custom quotes for orders and licensing.' : 'Consulta nuestro catálogo de obras y solicita información personalizada para pedidos y distribución.') }}
            @endif
        </p>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="editorial-card p-4">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center justify-between gap-4">
            <!-- Category Pills -->
            <div class="flex flex-wrap items-center gap-2 text-xs font-sans">
                <a href="{{ url()->current() }}"
                   class="px-4 py-2 rounded-full border transition-all {{ empty($selectedCategory) ? 'bg-stone-900 text-white border-stone-900 font-medium' : 'bg-white border-stone-200 text-stone-600 hover:border-stone-400' }}">
                    {{ $currentLocale === 'ca' ? 'Tots' : ($currentLocale === 'en' ? 'All' : 'Todos') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ url()->current() }}?category={{ $cat->id }}"
                       class="px-4 py-2 rounded-full border transition-all flex items-center gap-1.5 {{ $selectedCategory === $cat->id ? 'bg-stone-900 text-white border-stone-900 font-medium' : 'bg-white border-stone-200 text-stone-600 hover:border-stone-400' }}">
                        <span>{!! !empty($cat->icon) ? (str_starts_with($cat->icon, 'fa-') ? '<i class="'.$cat->icon.'"></i>' : $cat->icon) : '<i class="fa-solid fa-tag"></i>' !!}</span>
                        <span>{{ $cat->localizedName($currentLocale) }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Search input -->
            <div class="flex items-center gap-2">
                <input type="text" name="q" value="{{ $searchQuery }}" placeholder="{{ $currentLocale === 'ca' ? 'Cercar obra...' : ($currentLocale === 'en' ? 'Search title...' : 'Buscar título...') }}" class="bg-white border border-stone-300 focus:border-stone-500 rounded-lg px-3 py-1.5 text-stone-900 text-xs w-48 font-sans">
                <button type="submit" class="px-4 py-1.5 bg-stone-900 hover:bg-stone-800 text-white rounded-lg text-xs font-medium font-sans">{{ $currentLocale === 'ca' ? 'Cercar' : ($currentLocale === 'en' ? 'Search' : 'Buscar') }}</button>
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
            <div class="editorial-card flex flex-col justify-between overflow-hidden group hover:shadow-lg transition-all duration-300">
                <div>
                    <!-- Product Image -->
                    <div class="relative h-60 bg-stone-100 overflow-hidden border-b border-stone-100">
                        @if($prod->featured_image)
                            <img src="{{ $prod->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-stone-300"><i class="fa-solid fa-book-open"></i></div>
                        @endif

                        @if($prod->is_featured)
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded bg-stone-900 text-white font-sans font-medium text-[10px] tracking-wide">
                                {{ $currentLocale === 'ca' ? 'DESTACAT' : ($currentLocale === 'en' ? 'FEATURED' : 'DESTACADO') }}
                            </span>
                        @endif

                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded bg-white/90 border border-stone-200 text-stone-600 text-[10px] font-sans">
                            {{ $prod->sku }}
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-6 space-y-3">
                        <div class="text-[11px] text-amber-800 uppercase font-sans font-semibold tracking-wider">
                            {{ $prod->category?->localizedName($currentLocale) ?? 'Editorial' }}
                        </div>

                        <h3 class="text-xl font-serif font-bold text-stone-900 group-hover:text-amber-800 transition-colors leading-snug">
                            <a href="{{ $detailUrl }}">
                                {{ $trans?->name }}
                            </a>
                        </h3>

                        <p class="text-stone-600 font-serif text-xs line-clamp-2 leading-relaxed">
                            {{ $trans?->summary ?? ($currentLocale === 'ca' ? 'Edició d\'alta qualitat amb continguts seleccionats.' : ($currentLocale === 'en' ? 'High-quality edition with curated contents.' : 'Edición de alta calidad con contenidos seleccionados.')) }}
                        </p>
                    </div>
                </div>

                <!-- Card Footer & Pricing -->
                <div class="p-6 pt-0 border-t border-stone-100 mt-4 flex items-center justify-between font-sans">
                    <div>
                        @if(!empty($showPrices))
                            <span class="text-xl font-serif font-bold text-stone-900">
                                {{ $prod->getFormattedPrice($currentMarket) }}
                            </span>
                            @if($prod->compare_price)
                                <span class="text-xs text-stone-400 line-through ml-1.5">
                                    {{ number_format($prod->compare_price, 2) }} €
                                </span>
                            @endif
                        @else
                            <span class="text-xs font-serif italic text-stone-600 font-semibold">
                                {{ $currentLocale === 'ca' ? 'Consultar disponibilitat' : ($currentLocale === 'en' ? 'Check availability' : 'Consultar disponibilidad') }}
                            </span>
                        @endif
                    </div>

                    @php
                        $buyBtnText = $isShop
                            ? ($currentLocale === 'ca' ? 'Adquirir' : ($currentLocale === 'en' ? 'Purchase' : 'Comprar'))
                            : ($currentLocale === 'ca' ? 'Veure Fitxa' : ($currentLocale === 'en' ? 'View Details' : 'Ver Ficha'));
                    @endphp
                    <a href="{{ $detailUrl }}" class="px-4 py-2 bg-stone-900 hover:bg-stone-800 text-white rounded-lg text-xs font-medium transition-colors">
                        <span>{{ $buyBtnText }} &rarr;</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-16 text-center editorial-card font-serif text-stone-500 space-y-3">
                <span class="text-4xl block opacity-40"><i class="fa-solid fa-magnifying-glass"></i></span>
                <p class="text-lg text-stone-900 font-bold">
                    {{ $currentLocale === 'ca' ? 'No s\'han trobat títols en aquesta selecció.' : ($currentLocale === 'en' ? 'No items found in this selection.' : 'No se encontraron títulos en esta selección.') }}
                </p>
                <a href="{{ url()->current() }}" class="inline-block mt-2 px-4 py-2 bg-stone-900 text-white rounded-lg text-xs font-sans font-medium">
                    {{ $currentLocale === 'ca' ? 'Veure Tots els Títols' : ($currentLocale === 'en' ? 'View All Titles' : 'Ver Todos los Títulos') }}
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
