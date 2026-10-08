@extends('themes.db-cyber.layouts.app')

@section('title', __('Blog & Artículos Técnicos'))
@section('meta_description', __('Artículos sobre arquitectura de software, inteligencia artificial, optimización GEO y desarrollo de alto rendimiento.'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    @php
        $currentLocale = app()->getLocale();
        $titleText = $currentLocale === 'ca' ? 'Articles & Publicacions' : ($currentLocale === 'en' ? 'Articles & Publications' : 'Artículos & Publicaciones');
        $terminalLabel = $currentLocale === 'ca' ? '&gt; Terminal de Coneixement' : ($currentLocale === 'en' ? '&gt; Knowledge Terminal' : '&gt; Terminal de Conocimiento');
        $subtitleText = $currentLocale === 'ca' ? 'Explora les darreres investigacions, casos d\'estudi i guies sobre arquitectura moderna, SEO i GEO.' : ($currentLocale === 'en' ? 'Explore the latest research, case studies, and guides on modern architecture, SEO, and GEO.' : 'Explora las últimas investigaciones, casos de estudio y guías sobre arquitectura moderna, SEO y GEO.');
        $allArticlesText = $currentLocale === 'ca' ? 'Tots els Articles' : ($currentLocale === 'en' ? 'All Articles' : 'Todos los Artículos');
        $readArticleText = $currentLocale === 'ca' ? 'Llegir article' : ($currentLocale === 'en' ? 'Read article' : 'Leer artículo');
        $emptyText = $currentLocale === 'ca' ? '&gt; No s\'han trobat articles per a aquest mercat o idioma.' : ($currentLocale === 'en' ? '&gt; No articles found for this market or language.' : '&gt; No se encontraron artículos para este mercado o idioma.');
    @endphp

    <!-- Header Title -->
    <div class="mb-4" data-scroll-magic>
        <div class="text-xs font-mono text-[#00FF99] uppercase tracking-widest font-bold mb-2">
            {!! $terminalLabel !!}
        </div>
        <h1 class="text-3xl sm:text-5xl font-bold text-white tracking-tight">
            {{ $titleText }}
        </h1>
        <p class="text-sm font-mono text-gray-400 mt-3 max-w-2xl">
            {{ $subtitleText }}
        </p>
    </div>

    {{-- Curated intro (CMS block manager) --}}
@if(!empty($filterIntro))
    <div class="cms-filter-intro space-y-6 mb-8">
        {!! $filterIntro !!}
    </div>
@endif

<!-- Facet navigation: crawlable links to the canonical filter URLs -->
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <!-- Posts Grid -->
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($posts as $post)
            @php
                $trans = $post->translations->where('locale', app()->getLocale())->first()
                    ?? $post->translations->first();
            @endphp
            @if($trans)
                <article class="cyber-card flex flex-col justify-between overflow-hidden group hover:border-[#00FF99] transition-all hover:scale-[1.01]" data-scroll-magic>
                    <div>
                        <!-- Featured Image -->
                        <div class="h-48 w-full bg-[#121212] overflow-hidden relative">
                            @if($post->featured_image)
                                <img src="{{ $post->featured_image }}" alt="{{ $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center font-mono text-3xl text-gray-700 bg-gradient-to-br from-[#1A1A1A] to-[#0D0D0D]">
                                    &gt;_db
                                </div>
                            @endif
                            <div class="absolute top-3 right-3 bg-black/70 backdrop-blur-md px-2.5 py-1 rounded text-[10px] font-mono text-[#00FF99] border border-[#00FF99]/30">
                                <i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} min
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6">
                            <div class="flex items-center gap-2 text-[11px] font-mono text-gray-500 mb-2">
                                <span>{{ $post->published_at ? $post->published_at->format('d M, Y') : now()->format('d M, Y') }}</span>
                                <span>&bull;</span>
                                <span class="text-gray-400">{{ $post->author->name ?? 'Equipo dbCMS' }}</span>
                            </div>

                            <h2 class="text-lg font-bold text-white group-hover:text-[#00FF99] transition-colors leading-snug">
                                <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}">
                                    {{ $trans->title }}
                                </a>
                            </h2>

                            <p class="text-xs font-mono text-gray-400 mt-2.5 line-clamp-3 leading-relaxed">
                                {{ $trans->summary ?? substr(strip_tags($trans->content), 0, 140) }}
                            </p>
                        </div>
                    </div>

                    <div class="px-6 pb-6 pt-2 border-t border-gray-800/60 flex items-center justify-between font-mono text-xs">
                        <span class="text-[11px] text-gray-500">
                            {{ $post->comments_count ?? 0 }} comentarios
                        </span>
                        <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}" class="text-[#00FF99] font-bold group-hover:underline">
                            {{ $readArticleText }} &rarr;
                        </a>
                    </div>
                </article>
            @endif
        @empty
            <div class="col-span-3 text-center py-20 cyber-card">
                <p class="text-sm font-mono text-gray-400">{!! $emptyText !!}</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if(method_exists($posts, 'links'))
        <div class="mt-12 font-mono text-xs">
            {{ $posts->links() }}
        </div>
    @endif

</div>
@endsection
