@extends('themes.db-sunset.layouts.app')

@php
    $trans = $post->translations->where('locale', app()->getLocale())->first()
        ?? $post->translations->first();
    $marketCode = $currentMarket?->code ?? 'es';
    $markdownRenderer = app(\App\Services\Media\MarkdownRendererService::class);
    $renderedBody = $trans ? $markdownRenderer->render($trans->content) : '';
@endphp

@section('title', ($trans->meta_title ?? $trans->title ?? __('Artículo')) . ' - dbCMS Sunset')
@section('meta_description', $trans->meta_description ?? $trans->summary ?? '')

@section('content')
<div class="max-w-4xl mx-auto space-y-12" @if(!empty($post)) data-cms-edit-url="{{ route('admin.blog.posts.edit', $post->id) }}" data-cms-edit-label="Editar Artículo" @endif>

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Article Header -->
    <header class="space-y-6 border-b border-white/10 pb-10">
        <div class="flex items-center gap-3 text-xs font-semibold text-rose-400">
            <span class="px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/30">
                {{ $post->category?->translations->first()?->name ?? 'Tendencias' }}
            </span>
            <span>&bull;</span>
            <span class="text-slate-400">{{ $post->published_at ? $post->published_at->format('d \d\e F, Y') : now()->format('d \d\e F, Y') }}</span>
            <span>&bull;</span>
            <span class="text-slate-400"><i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} min de lectura</span>
        </div>

        <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tight leading-tight">
            {{ $trans->title ?? '' }}
        </h1>

        @if($trans->summary)
            <p class="text-base sm:text-lg text-slate-300 border-l-2 border-rose-500 pl-4 py-1 leading-relaxed">
                {{ $trans->summary }}
            </p>
        @endif

        <div class="flex items-center space-x-3 pt-2 text-xs text-slate-400">
            <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-rose-500 to-amber-500 flex items-center justify-center text-white font-bold text-sm">
                {{ substr($post->author->name ?? 'S', 0, 1) }}
            </div>
            <div>
                <span class="text-white font-semibold block">{{ $post->author->name ?? 'Equipo dbCMS' }}</span>
                <span class="text-slate-500 text-[11px]">{{ __('Diseño & Estrategia') }}</span>
            </div>
        </div>
    </header>

    <!-- Featured Image -->
    @if($post->featured_image)
        <div class="rounded-2xl overflow-hidden border border-white/10 shadow-2xl">
            <img src="{{ $post->featured_image }}" alt="{{ $trans->title ?? '' }}" class="w-full max-h-[480px] object-cover" loading="eager" fetchpriority="high" decoding="async">
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
        <article class="sunset-card p-8 sm:p-12 text-slate-200 leading-relaxed space-y-6 max-w-none text-sm">
            {!! $renderedBody !!}
        </article>
    @endif

    @if($post->allowsComments())
    <!-- Comments Section -->
    <section class="mt-16 space-y-8 pt-8 border-t border-white/10">
        <div class="flex items-center justify-between pb-4 border-b border-white/10">
            <h2 class="text-xl font-bold text-white flex items-center gap-2">
                <span>Comentarios ({{ $post->comments->where('is_approved', true)->count() }})</span>
            </h2>
        </div>

        <!-- Comments List -->
        <div class="space-y-4">
            @forelse($post->comments->where('is_approved', true) as $comment)
                <div class="sunset-card p-5 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-slate-400">
                        <span class="text-rose-400 font-semibold">{{ $comment->author_name }}</span>
                        <span class="text-[10px]">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-slate-300 leading-relaxed">{{ $comment->content }}</p>
                </div>
            @empty
                <div class="text-center py-8 text-slate-500 text-xs">{{ __('No hay comentarios todavía. Comparte tu perspectiva.') }}</div>
            @endforelse
        </div>

        <!-- Comment Form -->
        <div class="sunset-card p-6 space-y-4 text-xs">
            <h3 class="text-sm font-bold text-white">{{ __('Dejar un Comentario') }}</h3>

            <form method="POST" action="{{ url("/{$marketCode}/" . app()->getLocale() . '/blog/' . ($trans->slug ?? '') . '/comments') }}" class="space-y-4">
                @csrf

                <x-cms-form.fields
                    :schema="app(\App\Core\Forms\FormSchemas::class)->blogComment()"
                    variant="comment-sunset"
                    layout="grid"
                    honeypot="_hp_security_check"
                />

                <div class="pt-2">
                    <button type="submit" class="btn-sunset px-5 py-2.5 text-xs">
                        Publicar Comentario &rarr;
                    </button>
                </div>
            </form>
        </div>
    </section>
    @else
    <section class="mt-16 p-6 bg-white/5 border border-white/10 rounded-2xl text-center text-slate-400 font-sans text-xs" data-scroll-magic>
        <i class="fa-solid fa-comment-slash mr-1"></i>{{ __('Los comentarios están desactivados para este artículo.') }}</section>
    @endif

</div>

<!-- Schema.org Article JSON-LD -->
@endsection
