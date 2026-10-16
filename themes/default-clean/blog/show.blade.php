@extends('themes::default-clean.layouts.app')

@section('title', $translation?->meta_title ?? $translation?->title ?? '')
@section('meta_description', $translation?->meta_description ?? '')

@section('content')
<article class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-6">
    <h1 class="text-3xl font-bold">{{ $translation?->title }}</h1>
    <div class="prose max-w-none">{!! app(\App\Services\Media\MarkdownRendererService::class)->renderHtml((string) $translation?->content) !!}</div>
</article>
@endsection