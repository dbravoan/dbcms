<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $translation->meta_title ?? $translation->title }} | {{ config('app.name') }}</title>
    <meta name="description" content="{{ $translation->meta_description ?? $translation->summary }}">

    {{-- Automated Multi-Directional Hreflang Cluster --}}
    @foreach($hreflangs as $hreflangTag => $url)
        <link rel="alternate" hreflang="{{ $hreflangTag }}" href="{{ $url }}" />
    @endforeach
    <link rel="canonical" href="{{ url()->current() }}" />

    {{-- Schema.org JSON-LD --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased">
    <article class="max-w-4xl mx-auto px-4 py-12">
        <nav class="mb-8 flex items-center gap-2 text-sm text-slate-500">
            <a href="{{ url($currentMarket->code . '/' . app()->getLocale() . '/blog') }}" class="hover:text-indigo-600">{{ __('Blog') }}</a>
            <span>/</span>
            <span>{{ $post->category?->translations->first()?->name ?? 'General' }}</span>
        </nav>

        <header class="mb-10">
            <div class="flex items-center gap-3 text-sm text-indigo-600 font-semibold mb-3">
                <span>{{ $post->category?->translations->first()?->name ?? 'Article' }}</span>
                <span>•</span>
                <span>{{ $post->reading_time_minutes }} min read</span>
                <span>•</span>
                <span>Market: {{ strtoupper($currentMarket->code) }}</span>
            </div>
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl mb-4">{{ $translation->title }}</h1>
            <div class="text-sm text-slate-400">Published by {{ $post->author->name ?? 'Staff' }} on {{ $post->published_at?->format('F d, Y') }}</div>
        </header>

        @if($post->featured_image)
            <img src="{{ $post->featured_image }}" alt="{{ $translation->title }}" class="rounded-3xl shadow-lg mb-10 w-full h-96 object-cover">
        @endif

        @if(isset($renderedSections) && count($renderedSections) > 0)
            <div class="space-y-8">
                @foreach($renderedSections as $sectionHtml)
                    {!! $sectionHtml !!}
                @endforeach
            </div>
        @else
            <div class="prose prose-lg prose-slate max-w-none bg-white p-8 md:p-12 rounded-3xl shadow-sm border border-slate-100">
                {!! app(\App\Services\Media\MarkdownRendererService::class)->render((string) $translation->content) !!}
            </div>
        @endif

        @if($post->allowsComments())
        {{-- Comments Section --}}
        <section class="mt-16 pt-10 border-t border-slate-200">
            <h3 class="text-2xl font-bold mb-6">Discussion & Comments ({{ $post->comments->count() }})</h3>
            <div class="space-y-4">
                @forelse($post->comments as $comment)
                    <div class="bg-white p-6 rounded-2xl border border-slate-100 shadow-sm">
                        <div class="font-bold text-sm text-slate-800">{{ $comment->author_name ?? 'Anonymous' }}</div>
                        <div class="text-xs text-slate-400 mb-2">{{ $comment->created_at?->diffForHumans() }}</div>
                        <p class="text-slate-600 text-sm">{{ $comment->content }}</p>
                    </div>
                @empty
                    <p class="text-slate-400 text-sm">No comments yet. Be the first to share your thoughts!</p>
                @endforelse
            </div>
        </section>
        @else
        <section class="mt-16 pt-10 border-t border-slate-200 text-center text-slate-400 text-sm">
            <i class="fa-solid fa-comment-slash mr-1"></i>{{ __('Los comentarios están desactivados para este artículo.') }}</section>
        @endif
    </article>
</body>
</html>
