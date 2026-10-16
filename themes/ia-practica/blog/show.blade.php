@extends('themes::ia-practica.layouts.app')
@php
    $trans = $post->translations->firstWhere('locale', app()->getLocale());
    $categoryTranslation = $post->category?->translations->firstWhere('locale', app()->getLocale());
    $renderedBody = $trans ? app(\App\Services\Media\MarkdownRendererService::class)->render($trans->content) : '';
@endphp
@section('title', $trans?->meta_title ?? $trans?->title ?? __('Guía práctica'))
@section('meta_description', $trans?->meta_description ?? $trans?->summary ?? '')

@section('content')
<article class="ia-article" data-cms-edit-url="{{ route('admin.blog.posts.edit', $post->id) }}" data-cms-edit-label="{{ __('Editar guía') }}">
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])
    <header class="ia-article-heading">
        <div class="ia-post-meta">
            @if($categoryTranslation)<span>{{ $categoryTranslation->name }}</span>@endif
            <span>{{ $post->reading_time_minutes }} {{ __('min de lectura') }}</span>
        </div>
        <h1>{{ $trans?->title }}</h1>
        @if($trans?->summary)<p class="ia-article-summary">{{ $trans->summary }}</p>@endif
        <div class="ia-byline">
            <span>{{ $post->author?->name ?? __('Redacción') }}</span>
            @if($post->published_at)<time datetime="{{ $post->published_at->toDateString() }}">{{ $post->published_at->translatedFormat('j \d\e F \d\e Y') }}</time>@endif
        </div>
    </header>
    @if($post->featured_image)
        <img class="ia-article-image" src="{{ $post->featured_image }}" alt="{{ $trans?->title }}" loading="eager" fetchpriority="high" decoding="async">
    @endif
    <div class="ia-article-content">
        @if(isset($renderedSections) && count($renderedSections) > 0)
            @foreach($renderedSections as $sectionHtml)<div class="ia-section">{!! $sectionHtml !!}</div>@endforeach
        @else
            <div class="ia-prose">{!! $renderedBody !!}</div>
        @endif
    </div>
    <div class="ia-article-end"><a href="{{ $localization->url('/blog', $currentMarket) }}">← {{ __('Explorar más guías') }}</a></div>
    @if($post->allowsComments())
        <section class="ia-comments" aria-labelledby="comentarios">
            <h2 id="comentarios">{{ __('Conversación') }}</h2>
            @foreach($post->comments->where('is_approved', true) as $comment)
                <article class="ia-comment"><strong>{{ $comment->author_name }}</strong><p>{{ $comment->content }}</p></article>
            @endforeach
            <form method="POST" action="{{ $localization->url('/blog/'.($trans?->slug ?? '').'/comments', $currentMarket) }}">
                @csrf
                <x-cms-form.fields :schema="app(\App\Core\Forms\FormSchemas::class)->blogComment(['content' => __('Tu comentario *')])" variant="comment-editorial" layout="grid" honeypot="_hp_security_check" />
                <button class="cms-btn cms-btn-primary" type="submit">{{ __('Enviar comentario') }}</button>
            </form>
        </section>
    @endif
</article>
@endsection
