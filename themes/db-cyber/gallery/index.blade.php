@extends('themes::db-cyber.layouts.app')

@section('title', app()->getLocale() === 'es' ? 'Galerías y álbumes multimedia' : (app()->getLocale() === 'ca' ? 'Galeries i àlbums multimèdia' : 'Media Galleries & Showcase'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 space-y-10">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Banner -->
    <div class="space-y-3">
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight text-white flex items-center gap-3">
            <span class="text-[#00FF99]">&gt;</span>
            <span>{{ app()->getLocale() === 'es' ? 'Galerías multimedia' : (app()->getLocale() === 'ca' ? 'Galeries multimèdia' : 'Media Galleries') }}</span>
        </h1>
        <p class="text-gray-400 font-mono text-sm max-w-2xl">
            {{ app()->getLocale() === 'es' ? 'Colección de activos visuales, capturas técnicas y recursos de ingeniería de software.' : 'Visual assets collection, architecture schematics, and software engineering resources.' }}
        </p>
    </div>

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
            <div class="cyber-card group overflow-hidden flex flex-col justify-between hover:border-[#00FF99] transition-all" data-scroll-magic>
                <div class="relative aspect-video bg-gray-900 overflow-hidden">
                    @if($firstItem && !empty($firstItem->image_url))
                        <img src="{{ $firstItem->image_url }}" alt="{{ $firstItem->alt_text ?? $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-mono text-gray-600 text-xs">
                            [ &gt;_ Media Preview ]
                        </div>
                    @endif
                    <div class="absolute top-3 right-3 px-2 py-1 bg-black/80 backdrop-blur border border-[#00FF99]/30 rounded text-[10px] font-mono text-[#00FF99] font-bold">
                        {{ $gal->items->count() }} {{ app()->getLocale() === 'es' ? 'elementos' : 'items' }}
                    </div>
                </div>

                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white group-hover:text-[#00FF99] transition-colors">
                            <a href="{{ $albumUrl }}">{{ $trans->title ?? $gal->title }}</a>
                        </h2>
                        <p class="text-xs text-gray-400 font-mono mt-2 line-clamp-2">
                            {{ $trans->description ?? $gal->description ?? 'Álbum fotográfico y recursos gráficos.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-gray-800/60 flex items-center justify-between">
                        <span class="text-[10px] font-mono text-gray-500">{{ $gal->created_at->format('Y-m-d') }}</span>
                        <a href="{{ $albumUrl }}" class="text-xs font-mono font-bold text-[#00FF99] hover:underline flex items-center gap-1">
                            <span>{{ __('Ver Álbum') }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center cyber-card font-mono text-gray-500 text-sm">
                &gt; No hay galerías publicadas en este mercado todavía.
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-6">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
