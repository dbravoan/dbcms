@extends('themes::db-cyber.layouts.app')

@section('title', $translation->meta_title ?? $translation->title ?? $gallery->title)
@section('meta_description', $translation->meta_description ?? $translation->description ?? $gallery->description ?? '')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10" x-data="{ lightboxImage: null, lightboxTitle: '' }">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Album Header -->
    <div class="space-y-4 border-b border-gray-800 pb-8">
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white flex items-center gap-3">
            <span class="text-[#00FF99]">&gt;</span>
            <span>{{ $translation->title ?? $gallery->title }}</span>
        </h1>
        @if(!empty($translation->description ?? $gallery->description))
            <p class="text-gray-400 font-mono text-sm max-w-3xl leading-relaxed">
                {{ $translation->description ?? $gallery->description }}
            </p>
        @endif
        <div class="flex items-center gap-4 text-xs font-mono text-gray-500">
            <span><i class="fa-solid fa-calendar mr-1"></i> {{ $gallery->created_at->format('d M, Y') }}</span>
            <span><i class="fa-solid fa-camera mr-1"></i> {{ $items->count() }} elementos</span>
        </div>
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($items as $item)
            <div class="cyber-card group overflow-hidden cursor-pointer"
                 @click="lightboxImage = '{{ $item->image_url }}'; lightboxTitle = '{{ addslashes($item->title ?? $item->caption ?? '') }}'"
                 data-scroll-magic>
                <div class="aspect-square bg-gray-900 overflow-hidden relative">
                    <img src="{{ $item->image_url }}" alt="{{ $item->alt_text ?? $item->title ?? 'Imagen' }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="lazy" decoding="async">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="px-3 py-1.5 bg-[#1A1A1A] border border-[#00FF99] rounded text-[11px] font-mono text-[#00FF99] font-bold">{{ __('+ Expandir') }}</span>
                    </div>
                </div>
                @if(!empty($item->title) || !empty($item->caption))
                    <div class="p-3 font-mono text-xs">
                        <div class="font-bold text-white truncate">{{ $item->title }}</div>
                        @if(!empty($item->caption))
                            <div class="text-[10px] text-gray-400 truncate mt-0.5">{{ $item->caption }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-16 text-center cyber-card font-mono text-gray-500 text-sm">
                &gt; Este álbum aún no tiene imágenes asignadas.
            </div>
        @endforelse
    </div>

    <!-- Lightbox Modal -->
    <div x-show="lightboxImage"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
         style="display: none;"
         @click.outside="lightboxImage = null">
        <div class="max-w-4xl w-full bg-[#141414] border border-[#00FF99]/40 rounded-xl overflow-hidden shadow-[0_0_50px_rgba(0,255,153,0.3)] space-y-3 p-4 font-mono">
            <div class="flex items-center justify-between pb-2 border-b border-gray-800">
                <span class="text-xs font-bold text-white" x-text="lightboxTitle || 'Vista previa'"></span>
                <button @click="lightboxImage = null" class="text-gray-400 hover:text-white text-lg">&times;</button>
            </div>
            <div class="max-h-[75vh] overflow-hidden flex items-center justify-center bg-black/50 rounded-lg">
                <img :src="lightboxImage" class="max-h-[75vh] w-auto object-contain rounded-lg" loading="lazy" decoding="async">
            </div>
        </div>
    </div>

    <!-- Schema.org ImageGallery JSON-LD -->
</div>
@endsection
