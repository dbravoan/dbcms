@php
    $layout = app(\App\Services\Cms\FrontViewResolver::class)->layout();
@endphp
@extends($layout)

@section('title', $translation->meta_title ?? $translation->title ?? $gallery->title)

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <div class="space-y-3">
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white flex items-center gap-3">
            <span class="text-indigo-500">&gt;</span>
            <span>{{ $translation->title ?? $gallery->title }}</span>
        </h1>
        @if(!empty($translation->description ?? $gallery->description))
            <p class="text-gray-400 font-mono text-sm max-w-3xl leading-relaxed">
                {{ $translation->description ?? $gallery->description }}
            </p>
        @endif
    </div>

    <!-- Gallery Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($items as $item)
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden flex flex-col justify-between group">
                <div class="aspect-video bg-black overflow-hidden relative">
                    <img src="{{ $item->image_url }}" alt="{{ $item->alt_text ?? $item->title ?? 'Imagen' }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                </div>
                @if($item->title || $item->caption)
                    <div class="p-4 space-y-1 bg-gray-900/80 border-t border-gray-800">
                        @if($item->title)
                            <h4 class="text-xs font-bold text-white font-mono">{{ $item->title }}</h4>
                        @endif
                        @if($item->caption)
                            <p class="text-[11px] text-gray-400 font-mono leading-relaxed">{{ $item->caption }}</p>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="col-span-full py-16 text-center text-gray-500 font-mono">{{ __('Este álbum no contiene imágenes en este momento.') }}</div>
        @endforelse
    </div>
</div>
@endsection
