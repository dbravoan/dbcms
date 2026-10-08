@extends('themes.db-brutalist.layouts.app')

@php
    $currentLocale = app()->getLocale();
    $catTrans = $category->translations->where('locale', $currentLocale)->first()
        ?? $category->translations->first();
    $catFallbackName = match($currentLocale) {
        'ca' => 'Categoria',
        'en' => 'Category',
        default => 'Categoría',
    };
    $readArticleText = match($currentLocale) {
        'ca' => 'Llegir',
        'en' => 'Read',
        default => 'Leer',
    };
    $emptyCatText = match($currentLocale) {
        'ca' => 'No hi ha articles en aquesta categoria encara.',
        'en' => 'No articles in this category yet.',
        default => 'No hay artículos en esta categoría todavía.',
    };
@endphp

@section('title', ($catTrans?->name ?? $catFallbackName) . ' - dbCMS Brutalist')
@section('meta_description', $catTrans?->description ?? '')

@section('content')
<div class="space-y-12 font-mono">

    <!-- Breadcrumbs Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Title -->
    <header class="space-y-3">
        <div class="inline-block px-3 py-1 bg-[#FF5A5F] text-white font-black text-xs border-2 border-black shadow-[3px_3px_0px_0px_#000] uppercase">{{ __('Categoría') }}</div>
        <h1 class="text-4xl sm:text-6xl font-black text-black tracking-tight uppercase">
            {{ $catTrans?->name ?? $catFallbackName }}
        </h1>
        @if($catTrans?->description)
            <p class="text-black font-bold text-sm max-w-2xl leading-relaxed bg-[#FFE600] p-3 border-2 border-black shadow-[4px_4px_0px_0px_#000]">
                {{ $catTrans->description }}
            </p>
        @endif
    </header>

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
                <article class="brutalist-card flex flex-col justify-between overflow-hidden group">
                    <div>
                        <div class="h-48 w-full bg-black overflow-hidden relative border-b-3 border-black">
                            @if($post->featured_image)
                                <img src="{{ $post->featured_image }}" alt="{{ $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                            @else
                                <div class="w-full h-full flex items-center justify-center font-black text-2xl text-[#FFE600] bg-black">
                                    [ BRUTAL ]
                                </div>
                            @endif
                            <div class="absolute top-3 right-3 bg-[#FFE600] px-2.5 py-1 text-[10px] font-black text-black border-2 border-black shadow-[2px_2px_0px_0px_#000]">
                                <i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} MIN
                            </div>
                        </div>

                        <div class="p-6 space-y-3">
                            <div class="text-[10px] text-black font-black uppercase tracking-wider flex items-center gap-2">
                                <span>{{ $post->published_at ? $post->published_at->format('d/m/Y') : now()->format('d/m/Y') }}</span>
                                <span>&bull;</span>
                                <span>{{ $post->author->name ?? 'Equipo' }}</span>
                            </div>

                            <h2 class="text-lg font-black text-black group-hover:text-[#FF5A5F] transition-colors leading-snug uppercase">
                                <a href="{{ $postUrl }}">
                                    {{ $trans->title }}
                                </a>
                            </h2>

                            <p class="text-xs text-black font-semibold line-clamp-3 leading-relaxed">
                                {{ $trans->summary ?? substr(strip_tags($trans->content), 0, 140) }}
                            </p>
                        </div>
                    </div>

                    <div class="px-6 pb-6 pt-3 border-t-2 border-black flex items-center justify-between text-xs font-black">
                        <span class="text-black">
                            {{ $post->comments_count ?? 0 }} NOTAS
                        </span>
                        <a href="{{ $postUrl }}" class="btn-brutalist px-3 py-1 text-[11px] flex items-center gap-1">
                            <span>{{ $readArticleText }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </article>
            @endif
        @empty
            <div class="col-span-3 text-center py-20 brutalist-card font-black text-sm">
                {{ $emptyCatText }}
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
