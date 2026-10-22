@extends('themes::db-editorial.layouts.app')

@section('title', $translation->meta_title ?? $translation->title ?? $gallery->title)
@section('meta_description', $translation->meta_description ?? $translation->description ?? $gallery->description ?? '')

@section('content')
<div class="max-w-6xl mx-auto space-y-12" x-data="{ lightboxImage: null, lightboxTitle: '' }">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Album Header -->
    <header class="space-y-4 border-b border-stone-200 pb-8 text-center max-w-3xl mx-auto">
        <span class="text-xs font-sans uppercase tracking-widest text-amber-700 font-semibold">{{ __('Exposición Fotográfica') }}</span>
        <h1 class="text-4xl sm:text-5xl font-serif font-bold tracking-tight text-stone-900">
            {{ $translation->title ?? $gallery->title }}
        </h1>
        @if(!empty($translation->description ?? $gallery->description))
            <p class="text-stone-600 font-serif text-lg leading-relaxed">
                {{ $translation->description ?? $gallery->description }}
            </p>
        @endif
        <div class="flex items-center justify-center gap-4 text-xs font-sans text-stone-400 pt-2">
            <span><i class="fa-solid fa-calendar mr-1"></i> {{ $gallery->created_at->format('d \d\e F, Y') }}</span>
            <span>&bull;</span>
            <span><i class="fa-solid fa-camera mr-1"></i> {{ $items->count() }} obras</span>
        </div>
    </header>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($items as $item)
            <div class="editorial-card group overflow-hidden cursor-pointer hover:shadow-lg transition-all"
                 @click="lightboxImage = '{{ $item->image_url }}'; lightboxTitle = '{{ addslashes($item->title ?? $item->caption ?? '') }}'">
                <div class="aspect-square bg-stone-100 overflow-hidden relative">
                    <img src="{{ $item->image_url }}" alt="{{ $item->alt_text ?? $item->title ?? 'Imagen' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="eager" fetchpriority="high" decoding="async">
                    <div class="absolute inset-0 bg-stone-900/30 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="px-3 py-1.5 bg-white/90 backdrop-blur rounded text-xs font-sans text-stone-900 font-semibold shadow-sm">
                            Ampliar &rarr;
                        </span>
                    </div>
                </div>
                @if(!empty($item->title) || !empty($item->caption))
                    <div class="p-3 text-xs">
                        <div class="font-serif font-bold text-stone-900 truncate">{{ $item->title }}</div>
                        @if(!empty($item->caption))
                            <div class="text-[11px] text-stone-500 font-serif italic truncate mt-0.5">{{ $item->caption }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-16 text-center editorial-card font-serif text-stone-500 text-sm">{{ __('Este álbum aún no tiene imágenes asignadas.') }}</div>
        @endforelse
    </div>

    <!-- Lightbox Modal -->
    <div x-show="lightboxImage"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-stone-950/80 backdrop-blur-sm"
         style="display: none;"
         @click.outside="lightboxImage = null">
        <div class="max-w-4xl w-full bg-white rounded-2xl overflow-hidden shadow-2xl space-y-3 p-5">
            <div class="flex items-center justify-between pb-3 border-b border-stone-200">
                <span class="text-sm font-serif font-bold text-stone-900" x-text="lightboxTitle || 'Vista previa'"></span>
                <button @click="lightboxImage = null" class="text-stone-400 hover:text-stone-900 text-xl font-bold">&times;</button>
            </div>
            <div class="max-h-[75vh] overflow-hidden flex items-center justify-center bg-stone-50 rounded-xl p-2">
                <img :src="lightboxImage" class="max-h-[72vh] w-auto object-contain rounded-lg" loading="lazy" decoding="async">
            </div>
        </div>
    </div>

    <!-- Schema.org ImageGallery JSON-LD -->
</div>
@endsection
