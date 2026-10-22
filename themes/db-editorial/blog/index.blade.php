@extends('themes::db-editorial.layouts.app')

@section('title', __('Artículos & Publicaciones - dbCMS'))
@section('meta_description', __('Ensayos, reflexiones y artículos de fondo sobre diseño, tecnología y arquitectura digital.'))

@section('content')
<div class="max-w-6xl mx-auto space-y-12">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    @php
        $currentLocale = app()->getLocale();
        $editorialTitle = match($currentLocale) {
            'ca' => 'Articles & Publicacions',
            'en' => 'Articles & Essays',
            default => 'Artículos & Publicaciones',
        };
        $editorialSubtitle = match($currentLocale) {
            'ca' => 'Reflexions de fons, anàlisis d\'arquitectura i lectures seleccionades sobre tecnologia i disseny.',
            'en' => 'In-depth essays, architectural analysis, and curated readings on technology and modern design.',
            default => 'Reflexiones de fondo, análisis de arquitectura y lecturas seleccionadas sobre tecnología y diseño.',
        };
        $allText = match($currentLocale) {
            'ca' => 'Tots els Articles',
            'en' => 'All Articles',
            default => 'Todos los Artículos',
        };
        $readText = match($currentLocale) {
            'ca' => 'Llegir article',
            'en' => 'Read essay',
            default => 'Leer artículo',
        };
        $emptyText = match($currentLocale) {
            'ca' => 'No s\'han trobat articles publicats per a aquest mercat o idioma.',
            'en' => 'No articles found for this market or language.',
            default => 'No se encontraron artículos publicados para este mercado o idioma.',
        };
    @endphp

    <!-- Editorial Header -->
    <header class="border-b border-stone-200 pb-8 space-y-3">
        <span class="text-xs font-sans uppercase tracking-widest text-amber-700 font-semibold">{{ __('Crónicas & Ensayos') }}</span>
        <h1 class="text-4xl sm:text-5xl font-serif font-bold text-stone-900 tracking-tight">
            {{ $editorialTitle }}
        </h1>
        <p class="text-stone-600 font-serif text-lg max-w-2xl leading-relaxed">
            {{ $editorialSubtitle }}
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
                <article class="editorial-card flex flex-col justify-between overflow-hidden group hover:shadow-md transition-shadow">
                    <div>
                        <!-- Featured Image -->
                        <div class="h-52 w-full bg-stone-100 overflow-hidden relative border-b border-stone-100">
                            @if($post->featured_image)
                                <img src="{{ $post->featured_image }}" alt="{{ $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center font-serif italic text-2xl text-stone-300 bg-stone-100">
                                    dbCMS
                                </div>
                            @endif
                            <div class="absolute top-3 right-3 bg-white/90 backdrop-blur px-2.5 py-1 rounded text-[11px] font-sans text-stone-700 border border-stone-200">
                                <i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} min
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6 space-y-3">
                            <div class="text-[11px] text-stone-400 font-sans uppercase tracking-wider flex items-center gap-2">
                                <span>{{ $post->published_at ? $post->published_at->format('d M, Y') : now()->format('d M, Y') }}</span>
                                <span>&bull;</span>
                                <span>{{ $post->author->name ?? 'Redacción' }}</span>
                            </div>

                            <h2 class="text-xl font-serif font-bold text-stone-900 group-hover:text-amber-800 transition-colors leading-snug">
                                <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}">
                                    {{ $trans->title }}
                                </a>
                            </h2>

                            <p class="text-xs font-serif text-stone-600 line-clamp-3 leading-relaxed">
                                {{ $trans->summary ?? substr(strip_tags($trans->content), 0, 140) }}
                            </p>
                        </div>
                    </div>

                    <div class="px-6 pb-6 pt-3 border-t border-stone-100 flex items-center justify-between text-xs font-medium">
                        <span class="text-stone-400">
                            {{ $post->comments_count ?? 0 }} comentarios
                        </span>
                        <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}" class="text-amber-800 font-semibold group-hover:underline flex items-center gap-1">
                            <span>{{ $readText }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </article>
            @endif
        @empty
            <div class="col-span-3 text-center py-20 editorial-card">
                <p class="text-sm font-serif text-stone-500">{{ $emptyText }}</p>
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
