@extends('themes.db-cyber.layouts.app')

@php
    $currentLocale = app()->getLocale();
    $catTrans = $category->translations->where('locale', $currentLocale)->first()
        ?? $category->translations->first();
    $catFallbackName = $currentLocale === 'ca' ? 'Categoria' : ($currentLocale === 'en' ? 'Category' : 'Categoría');
    $catLabel = $currentLocale === 'ca' ? '&gt; Categoria' : ($currentLocale === 'en' ? '&gt; Category' : '&gt; Categoría');
    $readArticleText = $currentLocale === 'ca' ? 'Llegir article' : ($currentLocale === 'en' ? 'Read article' : 'Leer artículo');
    $emptyCatText = $currentLocale === 'ca' ? '&gt; No hi ha articles publicats en aquesta categoria encara.' : ($currentLocale === 'en' ? '&gt; No published articles in this category yet.' : '&gt; No hay artículos publicados en esta categoría todavía.');
@endphp

@section('title', ($catTrans?->name ?? $catFallbackName) . ' - dbCMS')
@section('meta_description', $catTrans?->description ?? (($currentLocale === 'ca' ? 'Articles de la categoria ' : ($currentLocale === 'en' ? 'Articles in category ' : 'Artículos de la categoría ')) . ($catTrans?->name ?? '')))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">

    <!-- Breadcrumbs Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Title -->
    <div class="space-y-3" data-scroll-magic>
        <div class="text-xs font-mono text-[#00FF99] uppercase tracking-widest font-bold">
            {!! $catLabel !!}
        </div>
        <h1 class="text-3xl sm:text-5xl font-bold text-white tracking-tight">
            {{ $catTrans?->name ?? $catFallbackName }}
        </h1>
        @if($catTrans?->description)
            <p class="text-sm font-mono text-gray-400 max-w-2xl">
                {{ $catTrans->description }}
            </p>
        @endif
    </div>

    <!-- Posts Grid -->
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @forelse($posts as $post)
            @php
                $trans = $post->translations->where('locale', app()->getLocale())->first()
                    ?? $post->translations->first();
                $localization = app(\App\Contracts\Services\LocalizationServiceInterface::class);
                $postUrl = $localization->url('/blog/' . ($trans?->slug ?? ''), $currentMarket);
            @endphp
            @if($trans)
                <article class="cyber-card flex flex-col justify-between overflow-hidden group hover:border-[#00FF99] transition-all hover:scale-[1.01]" data-scroll-magic>
                    <div>
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

                        <div class="p-6">
                            <div class="flex items-center gap-2 text-[11px] font-mono text-gray-500 mb-2">
                                <span>{{ $post->published_at ? $post->published_at->format('d M, Y') : now()->format('d M, Y') }}</span>
                                <span>&bull;</span>
                                <span class="text-gray-400">{{ $post->author->name ?? 'Equipo dbCMS' }}</span>
                            </div>

                            <h2 class="text-lg font-bold text-white group-hover:text-[#00FF99] transition-colors leading-snug">
                                <a href="{{ $postUrl }}">
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
                        <a href="{{ $postUrl }}" class="text-[#00FF99] font-bold group-hover:underline">
                            {{ $readArticleText }} &rarr;
                        </a>
                    </div>
                </article>
            @endif
        @empty
            <div class="col-span-3 text-center py-20 cyber-card font-mono text-gray-400 text-sm">
                {!! $emptyCatText !!}
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if(method_exists($posts, 'links'))
        <div class="mt-8 font-mono text-xs">
            {{ $posts->links() }}
        </div>
    @endif
</div>
@endsection
