@extends('themes::db-sunset.layouts.app')

@section('title', ($translation->meta_title ?? $translation->title ?? $gallery->title) . ' - dbCMS Sunset')
@section('meta_description', $translation->meta_description ?? $translation->description ?? $gallery->description ?? '')

@section('content')
<div class="space-y-12" x-data="{ lightboxImage: null, lightboxTitle: '' }">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Album Header -->
    <header class="space-y-4 border-b border-white/10 pb-8">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold uppercase tracking-wider">
            <span>{{ __('Álbum Creativo') }}</span>
        </div>
        <h1 class="text-3xl sm:text-4xl font-black tracking-tight text-white">
            {{ $translation->title ?? $gallery->title }}
        </h1>
        @if(!empty($translation->description ?? $gallery->description))
            <p class="text-slate-400 text-base max-w-3xl leading-relaxed">
                {{ $translation->description ?? $gallery->description }}
            </p>
        @endif
        <div class="flex items-center gap-4 text-xs text-slate-500 pt-1">
            <span><i class="fa-solid fa-calendar mr-1"></i> {{ $gallery->created_at->format('d M, Y') }}</span>
            <span>&bull;</span>
            <span><i class="fa-solid fa-camera mr-1"></i> {{ $items->count() }} elementos</span>
        </div>
    </header>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @forelse($items as $item)
            <div class="sunset-card group overflow-hidden cursor-pointer hover:border-rose-500/50 transition-all"
                 @click="lightboxImage = '{{ $item->image_url }}'; lightboxTitle = '{{ addslashes($item->title ?? $item->caption ?? '') }}'">
                <div class="aspect-square bg-slate-900 overflow-hidden relative">
                    <img src="{{ $item->image_url }}" alt="{{ $item->alt_text ?? $item->title ?? 'Imagen' }}" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500" loading="eager" fetchpriority="high" decoding="async">
                    <div class="absolute inset-0 bg-black/40 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                        <span class="px-3 py-1.5 bg-gradient-to-r from-rose-500 to-amber-500 rounded-xl text-xs font-semibold text-white shadow-lg">{{ __('+ Ver') }}</span>
                    </div>
                </div>
                @if(!empty($item->title) || !empty($item->caption))
                    <div class="p-3 text-xs">
                        <div class="font-semibold text-white truncate">{{ $item->title }}</div>
                        @if(!empty($item->caption))
                            <div class="text-[11px] text-slate-400 truncate mt-0.5">{{ $item->caption }}</div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-16 text-center sunset-card text-slate-500 text-sm">{{ __('Este álbum aún no tiene imágenes asignadas.') }}</div>
        @endforelse
    </div>

    <!-- Lightbox Modal -->
    <div x-show="lightboxImage"
         x-transition
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/90 backdrop-blur-md"
         style="display: none;"
         @click.outside="lightboxImage = null">
        <div class="max-w-5xl w-full bg-[#120c22] border border-white/15 rounded-2xl overflow-hidden shadow-2xl space-y-3 p-5">
            <div class="flex items-center justify-between pb-3 border-b border-white/10">
                <span class="text-xs font-semibold text-rose-400" x-text="lightboxTitle || 'Vista previa'"></span>
                <button @click="lightboxImage = null" class="text-slate-400 hover:text-white text-xl font-bold">&times;</button>
            </div>
            <div class="max-h-[75vh] overflow-hidden flex items-center justify-center bg-black/60 rounded-xl p-2">
                <img :src="lightboxImage" class="max-h-[72vh] w-auto object-contain rounded-lg" loading="lazy" decoding="async">
            </div>
        </div>
    </div>

    <!-- Schema.org ImageGallery JSON-LD -->
</div>
@endsection
