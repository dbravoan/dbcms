@extends('themes.db-sunset.layouts.app')

@section('title', ($isShop ? __('Tienda Aurora') : __('Catálogo de Productos')) . ' - dbCMS Sunset')
@section('meta_description', __('Explora nuestro catálogo de productos con diseño aurora, pagos seguros y soporte multi-mercado.'))

@section('content')
<div class="space-y-12">

    <!-- Hero Header -->
    <header class="text-center max-w-3xl mx-auto space-y-4">
        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold uppercase tracking-wider">
            <span>
                @if($isShop)
                    {{ $currentLocale === 'ca' ? 'Botiga Aurora' : ($currentLocale === 'en' ? 'Aurora Store' : __('Tienda Aurora')) }}
                @else
                    {{ $currentLocale === 'ca' ? 'Catàleg Oficial' : ($currentLocale === 'en' ? 'Official Showcase' : 'Catálogo Oficial') }}
                @endif
            </span>
            <span>&bull;</span>
            <span>{{ strtoupper($currentMarket->code) }} ({{ $currentMarket->currency }})</span>
        </div>

        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedName($currentLocale) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Equipament & Productes' : ($currentLocale === 'en' ? 'Products & Gear' : 'Equipamiento & Productos') }}
            @else
                {{ $currentLocale === 'ca' ? 'Catàleg de Solucions' : ($currentLocale === 'en' ? 'Solutions Catalog' : 'Catálogo de Soluciones') }}
            @endif
        </h1>

        <p class="text-slate-400 text-base leading-relaxed">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedDescription($currentLocale) ?? ($isShop ? ($currentLocale === 'ca' ? 'Explora la nostra selecció per a aquesta categoria.' : ($currentLocale === 'en' ? 'Explore our curated selection for this category.' : 'Explora nuestra selección para esta categoría.')) : ($currentLocale === 'ca' ? 'Consulta informació tècnica per a aquesta categoria.' : ($currentLocale === 'en' ? 'Review technical specifications for this category.' : 'Consulta información técnica para esta categoría.'))) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Tecnologia d\'avantguarda, disseny cuidat i passarel·la de pagament ràpida i segura.' : ($currentLocale === 'en' ? 'Cutting-edge tech, crafted design, and seamless checkout.' : 'Tecnología de vanguardia, diseño cuidado y pasarela de pago rápida y segura.') }}
            @else
                {{ $currentLocale === 'ca' ? 'Descobreix les nostres solucions i demana pressupost a mida.' : ($currentLocale === 'en' ? 'Discover our solutions and request a custom quote.' : 'Descubre nuestras soluciones y solicita presupuesto a medida.') }}
            @endif
        </p>
    </header>

    <!-- Filters & Search Toolbar -->
    <div class="sunset-card p-4">
        <form method="GET" action="{{ url()->current() }}" class="flex flex-wrap items-center justify-between gap-4">
            <!-- Category Pills -->
            <div class="flex flex-wrap items-center gap-2 text-xs font-medium">
                <a href="{{ url()->current() }}"
                   class="px-4 py-2 rounded-xl border transition-all {{ empty($selectedCategory) ? 'bg-gradient-to-r from-rose-500 to-amber-500 text-white border-transparent font-bold shadow-md shadow-rose-500/20' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white hover:border-white/20' }}">
                    {{ $currentLocale === 'ca' ? 'Tots' : ($currentLocale === 'en' ? 'All' : 'Todos') }}
                </a>
                @foreach($categories as $cat)
                    <a href="{{ url()->current() }}?category={{ $cat->id }}"
                       class="px-4 py-2 rounded-xl border transition-all flex items-center gap-1.5 {{ $selectedCategory === $cat->id ? 'bg-gradient-to-r from-rose-500 to-amber-500 text-white border-transparent font-bold shadow-md shadow-rose-500/20' : 'bg-white/5 border-white/10 text-slate-400 hover:text-white hover:border-white/20' }}">
                        <span>{{ $cat->icon ?? '' }}</span>
                        <span>{{ $cat->localizedName($currentLocale) }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Search input -->
            <div class="flex items-center gap-2">
                <input type="text" name="q" value="{{ $searchQuery }}" placeholder="{{ $currentLocale === 'ca' ? 'Cercar...' : ($currentLocale === 'en' ? 'Search...' : 'Buscar...') }}" class="bg-white/5 border border-white/10 focus:border-rose-500 rounded-xl px-3 py-2 text-white text-xs w-48">
                <button type="submit" class="btn-sunset px-4 py-2 text-xs">{{ $currentLocale === 'ca' ? 'Cercar' : ($currentLocale === 'en' ? 'Search' : 'Buscar') }}</button>
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
            <div class="sunset-card flex flex-col justify-between overflow-hidden group hover:scale-[1.01] transition-all duration-300">
                <div>
                    <!-- Product Image -->
                    <div class="relative h-56 bg-slate-900 overflow-hidden border-b border-white/10">
                        @if($prod->featured_image)
                            <img src="{{ $prod->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-slate-700 bg-slate-950"><i class="fa-solid fa-box"></i></div>
                        @endif

                        @if($prod->is_featured)
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-lg bg-gradient-to-r from-rose-500 to-amber-500 text-white font-bold text-[10px] shadow-lg shadow-rose-500/30">
                                {{ $currentLocale === 'ca' ? 'DESTACAT' : ($currentLocale === 'en' ? 'FEATURED' : 'DESTACADO') }}
                            </span>
                        @endif

                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded-lg bg-black/60 border border-white/10 text-slate-300 text-[10px] font-mono">
                            {{ $prod->sku }}
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-6 space-y-3">
                        <div class="text-[11px] text-rose-400 uppercase font-semibold tracking-wider">
                            {{ $prod->category?->localizedName($currentLocale) ?? 'Colección' }}
                        </div>

                        <h3 class="text-lg font-bold text-white group-hover:text-rose-400 transition-colors leading-snug">
                            <a href="{{ $detailUrl }}">
                                {{ $trans?->name }}
                            </a>
                        </h3>

                        <p class="text-slate-400 text-xs line-clamp-2 leading-relaxed">
                            {{ $trans?->summary ?? ($currentLocale === 'ca' ? 'Producte d\'alta tecnologia i disseny modern.' : ($currentLocale === 'en' ? 'High-tech product with modern aesthetics.' : 'Producto de alta tecnología y diseño moderno.')) }}
                        </p>
                    </div>
                </div>

                <!-- Card Footer & Pricing -->
                <div class="p-6 pt-0 border-t border-white/5 mt-4 flex items-center justify-between">
                    <div>
                        @if(!empty($showPrices))
                            <span class="text-lg font-bold text-white">
                                {{ $prod->getFormattedPrice($currentMarket) }}
                            </span>
                            @if($prod->compare_price)
                                <span class="text-xs text-slate-500 line-through ml-1.5">
                                    {{ number_format($prod->compare_price, 2) }} €
                                </span>
                            @endif
                        @else
                            <span class="text-xs text-rose-400 font-semibold">
                                {{ $currentLocale === 'ca' ? 'Consultar disponibilitat' : ($currentLocale === 'en' ? 'Check availability' : 'Consultar disponibilidad') }}
                            </span>
                        @endif
                    </div>

                    @php
                        $buyBtnText = $isShop
                            ? ($currentLocale === 'ca' ? 'Comprar' : ($currentLocale === 'en' ? 'Buy' : 'Comprar'))
                            : ($currentLocale === 'ca' ? 'Veure' : ($currentLocale === 'en' ? 'View' : 'Ver'));
                    @endphp
                    <a href="{{ $detailUrl }}" class="btn-sunset px-4 py-2 text-xs">
                        <span>{{ $buyBtnText }} &rarr;</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-16 text-center sunset-card text-slate-400 space-y-3">
                <span class="text-4xl block opacity-40"><i class="fa-solid fa-magnifying-glass"></i></span>
                <p class="text-base text-white font-bold">
                    {{ $currentLocale === 'ca' ? 'No s\'han trobat productes.' : ($currentLocale === 'en' ? 'No products found.' : 'No se encontraron productos.') }}
                </p>
                <a href="{{ url()->current() }}" class="btn-sunset text-xs inline-block mt-2 px-4 py-2">
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
