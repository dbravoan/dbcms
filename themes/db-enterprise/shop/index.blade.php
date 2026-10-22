@extends('themes::db-enterprise.layouts.app')

@section('title', ($isShop ? __('Soluciones & Licencias Enterprise') : __('Catálogo de Soluciones Enterprise')) . ' - dbCMS')
@section('meta_description', __('Soluciones de software empresarial, suscripciones SaaS, infraestructura cloud y soporte técnico prioritario.'))

@section('content')
<div class="max-w-7xl mx-auto space-y-12">

    <!-- Hero Header -->
    <header class="text-center max-w-3xl mx-auto space-y-4">
        <div class="inline-flex items-center gap-2 px-3.5 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-semibold uppercase tracking-wider">
            <span>
                @if($isShop)
                    {{ $currentLocale === 'ca' ? 'Plataforma de Compra B2B' : ($currentLocale === 'en' ? 'B2B Enterprise Store' : 'Tienda B2B Enterprise') }}
                @else
                    {{ $currentLocale === 'ca' ? 'Catàleg de Solucions B2B' : ($currentLocale === 'en' ? 'B2B Solutions Portfolio' : 'Catálogo de Soluciones B2B') }}
                @endif
            </span>
            <span>&bull;</span>
            <span>{{ strtoupper($currentMarket->code) }} ({{ $currentMarket->currency }})</span>
        </div>

        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedName($currentLocale) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Productes & Llicències Empresarials' : ($currentLocale === 'en' ? 'Enterprise Products & Licenses' : 'Productos & Licencias Empresariales') }}
            @else
                {{ $currentLocale === 'ca' ? 'Cartera de Solucions Corporatives' : ($currentLocale === 'en' ? 'Corporate Solutions Catalog' : 'Cartera de Soluciones Corporativas') }}
            @endif
        </h1>

        <p class="text-slate-400 text-base leading-relaxed">
            @if(!empty($selectedCategoryModel))
                {{ $selectedCategoryModel->localizedDescription($currentLocale) ?? ($isShop ? ($currentLocale === 'ca' ? 'Explora els nostres productes i solucions corporatives.' : ($currentLocale === 'en' ? 'Explore our enterprise products and solutions.' : 'Explora nuestros productos y soluciones corporativas.')) : ($currentLocale === 'ca' ? 'Consulta informació tècnica per a aquesta línia de solucions.' : ($currentLocale === 'en' ? 'Review technical specifications for this solution line.' : 'Consulta información técnica para esta línea de soluciones.'))) }}
            @elseif($isShop)
                {{ $currentLocale === 'ca' ? 'Mòduls empresarials, paquets d\'infraestructura i llicències amb suport SLA 99.9% garantit.' : ($currentLocale === 'en' ? 'Enterprise modules, infrastructure suites, and software licenses backed by 99.9% SLA guarantees.' : 'Módulos empresariales, paquetes de infraestructura y licencias con garantía SLA 99.9% de alta disponibilidad.') }}
            @else
                {{ $currentLocale === 'ca' ? 'Consulteu les nostres especificacions corporatives i sol·liciteu una proposta tècnica a mida amb contracte SLA.' : ($currentLocale === 'en' ? 'Review our corporate specifications and request a customized technical proposal with SLA contracts.' : 'Consulte nuestras especificaciones corporativas y solicite una propuesta técnica a medida con contrato SLA.') }}
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
            <div class="bg-slate-900 border border-slate-800 rounded-2xl flex flex-col justify-between overflow-hidden group hover:border-indigo-500/60 hover:shadow-xl hover:shadow-indigo-500/5 transition-all duration-300">
                <div>
                    <!-- Product Image -->
                    <div class="relative h-56 bg-slate-950 overflow-hidden border-b border-slate-800">
                        @if($prod->featured_image)
                            <img src="{{ $prod->featured_image }}" alt="{{ $trans?->name }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                        @else
                            <div class="w-full h-full flex items-center justify-center text-4xl text-slate-700 bg-slate-950"><i class="fa-solid fa-desktop"></i></div>
                        @endif

                        @if($prod->is_featured)
                            <span class="absolute top-3 left-3 px-2.5 py-1 rounded-md bg-indigo-600 text-white font-semibold text-[10px] tracking-wide">
                                {{ $currentLocale === 'ca' ? 'RECOMANAT' : ($currentLocale === 'en' ? 'FEATURED' : 'RECOMENDADO') }}
                            </span>
                        @endif

                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded bg-slate-950/80 border border-slate-700 text-slate-400 text-[10px] font-mono">
                            {{ $prod->sku }}
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-6 space-y-3">
                        <div class="text-[11px] text-indigo-400 uppercase font-semibold tracking-wider">
                            {{ $prod->category?->localizedName($currentLocale) ?? 'Enterprise' }}
                        </div>

                        <h3 class="text-lg font-bold text-white group-hover:text-indigo-400 transition-colors leading-snug">
                            <a href="{{ $detailUrl }}">
                                {{ $trans?->name }}
                            </a>
                        </h3>

                        <p class="text-slate-400 text-xs line-clamp-2 leading-relaxed">
                            {{ $trans?->summary ?? ($currentLocale === 'ca' ? 'Solució corporativa d\'alt rendiment i escalabilitat.' : ($currentLocale === 'en' ? 'High-performance corporate solution for scalable architectures.' : 'Solución corporativa de alto rendimiento y escalabilidad.')) }}
                        </p>
                    </div>
                </div>

                <!-- Card Footer & Pricing -->
                <div class="p-6 pt-0 border-t border-slate-800/80 mt-4 flex items-center justify-between">
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
                            <span class="text-xs text-indigo-400 font-semibold">
                                {{ $currentLocale === 'ca' ? 'Consultar disponibilitat' : ($currentLocale === 'en' ? 'Check availability' : 'Consultar disponibilidad') }}
                            </span>
                        @endif
                    </div>

                    @php
                        $buyBtnText = $isShop
                            ? ($currentLocale === 'ca' ? 'Contractar' : ($currentLocale === 'en' ? 'Order' : 'Contratar'))
                            : ($currentLocale === 'ca' ? 'Veure Detalls' : ($currentLocale === 'en' ? 'View Details' : 'Ver Detalles'));
                    @endphp
                    <a href="{{ $detailUrl }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold transition-colors shadow-sm">
                        <span>{{ $buyBtnText }} &rarr;</span>
                    </a>
                </div>
            </div>
        @empty
            <div class="col-span-full p-16 text-center bg-slate-900 border border-slate-800 rounded-2xl text-slate-400 space-y-3">
                <span class="text-4xl block opacity-40"><i class="fa-solid fa-magnifying-glass"></i></span>
                <p class="text-base text-white font-bold">
                    {{ $currentLocale === 'ca' ? 'No s\'han trobat solucions en aquesta cerca.' : ($currentLocale === 'en' ? 'No enterprise solutions found in this query.' : 'No se encontraron soluciones en esta búsqueda.') }}
                </p>
                <a href="{{ url()->current() }}" class="inline-block mt-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white rounded-lg text-xs font-semibold">
                    {{ $currentLocale === 'ca' ? 'Veure Totes les Solucions' : ($currentLocale === 'en' ? 'View All Solutions' : 'Ver Todas las Soluciones') }}
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
