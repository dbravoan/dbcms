@extends('themes.db-editorial.layouts.app')

@php
    $trans = $post->translations->where('locale', app()->getLocale())->first()
        ?? $post->translations->first();
    $marketCode = $currentMarket?->code ?? 'es';
    $markdownRenderer = app(\App\Services\Media\MarkdownRendererService::class);
    $renderedBody = $trans ? $markdownRenderer->render($trans->content) : '';
@endphp

@section('title', $trans->meta_title ?? $trans->title ?? __('Artículo'))
@section('meta_description', $trans->meta_description ?? $trans->summary ?? '')

@section('content')
<div class="max-w-4xl mx-auto space-y-12" @if(!empty($post)) data-cms-edit-url="{{ route('admin.blog.posts.edit', $post->id) }}" data-cms-edit-label="Editar Artículo" @endif>

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Article Header -->
    <header class="space-y-6 text-center border-b border-stone-200 pb-10">
        <div class="inline-flex items-center gap-3 text-xs font-sans uppercase tracking-widest text-amber-800 font-semibold">
            <span>{{ $post->category?->translations->first()?->name ?? 'Ensayo' }}</span>
            <span>&bull;</span>
            <span>{{ $post->published_at ? $post->published_at->format('d \d\e F, Y') : now()->format('d \d\e F, Y') }}</span>
            <span>&bull;</span>
            <span><i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} min de lectura</span>
        </div>

        <h1 class="text-4xl sm:text-5xl lg:text-6xl font-serif font-bold text-stone-900 tracking-tight leading-tight">
            {{ $trans->title ?? '' }}
        </h1>

        @if($trans->summary)
            <p class="text-lg sm:text-xl text-stone-600 font-serif italic max-w-2xl mx-auto leading-relaxed">
                {{ $trans->summary }}
            </p>
        @endif

        <div class="flex items-center justify-center space-x-3 pt-4 text-xs text-stone-600 font-sans">
            <div class="w-10 h-10 rounded-full bg-stone-200 text-stone-800 font-serif font-bold flex items-center justify-center text-sm border border-stone-300">
                {{ substr($post->author->name ?? 'A', 0, 1) }}
            </div>
            <div class="text-left">
                <span class="text-stone-900 font-semibold block">{{ $post->author->name ?? 'Redacción dbCMS' }}</span>
                <span class="text-stone-400 text-[11px]">{{ __('Investigación & Contenidos') }}</span>
            </div>
        </div>
    </header>

    <!-- Featured Image -->
    @if($post->featured_image)
        <div class="rounded-2xl overflow-hidden border border-stone-200 shadow-sm">
            <img src="{{ $post->featured_image }}" alt="{{ $trans->title ?? '' }}" class="w-full max-h-[500px] object-cover" loading="eager" fetchpriority="high" decoding="async">
        </div>
    @endif

    <!-- Article Body & Modular Sections -->
    @if(isset($renderedSections) && count($renderedSections) > 0)
        <div class="space-y-10">
            @foreach($renderedSections as $sectionHtml)
                <div>
                    {!! $sectionHtml !!}
                </div>
            @endforeach
        </div>
    @else
        <article class="editorial-card p-8 sm:p-12 font-serif text-base text-stone-800 leading-relaxed space-y-6 max-w-none">
            {!! $renderedBody !!}
        </article>
    @endif

    @if($post->allowsComments())
    <!-- Comments Section -->
    <section class="mt-16 space-y-8 pt-8 border-t border-stone-200">
        <div class="flex items-center justify-between pb-4 border-b border-stone-200">
            <h2 class="text-2xl font-serif font-bold text-stone-900">
                Conversación ({{ $post->comments->where('is_approved', true)->count() }})
            </h2>
        </div>

        <!-- Comments List -->
        <div class="space-y-4">
            @forelse($post->comments->where('is_approved', true) as $comment)
                <div class="editorial-card p-6 space-y-2">
                    <div class="flex items-center justify-between text-xs text-stone-500 font-sans">
                        <span class="font-bold text-stone-900">{{ $comment->author_name }}</span>
                        <span class="text-[11px]">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-stone-700 font-serif text-sm leading-relaxed">{{ $comment->content }}</p>
                </div>
            @empty
                <div class="text-center py-8 text-stone-400 font-serif text-sm italic">{{ __('Aún no hay comentarios en este ensayo. Sé el primero en compartir tu perspectiva.') }}</div>
            @endforelse
        </div>

        <!-- Comment Form -->
        <div class="editorial-card p-8 space-y-6">
            <h3 class="text-lg font-serif font-bold text-stone-900">{{ __('Añadir Comentario') }}</h3>

            <form method="POST" action="{{ url("/{$marketCode}/" . app()->getLocale() . '/blog/' . ($trans->slug ?? '') . '/comments') }}" class="space-y-4 text-xs font-sans">
                @csrf

                <x-cms-form.fields
                    :schema="app(\App\Core\Forms\FormSchemas::class)->blogComment(['content' => __('Tu Mensaje *')])"
                    variant="comment-editorial"
                    layout="grid"
                    honeypot="_hp_security_check"
                />

                <div class="pt-2">
                    <button type="submit" class="px-6 py-2.5 bg-stone-900 hover:bg-stone-800 text-white rounded-lg font-medium transition-colors">
                        Enviar Comentario &rarr;
                    </button>
                </div>
            </form>
        </div>
    </section>
    @else
    <section class="mt-16 p-6 bg-stone-50 border border-stone-200 rounded-xl text-center text-stone-500 font-serif text-sm" data-scroll-magic>
        <i class="fa-solid fa-comment-slash mr-1"></i>{{ __('Los comentarios están desactivados para este artículo.') }}</section>
    @endif

</div>

<!-- Schema.org Article JSON-LD -->
@endsection
