@extends('themes.default-clean.layouts.app')

@section('title', $category->name ?? __('Category'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ $category->name ?? __('Category') }}</h1>
    {{-- Facet navigation: theme-skinned filter panel (canonical, crawlable URLs) --}}
    <x-cms-filter.bar :module="$filterModule ?? null" :set="$filterSet ?? null" :market="$currentMarket" />

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        @forelse($posts as $post)
            @php $trans = $post->translations->where('locale', $currentLocale)->first() ?? $post->translations->first(); @endphp
            <article class="border border-slate-200 rounded-xl p-5 space-y-2">
                <h2 class="font-bold text-lg">{{ $trans?->title }}</h2>
                <a href="{{ $localization->url('/blog/'.$trans?->slug, $currentMarket) }}" class="text-sm font-semibold hover:underline">{{ __('Read more') }} &rarr;</a>
            </article>
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No articles available.') }}</p>
        @endforelse
    </div>
</div>
@endsection