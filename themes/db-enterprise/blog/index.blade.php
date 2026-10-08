@extends('themes.db-enterprise.layouts.app')

@section('title', __('Insights & Whitepapers - dbCMS Enterprise'))
@section('meta_description', __('Publicaciones de ingeniería, análisis de arquitectura cloud, gobernanza multi-mercado y optimización de rendimiento empresarial.'))

@section('content')
<div class="max-w-7xl mx-auto space-y-12">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    @php
        $currentLocale = app()->getLocale();
        $enterpriseTitle = match($currentLocale) {
            'ca' => 'Articles Tècnics & Coneixement Enterprise',
            'en' => 'Technical Insights & Enterprise Whitepapers',
            default => 'Artículos Técnicos & Conocimiento Enterprise',
        };
        $enterpriseSubtitle = match($currentLocale) {
            'ca' => 'Recerca aplicada, arquitectura distribuïda, conformitat RGPD i sistemes preparats per a models d\'intel·ligència artificial.',
            'en' => 'Applied research, distributed architecture, GDPR compliance, and enterprise systems ready for generative AI models.',
            default => 'Investigación aplicada, arquitectura distribuida, conformidad RGPD y sistemas preparados para modelos de inteligencia artificial.',
        };
        $allText = match($currentLocale) {
            'ca' => 'Tots els Articles',
            'en' => 'All Insights',
            default => 'Todos los Artículos',
        };
        $readText = match($currentLocale) {
            'ca' => 'Llegir informe',
            'en' => 'Read whitepaper',
            default => 'Leer informe',
        };
        $emptyText = match($currentLocale) {
            'ca' => 'No s\'han trobat articles disponibles per a aquest mercat o idioma.',
            'en' => 'No technical articles found for this market or language.',
            default => 'No se encontraron artículos disponibles para este mercado o idioma.',
        };
    @endphp

    <!-- Enterprise Header -->
    <header class="border-b border-slate-800 pb-8 space-y-3">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-semibold uppercase tracking-wider">
            <span><i class="fa-solid fa-bolt text-indigo-400 mr-1"></i> Enterprise Research</span>
            <span>&bull;</span>
            <span>{{ strtoupper($currentMarket->code) }}</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-bold text-white tracking-tight">
            {{ $enterpriseTitle }}
        </h1>
        <p class="text-slate-400 text-base max-w-3xl leading-relaxed">
            {{ $enterpriseSubtitle }}
        </p>
    </header>

    <!-- Articles Grid -->
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($posts as $post)
            @php
                $trans = $post->translations->where('locale', app()->getLocale())->first() ?? $post->translations->first();
            @endphp
            @if($trans)
                <article class="bg-slate-900 border border-slate-800 rounded-2xl flex flex-col justify-between overflow-hidden group hover:border-indigo-500/50 hover:shadow-xl hover:shadow-indigo-500/5 transition-all">
                    <div>
                        <!-- Featured Image -->
                        <div class="h-48 w-full bg-slate-950 overflow-hidden relative border-b border-slate-800">
                            @if($post->featured_image)
                                <img src="{{ $post->featured_image }}" alt="{{ $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center font-bold text-2xl text-slate-700 bg-slate-950">
                                    dbEnterprise
                                </div>
                            @endif
                            <div class="absolute top-3 right-3 bg-slate-950/80 backdrop-blur px-2.5 py-1 rounded-md text-[10px] font-semibold text-indigo-300 border border-indigo-500/20">
                                <i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} min read
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6 space-y-3">
                            <div class="text-[11px] text-slate-500 uppercase tracking-wider flex items-center gap-2">
                                <span>{{ $post->published_at ? $post->published_at->format('d M, Y') : now()->format('d M, Y') }}</span>
                                <span>&bull;</span>
                                <span>{{ $post->author->name ?? 'Ingeniería dbCMS' }}</span>
                            </div>

                            <h2 class="text-lg font-bold text-white group-hover:text-indigo-400 transition-colors leading-snug">
                                <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}">
                                    {{ $trans->title }}
                                </a>
                            </h2>

                            <p class="text-xs text-slate-400 line-clamp-3 leading-relaxed">
                                {{ $trans->summary ?? substr(strip_tags($trans->content), 0, 140) }}
                            </p>
                        </div>
                    </div>

                    <div class="px-6 pb-6 pt-3 border-t border-slate-800/80 flex items-center justify-between text-xs">
                        <span class="text-slate-500">
                            {{ $post->comments_count ?? 0 }} notas
                        </span>
                        <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}" class="text-indigo-400 font-semibold group-hover:underline flex items-center gap-1">
                            <span>{{ $readText }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </article>
            @endif
        @empty
            <div class="col-span-3 text-center py-20 bg-slate-900 border border-slate-800 rounded-2xl">
                <p class="text-sm text-slate-400">{{ $emptyText }}</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if(method_exists($posts, 'links'))
        <div class="pt-6">
            {{ $posts->links() }}
        </div>
    @endif

</div>
@endsection
