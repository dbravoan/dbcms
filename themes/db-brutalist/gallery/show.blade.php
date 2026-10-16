@extends('themes::db-brutalist.layouts.app')

@section('title', ($translation->meta_title ?? $translation->title ?? $gallery->title) . ' - dbCMS Brutalist')
@section('meta_description', $translation->meta_description ?? $translation->description ?? $gallery->description ?? '')

@section('content')
<div class="space-y-12 font-mono" x-data="{ lightboxImage: null, lightboxTitle: '' }">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Album Header -->
    <header class="space-y-4 border-b-3 border-black pb-8">
        <div class="inline-block px-3 py-1 bg-[#FFE600] text-black font-black text-xs border-2 border-black shadow-[2px_2px_0px_0px_#000] uppercase">{{ __('Álbum') }}</div>
        <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-black uppercase">
            {{ $translation->title ?? $gallery->title }}
        </h1>
        @if(!empty($translation->description ?? $gallery->description))
            <p class="text-black font-bold text-base max-w-3xl leading-relaxed bg-[#00F0FF]/30 p-3 border-2 border-black shadow-[3px_3px_0px_0px_#000]">
                {{ $translation->description ?? $gallery->description }}
            </p>
        @endif
        <div class="flex items-center gap-4 text-xs text-black font-black pt-1">
            <span><i class="fa-solid fa-calendar mr-1"></i> {{ $gallery->created_at->format('d/m/Y') }}</span>
            <span>&bull;</span>
            <span><i class="fa-solid fa-camera mr-1"></i> {{ $items->count() }} ELEMENTOS</span>
        </div>
    </header>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($items as $item)
            <div class="brutalist-card group overflow-hidden cursor-pointer"
                 @click="lightboxImage = '{{ $item->image_url }}'; lightboxTitle = '{{ addslashes($item->title ?? $item->caption ?? '') }}'">
                <div class="aspect-square bg-black overflow-hidden relative">
                    <img src="{{ $item->image_url }}" alt="{{ $item->alt_text ?? $item->title ?? 'Imagen' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="eager" fetchpriority="high" decoding="async">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="btn-brutalist px-3 py-1 text-xs">{{ __('+ AMPLIAR') }}</span>
                    </div>
                </div>
                @if(!empty($item->title) || !empty($item->caption))
                    <div class="p-3 text-xs font-black">
                        <div class="text-black truncate uppercase">{{ $item->title }}</div>
                        @if(!empty($item->caption))
                            <div class="text-[10px] text-black/70 truncate mt-0.5">{{ $item->caption }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-16 text-center brutalist-card text-black font-black text-sm uppercase">{{ __('Este álbum no dispone de imágenes.') }}</div>
        @endforelse
    </div>

    <!-- Lightbox Modal -->
    <div x-show="lightboxImage"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80"
         style="display: none;"
         @click.outside="lightboxImage = null">
        <div class="max-w-5xl w-full bg-[#FFFDF8] border-3 border-black shadow-[8px_8px_0px_0px_#FFE600] space-y-3 p-5 font-mono">
            <div class="flex items-center justify-between pb-3 border-b-2 border-black">
                <span class="text-xs font-black text-black uppercase" x-text="lightboxTitle || 'Vista'"></span>
                <button @click="lightboxImage = null" class="text-black hover:bg-[#FF5A5F] px-2 py-0.5 border border-black font-black text-lg">&times;</button>
            </div>
            <div class="max-h-[75vh] overflow-hidden flex items-center justify-center bg-black border-2 border-black p-2">
                <img :src="lightboxImage" class="max-h-[72vh] w-auto object-contain" loading="lazy" decoding="async">
            </div>
        </div>
    </div>

    <!-- Schema.org ImageGallery JSON-LD -->
</div>
@endsection
