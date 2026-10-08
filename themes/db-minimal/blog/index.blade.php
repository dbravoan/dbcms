@extends('themes.db-minimal.layouts.app')

@section('title', __('Diario & Publicaciones - dbCMS'))
@section('meta_description', __('Ensayos y notas sobre diseño, arquitectura de software y minimalismo digital.'))

@section('content')
<div class="space-y-16">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    @php
        $currentLocale = app()->getLocale();
        $minimalTitle = match($currentLocale) {
            'ca' => 'Articles & Diari Digital',
            'en' => 'Journal & Articles',
            default => 'Diario & Artículos',
        };
        $minimalSubtitle = match($currentLocale) {
            'ca' => 'Apunts sobre arquitectura pura, tecnologia essencial i disseny escandinau.',
            'en' => 'Essays on pure architecture, essential technology, and Scandinavian design.',
            default => 'Apuntes sobre arquitectura pura, tecnología esencial y diseño escandinavo.',
        };
        $allText = match($currentLocale) {
            'ca' => 'Tots',
            'en' => 'All',
            default => 'Todos',
        };
        $readText = match($currentLocale) {
            'ca' => 'Llegir',
            'en' => 'Read',
            default => 'Leer',
        };
        $emptyText = match($currentLocale) {
            'ca' => 'No hi ha articles en aquest idioma.',
            'en' => 'No articles in this language.',
            default => 'No hay artículos en este idioma.',
        };
    @endphp

    <!-- Header -->
    <header class="border-b border-neutral-200/80 pb-8 space-y-3">
        <span class="text-[10px] font-mono uppercase tracking-widest text-neutral-400">Publicaciones</span>
        <h1 class="text-3xl sm:text-4xl font-light text-neutral-900 tracking-tight">
            {{ $minimalTitle }}
        </h1>
        <p class="text-neutral-500 text-sm max-w-xl leading-relaxed">
            {{ $minimalSubtitle }}
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
                <article class="minimal-card flex flex-col justify-between overflow-hidden group hover:border-neutral-400 transition-colors">
                    <div>
                        <!-- Featured Image -->
                        <div class="h-48 w-full bg-neutral-100 overflow-hidden relative border-b border-neutral-100">
                            @if($post->featured_image)
                                <img src="{{ $post->featured_image }}" alt="{{ $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center font-mono text-neutral-300 text-sm">
                                    [ Minimal ]
                                </div>
                            @endif
                            <div class="absolute top-2.5 right-2.5 bg-white/95 px-2 py-0.5 text-[9px] font-mono text-neutral-600 border border-neutral-200">
                                {{ $post->reading_time_minutes }}m
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6 space-y-3">
                            <div class="text-[10px] text-neutral-400 font-mono uppercase tracking-widest flex items-center gap-2">
                                <span>{{ $post->published_at ? $post->published_at->format('d M, Y') : now()->format('d M, Y') }}</span>
                                <span>&bull;</span>
                                <span>{{ $post->author->name ?? 'Studio' }}</span>
                            </div>

                            <h2 class="text-base font-medium text-neutral-900 group-hover:underline leading-snug">
                                <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}">
                                    {{ $trans->title }}
                                </a>
                            </h2>

                            <p class="text-xs text-neutral-500 line-clamp-3 leading-relaxed">
                                {{ $trans->summary ?? substr(strip_tags($trans->content), 0, 140) }}
                            </p>
                        </div>
                    </div>

                    <div class="px-6 pb-6 pt-3 border-t border-neutral-100 flex items-center justify-between text-[11px] font-mono">
                        <span class="text-neutral-400">
                            {{ $post->comments_count ?? 0 }}
                        </span>
                        <a href="{{ url("/{$currentMarket->code}/" . app()->getLocale() . '/blog/' . $trans->slug) }}" class="text-neutral-900 uppercase tracking-widest hover:underline flex items-center gap-1">
                            <span>{{ $readText }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </article>
            @endif
        @empty
            <div class="col-span-3 text-center py-20 minimal-card">
                <p class="text-xs font-mono text-neutral-400 uppercase tracking-widest">{{ $emptyText }}</p>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if(method_exists($posts, 'links'))
        <div class="pt-6 font-mono text-xs">
            {{ $posts->links() }}
        </div>
    @endif

</div>
@endsection
