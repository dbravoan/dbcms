@extends('themes::ia-practica.layouts.app')

@section('title', app()->getLocale() === 'es' ? 'Galerías & Exposiciones Visuales' : (app()->getLocale() === 'ca' ? 'Galeries & Exposicions Visuals' : 'Art & Media Galleries'))

@section('content')
<div class="max-w-6xl mx-auto space-y-12">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Banner -->
    <header class="border-b border-stone-200 pb-8 space-y-3">
        <span class="text-xs font-sans uppercase tracking-widest text-amber-700 font-semibold">{{ __('Catálogo Visual') }}</span>
        <h1 class="text-4xl sm:text-5xl font-serif font-bold text-stone-900 tracking-tight">
            {{ app()->getLocale() === 'es' ? 'Galerías & Exposiciones' : (app()->getLocale() === 'ca' ? 'Galeries & Exposicions' : 'Media Collections & Galleries') }}
        </h1>
        <p class="text-stone-600 font-serif text-lg max-w-2xl leading-relaxed">
            {{ app()->getLocale() === 'es' ? 'Colección curada de fotografía, esquemas de diseño y patrimonio visual.' : 'Curated collection of photography, design schematics, and editorial visuals.' }}
        </p>
    </header>

    <!-- Galleries Grid -->
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
            <div class="ia-card group overflow-hidden flex flex-col justify-between hover:shadow-md transition-shadow">
                <div class="relative aspect-video bg-stone-100 overflow-hidden border-b border-stone-100">
                    @if($firstItem && !empty($firstItem->image_url))
                        <img src="{{ $firstItem->image_url }}" alt="{{ $firstItem->alt_text ?? $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-serif italic text-stone-400 text-sm">{{ __('Exposición Fotográfica') }}</div>
                    @endif
                    <div class="absolute top-3 right-3 px-2.5 py-1 bg-white/90 backdrop-blur border border-stone-200 rounded text-[10px] font-sans font-semibold text-stone-800">
                        {{ $gal->items->count() }} {{ app()->getLocale() === 'es' ? 'obras' : 'items' }}
                    </div>
                </div>

                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div>
                        <h2 class="text-xl font-serif font-bold text-stone-900 group-hover:text-amber-800 transition-colors">
                            <a href="{{ $albumUrl }}">{{ $trans->title ?? $gal->title }}</a>
                        </h2>
                        <p class="text-xs text-stone-600 font-serif mt-2 line-clamp-2 leading-relaxed">
                            {{ $trans->description ?? $gal->description ?? 'Colección de elementos gráficos y fotográficos.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-stone-100 flex items-center justify-between text-xs font-sans">
                        <span class="text-stone-400">{{ $gal->created_at->format('d M, Y') }}</span>
                        <a href="{{ $albumUrl }}" class="font-semibold text-amber-800 hover:underline flex items-center gap-1">
                            <span>{{ __('Ver Álbum') }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center ia-card font-serif text-stone-500 text-sm">{{ __('No hay galerías publicadas en este mercado todavía.') }}</div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-6">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
