@extends('themes::db-brutalist.layouts.app')

@php
    $trans = $post->translations->where('locale', app()->getLocale())->first()
        ?? $post->translations->first();
    $marketCode = $currentMarket?->code ?? 'es';
    $markdownRenderer = app(\App\Services\Media\MarkdownRendererService::class);
    $renderedBody = $trans ? $markdownRenderer->render($trans->content) : '';
@endphp

@section('title', ($trans->meta_title ?? $trans->title ?? __('Artículo')) . ' - dbCMS Brutalist')
@section('meta_description', $trans->meta_description ?? $trans->summary ?? '')

@section('content')
<div class="max-w-4xl mx-auto space-y-12 font-mono" @if(!empty($post)) data-cms-edit-url="{{ route('admin.blog.posts.edit', $post->id) }}" data-cms-edit-label="Editar Artículo" @endif>

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Article Header -->
    <header class="space-y-6 border-b-3 border-black pb-10">
        <div class="flex items-center gap-3 text-xs font-black text-black">
            <span class="px-3 py-1 bg-[#FFE600] border-2 border-black shadow-[2px_2px_0px_0px_#000] uppercase">
                {{ $post->category?->translations->first()?->name ?? 'Manifiesto' }}
            </span>
            <span>&bull;</span>
            <span>{{ $post->published_at ? $post->published_at->format('d/m/Y') : now()->format('d/m/Y') }}</span>
            <span>&bull;</span>
            <span><i class="fa-solid fa-clock mr-1"></i> {{ $post->reading_time_minutes }} MIN</span>
        </div>

        <h1 class="text-3xl sm:text-5xl lg:text-6xl font-black text-black tracking-tight leading-none uppercase">
            {{ $trans->title ?? '' }}
        </h1>

        @if($trans->summary)
            <p class="text-base sm:text-lg text-black font-bold border-l-4 border-black pl-4 py-1 leading-relaxed bg-[#00F0FF]/30 p-3 border-2 border-black shadow-[3px_3px_0px_0px_#000]">
                {{ $trans->summary }}
            </p>
        @endif

        <div class="flex items-center space-x-3 pt-2 text-xs text-black font-black">
            <div class="w-10 h-10 bg-black text-white flex items-center justify-center font-black text-lg border-2 border-black shadow-[2px_2px_0px_0px_#FFE600]">
                {{ substr($post->author->name ?? 'B', 0, 1) }}
            </div>
            <div>
                <span class="text-black font-black block uppercase">{{ $post->author->name ?? 'Equipo dbCMS' }}</span>
                <span class="text-black/60 text-[11px]">{{ __('Diseño & Arquitectura Brutal') }}</span>
            </div>
        </div>
    </header>

    <!-- Featured Image -->
    @if($post->featured_image)
        <div class="border-3 border-black shadow-[6px_6px_0px_0px_#000] overflow-hidden">
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
        <article class="brutalist-card p-8 sm:p-12 text-black leading-relaxed space-y-6 max-w-none text-base font-medium">
            {!! $renderedBody !!}
        </article>
    @endif

    @if($post->allowsComments())
    <!-- Comments Section -->
    <section class="mt-16 space-y-8 pt-8 border-t-3 border-black">
        <div class="flex items-center justify-between pb-4 border-b-2 border-black">
            <h2 class="text-2xl font-black text-black uppercase flex items-center gap-2">
                <span>Comentarios ({{ $post->comments->where('is_approved', true)->count() }})</span>
            </h2>
        </div>

        <!-- Comments List -->
        <div class="space-y-4">
            @forelse($post->comments->where('is_approved', true) as $comment)
                <div class="brutalist-card p-5 space-y-2 text-xs">
                    <div class="flex items-center justify-between text-black font-black">
                        <span class="bg-[#FFE600] px-2 py-0.5 border border-black">{{ $comment->author_name }}</span>
                        <span class="text-[10px]">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    <p class="text-black font-bold leading-relaxed">{{ $comment->content }}</p>
                </div>
            @empty
                <div class="text-center py-8 text-black font-black text-xs uppercase">{{ __('Sé el primero en dejar una opinión radical.') }}</div>
            @endforelse
        </div>

        <!-- Comment Form -->
        <div class="brutalist-card p-8 space-y-4 text-xs font-black">
            <h3 class="text-base font-black text-black uppercase">{{ __('Añadir Comentario') }}</h3>

            <form method="POST" action="{{ url("/{$marketCode}/" . app()->getLocale() . '/blog/' . ($trans->slug ?? '') . '/comments') }}" class="space-y-4">
                @csrf

                <x-cms-form.fields
                    :schema="app(\App\Core\Forms\FormSchemas::class)->blogComment()"
                    variant="comment-brutalist"
                    layout="grid"
                    honeypot="_hp_security_check"
                />

                <div class="pt-2">
                    <button type="submit" class="btn-brutalist px-6 py-3 text-xs">
                        Publicar Comentario &rarr;
                    </button>
                </div>
            </form>
        </div>
    </section>
    @else
    <section class="mt-16 p-6 border-4 border-black bg-[#EFEFEF] text-center font-black text-xs" data-scroll-magic>
        <i class="fa-solid fa-comment-slash mr-1"></i>{{ __('Los comentarios están desactivados para este artículo.') }}</section>
    @endif

</div>

<!-- Schema.org Article JSON-LD -->
@endsection
