@php
    $layout = app(\App\Services\Cms\FrontViewResolver::class)->layout();
@endphp
@extends($layout)

@section('title', app()->getLocale() === 'es' ? 'Galerías y álbumes multimedia' : (app()->getLocale() === 'ca' ? 'Galeries i àlbums multimèdia' : 'Media Galleries & Showcase'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">
    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <div class="space-y-3">
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white flex items-center gap-3">
            <span class="text-indigo-500">&gt;</span>
            <span>{{ app()->getLocale() === 'es' ? 'Galerías multimedia' : (app()->getLocale() === 'ca' ? 'Galeries multimèdia' : 'Media Galleries') }}</span>
        </h1>
        <p class="text-gray-400 font-mono text-sm max-w-2xl">
            {{ app()->getLocale() === 'es' ? 'Colección de activos visuales, capturas técnicas y recursos de ingeniería de software.' : 'Visual assets collection, architecture schematics, and software engineering resources.' }}
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        @php
            $localization = app(\App\Contracts\Services\LocalizationServiceInterface::class);
            $galPrefix = $localization->getModulePrefix('gallery', app()->getLocale());
        @endphp

        @forelse($galleries as $gal)
            @php
                $trans = $gal->translations->where('locale', app()->getLocale())->first() ?? $gal->translations->first();
                $firstItem = $gal->items->first();
                $albumUrl = $localization->url("/{$galPrefix}/" . ($trans->slug ?? $gal->slug), $currentMarket);
            @endphp
            <div class="bg-gray-900 border border-gray-800 rounded-xl overflow-hidden flex flex-col justify-between hover:border-indigo-500 transition-all">
                <div class="relative aspect-video bg-black overflow-hidden">
                    @if($firstItem && !empty($firstItem->image_url))
                        <img src="{{ $firstItem->image_url }}" alt="{{ $firstItem->alt_text ?? $trans?->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-mono text-gray-600 text-xs">
                            [Sin portada]
                        </div>
                    @endif
                </div>

                <div class="p-6 flex-1 flex flex-col justify-between space-y-4">
                    <div class="space-y-2">
                        <h3 class="text-lg font-bold text-white group-hover:text-indigo-400 transition-colors">
                            {{ $trans?->title ?? $gal->title }}
                        </h3>
                        <p class="text-xs text-gray-400 font-mono line-clamp-2 leading-relaxed">
                            {{ $trans?->description ?? $gal->description }}
                        </p>
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-800 text-xs font-mono">
                        <span class="text-gray-500">{{ $gal->items->count() }} elementos</span>
                        <a href="{{ $albumUrl }}" class="text-indigo-400 hover:underline font-bold inline-flex items-center gap-1">
                            Ver Álbum &rarr;
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center text-gray-500 font-mono">{{ __('No hay galerías publicadas actualmente para este mercado.') }}</div>
        @endforelse
    </div>
</div>
@endsection
