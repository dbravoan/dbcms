<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Blog Feed | {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased">
    <div class="max-w-6xl mx-auto px-4 py-12">
        <header class="mb-10 text-center">
            <h1 class="text-4xl font-extrabold tracking-tight text-slate-900 sm:text-5xl">Articles & Knowledge Base</h1>
            <p class="mt-3 text-xl text-slate-500">Market: <span class="uppercase font-bold">{{ $currentMarket->code }}</span> | Locale: <span class="font-bold">{{ app()->getLocale() }}</span></p>
        </header>

        {{-- Curated intro (CMS block manager) --}}
@if(!empty($filterIntro))
    <div class="cms-filter-intro space-y-6 mb-8">
        {!! $filterIntro !!}
    </div>
@endif

<x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" variant="light" />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            @forelse($posts as $post)
                @php $t = $post->translations->where('locale', app()->getLocale())->first() ?? $post->translations->first(); @endphp
                <article class="bg-white rounded-2xl shadow-sm hover:shadow-md transition-shadow border border-slate-100 overflow-hidden flex flex-col">
                    @if($post->featured_image)
                        <img src="{{ $post->featured_image }}" alt="{{ $t->title ?? '' }}" class="h-48 w-full object-cover">
                    @endif
                    <div class="p-6 flex-1 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center gap-2 text-xs font-semibold text-indigo-600 mb-2">
                                <span>{{ $post->category->translations->first()->name ?? 'Uncategorized' }}</span>
                                <span>•</span>
                                <span>{{ $post->reading_time_minutes }} min read</span>
                            </div>
                            <h2 class="text-xl font-bold text-slate-900 hover:text-indigo-600 mb-3">
                                <a href="{{ url($currentMarket->code . '/' . app()->getLocale() . '/blog/' . ($t->slug ?? $post->id)) }}">{{ $t->title ?? 'Untitled Post' }}</a>
                            </h2>
                            <p class="text-slate-600 text-sm line-clamp-3 mb-4">{{ $t->summary ?? '' }}</p>
                        </div>
                        <div class="pt-4 border-t border-slate-100 text-xs text-slate-400">
                            By {{ $post->author->name ?? 'Staff' }} on {{ $post->published_at?->format('M d, Y') }}
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-3 text-center py-12 text-slate-400">No articles available for this market and language combination.</div>
            @endforelse
        </div>

        <div class="mt-12">
            {{ $posts->links() }}
        </div>
    </div>
</body>
</html>
