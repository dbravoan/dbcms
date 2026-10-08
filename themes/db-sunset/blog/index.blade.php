@extends('themes.db-sunset.layouts.app')

@section('title', __('Blog & Novedades - dbCMS Sunset'))
@section('meta_description', __('Historias de producto, tendencias de diseño, inteligencia artificial y arquitectura de alto rendimiento.'))

@section('content')
<div class="space-y-12">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    @php
        $currentLocale = app()->getLocale();
        $sunsetBlogTitle = match($currentLocale) {
            'ca' => 'Articles & Històries Aurora',
            'en' => 'Aurora Stories & Insights',
            default => 'Artículos & Historias Aurora',
        };
        $sunsetBlogSubtitle = match($currentLocale) {
            'ca' => 'Explora les darreres anàlisis sobre tecnologies digitals, models generatius i disseny web modern.',
            'en' => 'Explore the latest insights on digital technologies, generative models, and modern web design.',
            default => 'Explora los últimos análisis sobre tecnologías digitales, modelos generativos y diseño web moderno.',
        };
        $allText = match($currentLocale) {
            'ca' => 'Tots els Articles',
            'en' => 'All Stories',
            default => 'Todos los Artículos',
        };
        $readText = match($currentLocale) {
            'ca' => 'Llegir article',
            'en' => 'Read story',
            default => 'Leer artículo',
        };
        $emptyText = match($currentLocale) {
            'ca' => 'No s\'han trobat articles en aquest idioma.',
            'en' => 'No stories found for this language.',
            default => 'No se encontraron artículos en este idioma.',
        };
    @endphp

    <!-- Header Title -->
    <header class="space-y-4">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold uppercase tracking-wider">
            <span><i class="fa-solid fa-wand-magic-sparkles text-rose-400 mr-1"></i> Aurora Insights</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight">
            {{ $sunsetBlogTitle }}
        </h1>
        <p class="text-slate-400 text-base max-w-2xl leading-relaxed">
            {{ $sunsetBlogSubtitle }}
        </p>
    </header>

    <!-- Posts Grid -->
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($posts as $post)
            @php
                $trans = $post->translations->where('locale', app()->getLocale())->first() ?? $post->translations->first();
            @endphp
            @if($trans)
                <article class="sunset-card flex flex-col justify-between overflow-hidden group hover:scale-[1.01] transition-all duration-300">
                    <div>
                        <!-- Featured Image -->
                        <div class="h-48 w-full bg-slate-900 overflow-hidden relative border-b border-white/10">
                            @if($post->featured_image)
                                <img src="{{ $post->featured_image }}" alt="{{ $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center font-bold text-2xl text-slate-700 bg-gradient-to-br from-[#1a0f2e] to-[#0a0712]">
                                    <i class="fa-solid fa-sun text-rose-400 mr-1"></i> Sunset
                                </div>
                            @endif
                            <div class="absolute top-3 right-3 bg-black/60 backdrop-blur-md px-2.5 py-1 rounded-lg text-[10px] font-semibold text-rose-300 border border-rose-500/30">
                                <i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} min
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6 space-y-3">
                            <div class="text-[11px] text-slate-500 uppercase tracking-wider flex items-center gap-2">
                                <span>{{ $post->published_at ? $post->published_at->format('d M, Y') : now()->format('d M, Y') }}</span>
                                <span>&bull;</span>
                                <span>{{ $post->author->name ?? 'Equipo Sunset' }}</span>
                            </div>

                            <h2 class="text-lg font-bold text-white group-hover:text-rose-400 transition-colors leading-snug">
                                <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}">
                                    {{ $trans->title }}
                                </a>
                            </h2>

                            <p class="text-xs text-slate-400 line-clamp-3 leading-relaxed">
                                {{ $trans->summary ?? substr(strip_tags($trans->content), 0, 140) }}
                            </p>
                        </div>
                    </div>

                    <div class="px-6 pb-6 pt-3 border-t border-white/5 flex items-center justify-between text-xs">
                        <span class="text-slate-500">
                            {{ $post->comments_count ?? 0 }} notas
                        </span>
                        <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}" class="text-rose-400 font-semibold group-hover:underline flex items-center gap-1">
                            <span>{{ $readText }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </article>
            @endif
        @empty
            <div class="col-span-3 text-center py-20 sunset-card">
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
