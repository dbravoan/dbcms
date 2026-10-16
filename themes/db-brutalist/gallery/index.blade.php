@extends('themes::db-brutalist.layouts.app')

@section('title', app()->getLocale() === 'es' ? 'Galerías & Exposiciones Gráficas' : (app()->getLocale() === 'ca' ? 'Galeries & Exposicions Gràfiques' : 'Graphic Galleries & Showcase'))

@section('content')
<div class="space-y-12 font-mono">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Banner -->
    <header class="space-y-3">
        <div class="inline-block px-3 py-1 bg-[#00F0FF] text-black font-black text-xs border-2 border-black shadow-[3px_3px_0px_0px_#000] uppercase">
            <i class="fa-solid fa-bolt mr-1"></i> Media Shock
        </div>
        <h1 class="text-4xl sm:text-6xl font-black tracking-tight text-black uppercase">
            {{ app()->getLocale() === 'es' ? 'Galerías & Exposiciones' : (app()->getLocale() === 'ca' ? 'Galeries & Exposicions' : 'Visual Galleries & Assets') }}
        </h1>
        <p class="text-black font-bold text-sm max-w-2xl leading-relaxed bg-[#FFE600] p-3 border-2 border-black shadow-[4px_4px_0px_0px_#000]">
            {{ app()->getLocale() === 'es' ? 'Colección de diagramas de alto contraste, capturas brutales y recursos gráficos.' : 'High-contrast schematics, brutalist screenshots, and graphic assets.' }}
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
            <div class="brutalist-card group overflow-hidden flex flex-col justify-between">
                <div class="relative aspect-video bg-black overflow-hidden border-b-3 border-black">
                    @if($firstItem && !empty($firstItem->image_url))
                        <img src="{{ $firstItem->image_url }}" alt="{{ $firstItem->alt_text ?? $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300" loading="lazy" decoding="async">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-black text-[#FFE600] text-sm">
                            [ ÁLBUM BRUTAL ]
                        </div>
                    @endif
                    <div class="absolute top-3 right-3 px-2.5 py-1 bg-[#FFE600] text-black font-black text-[10px] border-2 border-black shadow-[2px_2px_0px_0px_#000]">
                        {{ $gal->items->count() }} {{ app()->getLocale() === 'es' ? 'ITEMS' : 'ITEMS' }}
                    </div>
                </div>

                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div>
                        <h2 class="text-xl font-black text-black group-hover:text-[#FF5A5F] transition-colors uppercase">
                            <a href="{{ $albumUrl }}">{{ $trans->title ?? $gal->title }}</a>
                        </h2>
                        <p class="text-xs text-black font-semibold mt-2 line-clamp-2 leading-relaxed">
                            {{ $trans->description ?? $gal->description ?? 'Colección de recursos gráficos.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t-2 border-black flex items-center justify-between text-xs font-black">
                        <span class="text-black">{{ $gal->created_at->format('d/m/Y') }}</span>
                        <a href="{{ $albumUrl }}" class="btn-brutalist px-3 py-1 text-xs flex items-center gap-1">
                            <span>{{ __('VER ÁLBUM') }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center brutalist-card font-black text-sm">{{ __('No hay galerías publicadas en este mercado.') }}</div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-6">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
