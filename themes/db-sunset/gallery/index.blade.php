@extends('themes::db-sunset.layouts.app')

@section('title', app()->getLocale() === 'es' ? 'Galerías & Experiencias Visuales' : (app()->getLocale() === 'ca' ? 'Galeries & Experiències Visuals' : 'Visual Galleries & Experiences'))

@section('content')
<div class="space-y-12">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Banner -->
    <header class="space-y-3">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-rose-500/10 border border-rose-500/30 text-rose-400 text-xs font-semibold uppercase tracking-wider">
            <span>{{ __('Galería de Medios') }}</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-black tracking-tight text-white">
            {{ app()->getLocale() === 'es' ? 'Galerías & Experiencias Visuales' : (app()->getLocale() === 'ca' ? 'Galeries & Experiències Visuals' : 'Visual Galleries & Experiences') }}
        </h1>
        <p class="text-slate-400 text-base max-w-2xl leading-relaxed">
            {{ app()->getLocale() === 'es' ? 'Capturas visuales, proyectos creativos y recursos gráficos de alta fidelidad.' : 'Visual assets, creative showcases, and high-fidelity graphics.' }}
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
            <div class="sunset-card group overflow-hidden flex flex-col justify-between hover:scale-[1.01] transition-all duration-300">
                <div class="relative aspect-video bg-slate-900 overflow-hidden border-b border-white/10">
                    @if($firstItem && !empty($firstItem->image_url))
                        <img src="{{ $firstItem->image_url }}" alt="{{ $firstItem->alt_text ?? $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-bold text-slate-700 text-xs">
                            [ Álbum Sunset ]
                        </div>
                    @endif
                    <div class="absolute top-3 right-3 px-2.5 py-1 bg-black/70 backdrop-blur border border-white/10 rounded-lg text-[10px] font-semibold text-rose-300">
                        {{ $gal->items->count() }} {{ app()->getLocale() === 'es' ? 'fotos' : 'photos' }}
                    </div>
                </div>

                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white group-hover:text-rose-400 transition-colors">
                            <a href="{{ $albumUrl }}">{{ $trans->title ?? $gal->title }}</a>
                        </h2>
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                            {{ $trans->description ?? $gal->description ?? 'Colección de fotografías y recursos gráficos.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-white/5 flex items-center justify-between text-xs">
                        <span class="text-slate-500">{{ $gal->created_at->format('Y-m-d') }}</span>
                        <a href="{{ $albumUrl }}" class="font-semibold text-rose-400 hover:underline flex items-center gap-1">
                            <span>{{ __('Ver Álbum') }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center sunset-card text-slate-400 text-sm">{{ __('No hay galerías disponibles en este mercado.') }}</div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-6">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
