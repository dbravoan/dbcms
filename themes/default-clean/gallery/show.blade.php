@extends('themes::default-clean.layouts.app')

@section('title', $translation?->meta_title ?? $translation?->title ?? $gallery->title)
@section('meta_description', $translation?->meta_description ?? $translation?->description ?? '')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-8">
    <h1 class="text-3xl font-bold">{{ $translation?->title ?? $gallery->title }}</h1>
    @if($translation?->description)
        <p class="text-slate-500 max-w-2xl">{{ $translation->description }}</p>
    @endif
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        @forelse($items as $item)
            <img src="{{ $item->image_url }}" alt="{{ $item->title ?? '' }}" class="w-full aspect-square object-cover rounded-xl" loading="lazy" decoding="async">
        @empty
            <p class="text-slate-500 col-span-full">{{ __('No images assigned') }}</p>
        @endforelse
    </div>
</div>
@endsection