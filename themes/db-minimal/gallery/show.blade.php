@extends('themes::db-minimal.layouts.app')

@section('title', ($translation->meta_title ?? $translation->title ?? $gallery->title) . ' - dbCMS Minimal')
@section('meta_description', $translation->meta_description ?? $translation->description ?? $gallery->description ?? '')

@section('content')
<div class="space-y-16" x-data="{ lightboxImage: null, lightboxTitle: '' }">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Album Header -->
    <header class="space-y-3 border-b border-neutral-200/80 pb-8">
        <span class="text-[10px] font-mono uppercase tracking-widest text-neutral-400">{{ __('Colección Visual') }}</span>
        <h1 class="text-3xl sm:text-4xl font-light tracking-tight text-neutral-900">
            {{ $translation->title ?? $gallery->title }}
        </h1>
        @if(!empty($translation->description ?? $gallery->description))
            <p class="text-neutral-500 text-sm max-w-2xl leading-relaxed">
                {{ $translation->description ?? $gallery->description }}
            </p>
        @endif
        <div class="flex items-center gap-4 text-[11px] font-mono text-neutral-400 pt-1">
            <span>{{ $gallery->created_at->format('Y.m.d') }}</span>
            <span>&bull;</span>
            <span>{{ $items->count() }} elementos</span>
        </div>
    </header>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($items as $item)
            <div class="minimal-card group overflow-hidden cursor-pointer hover:border-neutral-500 transition-all"
                 @click="lightboxImage = '{{ $item->image_url }}'; lightboxTitle = '{{ addslashes($item->title ?? $item->caption ?? '') }}'">
                <div class="aspect-square bg-neutral-100 overflow-hidden relative">
                    <img src="{{ $item->image_url }}" alt="{{ $item->alt_text ?? $item->title ?? 'Imagen' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="eager" fetchpriority="high" decoding="async">
                    <div class="absolute inset-0 bg-neutral-900/20 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="px-2.5 py-1 bg-white text-neutral-900 text-[10px] font-mono uppercase tracking-widest">
                            {{ __('Ver') }}
                        </span>
                    </div>
                </div>
                @if(!empty($item->title) || !empty($item->caption))
                    <div class="p-3 text-xs">
                        <div class="font-medium text-neutral-900 truncate">{{ $item->title }}</div>
                        @if(!empty($item->caption))
                            <div class="text-[10px] text-neutral-400 truncate mt-0.5">{{ $item->caption }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-16 text-center minimal-card font-mono text-neutral-400 text-xs uppercase tracking-widest">{{ __('Sin imágenes asignadas') }}</div>
        @endforelse
    </div>

    <!-- Lightbox Modal -->
    <div x-show="lightboxImage"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-neutral-950/80 backdrop-blur-sm"
         style="display: none;"
         @click.outside="lightboxImage = null">
        <div class="max-w-4xl w-full bg-white rounded-lg overflow-hidden shadow-2xl space-y-3 p-5">
            <div class="flex items-center justify-between pb-2 border-b border-neutral-100">
                <span class="text-xs font-mono uppercase tracking-wider text-neutral-700" x-text="lightboxTitle || 'Vista'"></span>
                <button @click="lightboxImage = null" class="text-neutral-400 hover:text-neutral-900 text-lg">&times;</button>
            </div>
            <div class="max-h-[75vh] overflow-hidden flex items-center justify-center bg-neutral-50 rounded p-2">
                <img :src="lightboxImage" class="max-h-[72vh] w-auto object-contain" loading="lazy" decoding="async">
            </div>
        </div>
    </div>

    <!-- Schema.org ImageGallery JSON-LD -->
</div>
@endsection
