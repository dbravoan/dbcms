@extends('themes::db-minimal.layouts.app')

@php
    $trans = $post->translations->where('locale', app()->getLocale())->first()
        ?? $post->translations->first();
    $marketCode = $currentMarket?->code ?? 'es';
    $markdownRenderer = app(\App\Services\Media\MarkdownRendererService::class);
    $renderedBody = $trans ? $markdownRenderer->render($trans->content) : '';
@endphp

@section('title', ($trans->meta_title ?? $trans->title ?? __('Artículo')) . ' - dbCMS Minimal')
@section('meta_description', $trans->meta_description ?? $trans->summary ?? '')

@section('content')
<div class="max-w-3xl mx-auto space-y-12" @if(!empty($post)) data-cms-edit-url="{{ route('admin.blog.posts.edit', $post->id) }}" data-cms-edit-label="Editar Artículo" @endif>

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Article Header -->
    <header class="space-y-4 border-b border-neutral-200/80 pb-8">
        <div class="flex items-center gap-3 text-[10px] font-mono uppercase tracking-widest text-neutral-400">
            <span>{{ $post->category?->translations->first()?->name ?? 'Minimal' }}</span>
            <span>&bull;</span>
            <span>{{ $post->published_at ? $post->published_at->format('d M, Y') : now()->format('d M, Y') }}</span>
            <span>&bull;</span>
            <span>{{ $post->reading_time_minutes }}m</span>
        </div>

        <h1 class="text-3xl sm:text-4xl font-light text-neutral-900 tracking-tight leading-tight">
            {{ $trans->title ?? '' }}
        </h1>

        @if($trans->summary)
            <p class="text-base text-neutral-500 font-light leading-relaxed">
                {{ $trans->summary }}
            </p>
        @endif

        <div class="flex items-center space-x-3 pt-2 text-[11px] font-mono text-neutral-400">
            <span>Por: {{ $post->author->name ?? 'dbCMS Studio' }}</span>
        </div>
    </header>

    <!-- Featured Image -->
    @if($post->featured_image)
        <div class="border border-neutral-200 overflow-hidden">
            <img src="{{ $post->featured_image }}" alt="{{ $trans->title ?? '' }}" class="w-full max-h-[460px] object-cover" loading="eager" fetchpriority="high" decoding="async">
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
        <article class="p-4 sm:p-6 text-neutral-800 text-sm leading-relaxed space-y-6 max-w-none font-light">
            {!! $renderedBody !!}
        </article>
    @endif

    @if($post->allowsComments())
    <!-- Comments Section -->
    <section class="mt-16 space-y-8 pt-8 border-t border-neutral-200/80 font-sans">
        <div class="flex items-center justify-between pb-3 border-b border-neutral-100">
            <h2 class="text-sm font-mono uppercase tracking-widest text-neutral-900">
                Comentarios ({{ $post->comments->where('is_approved', true)->count() }})
            </h2>
        </div>

        <!-- Comments List -->
        <div class="space-y-4">
            @forelse($post->comments->where('is_approved', true) as $comment)
                <div class="minimal-card p-5 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-neutral-400 font-mono text-[10px]">
                        <span class="text-neutral-900 font-semibold">{{ $comment->author_name }}</span>
                        <span>{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-neutral-600 leading-relaxed font-light">{{ $comment->content }}</p>
                </div>
            @empty
                <div class="text-center py-8 text-neutral-400 font-mono text-xs uppercase tracking-widest">
                    Sin intervenciones
                </div>
            @endforelse
        </div>

        <!-- Comment Form -->
        <div class="minimal-card p-6 space-y-4 text-xs font-sans">
            <h3 class="text-xs font-mono uppercase tracking-widest text-neutral-900">{{ __('Publicar Nota') }}</h3>

            <form method="POST" action="{{ url("/{$marketCode}/" . app()->getLocale() . '/blog/' . ($trans->slug ?? '') . '/comments') }}" class="space-y-4">
                @csrf

                <x-cms-form.fields
                    :schema="app(\App\Core\Forms\FormSchemas::class)->blogComment(['content' => __('Texto *')])"
                    variant="comment-minimal"
                    layout="grid"
                    honeypot="_hp_security_check"
                />

                <div class="pt-2">
                    <button type="submit" class="px-5 py-2 bg-neutral-900 hover:bg-neutral-800 text-white rounded text-xs font-mono uppercase tracking-wider transition-colors">
                        Enviar
                    </button>
                </div>
            </form>
        </div>
    </section>
    @else
    <section class="mt-16 p-6 border border-neutral-200 rounded text-center text-neutral-400 font-mono text-xs" data-scroll-magic>
        <i class="fa-solid fa-comment-slash mr-1"></i>{{ __('Los comentarios están desactivados para este artículo.') }}</section>
    @endif

</div>

<!-- Schema.org Article JSON-LD -->
@endsection
