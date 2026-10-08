@extends('themes.db-minimal.layouts.app')

@section('title', app()->getLocale() === 'es' ? 'Galerías & Espacios' : (app()->getLocale() === 'ca' ? 'Galeries & Espais' : 'Galleries & Spaces'))

@section('content')
<div class="space-y-16">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Banner -->
    <header class="border-b border-neutral-200/80 pb-8 space-y-2">
        <span class="text-[10px] font-mono uppercase tracking-widest text-neutral-400">Archivo Visual</span>
        <h1 class="text-3xl sm:text-4xl font-light tracking-tight text-neutral-900">
            {{ app()->getLocale() === 'es' ? 'Galerías & Espacios' : (app()->getLocale() === 'ca' ? 'Galeries & Espais' : 'Galleries & Spaces') }}
        </h1>
        <p class="text-neutral-500 text-sm max-w-xl leading-relaxed">
            {{ app()->getLocale() === 'es' ? 'Colección fotográfica minimalista, geometría espacial y registros arquitectónicos.' : 'Minimalist photography collection, spatial geometry, and architectural records.' }}
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
            <div class="minimal-card group overflow-hidden flex flex-col justify-between hover:border-neutral-400 transition-colors">
                <div class="relative aspect-video bg-neutral-100 overflow-hidden border-b border-neutral-100">
                    @if($firstItem && !empty($firstItem->image_url))
                        <img src="{{ $firstItem->image_url }}" alt="{{ $firstItem->alt_text ?? $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-mono text-neutral-400 text-xs uppercase tracking-widest">
                            [ Sin Imagen ]
                        </div>
                    @endif
                    <div class="absolute top-2.5 right-2.5 px-2 py-0.5 bg-white/95 border border-neutral-200 text-[9px] font-mono uppercase tracking-widest text-neutral-700">
                        {{ $gal->items->count() }}
                    </div>
                </div>

                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div>
                        <h2 class="text-base font-medium text-neutral-900 group-hover:underline">
                            <a href="{{ $albumUrl }}">{{ $trans->title ?? $gal->title }}</a>
                        </h2>
                        <p class="text-xs text-neutral-500 mt-1.5 line-clamp-2 leading-relaxed">
                            {{ $trans->description ?? $gal->description ?? 'Registro visual.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-neutral-100 flex items-center justify-between text-[11px] font-mono text-neutral-400">
                        <span>{{ $gal->created_at->format('Y.m.d') }}</span>
                        <a href="{{ $albumUrl }}" class="text-neutral-900 uppercase tracking-widest hover:underline flex items-center gap-1">
                            <span>Ver</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center minimal-card font-mono text-neutral-400 text-xs uppercase tracking-widest">{{ __('Sin galerías registradas') }}</div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-6 font-mono text-xs">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
