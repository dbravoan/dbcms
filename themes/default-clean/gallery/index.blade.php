@extends('themes.default-clean.layouts.app')

@section('title', __('Gallery'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ __('Gallery') }}</h1>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($galleries as $gallery)
            @php
                $trans = $gallery->translations->where('locale', $currentLocale)->first() ?? $gallery->translations->first();
                $cover = $gallery->items->first();
            @endphp
            <a href="{{ $localization->url('/'.$galPrefix.'/'.$trans?->slug, $currentMarket) }}" class="group block border border-slate-200 rounded-xl overflow-hidden">
                @if($cover)
                    <img src="{{ $cover->image_url }}" alt="{{ $trans?->title }}" class="w-full h-48 object-cover group-hover:scale-105 transition-transform" loading="lazy" decoding="async">
                @endif
                <div class="p-4 font-semibold">{{ $trans?->title }}</div>
            </a>
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No galleries available.') }}</p>
        @endforelse
    </div>
    <div>{{ $galleries->links() }}</div>
</div>
@endsection