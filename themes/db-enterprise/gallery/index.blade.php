@extends('themes.db-enterprise.layouts.app')

@section('title', app()->getLocale() === 'es' ? 'Galerías de Infraestructura & Esquemas' : (app()->getLocale() === 'ca' ? 'Galeries d\'Infraestructura & Esquemes' : 'Infrastructure & Technical Media'))

@section('content')
<div class="max-w-7xl mx-auto space-y-12">

    <!-- Breadcrumb Component -->
    @include('components.breadcrumbs', ['trail' => $breadcrumbs ?? []])

    <!-- Header Banner -->
    <header class="border-b border-slate-800 pb-8 space-y-3">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-400 text-xs font-semibold uppercase tracking-wider">
            <span>Visual Assets & Architecture</span>
        </div>
        <h1 class="text-3xl sm:text-5xl font-bold tracking-tight text-white">
            {{ app()->getLocale() === 'es' ? 'Galerías de Infraestructura & Esquemas' : (app()->getLocale() === 'ca' ? 'Galeries d\'Infraestructura & Esquemes' : 'Infrastructure & Technical Media') }}
        </h1>
        <p class="text-slate-400 text-base max-w-3xl leading-relaxed">
            {{ app()->getLocale() === 'es' ? 'Planos de despliegue, centros de datos, esquemas de topología de red y documentación gráfica.' : 'Deployment schematics, cloud data centers, network topology diagrams, and enterprise visual documentation.' }}
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
            <div class="bg-slate-900 border border-slate-800 rounded-2xl group overflow-hidden flex flex-col justify-between hover:border-indigo-500/50 hover:shadow-xl hover:shadow-indigo-500/5 transition-all">
                <div class="relative aspect-video bg-slate-950 overflow-hidden border-b border-slate-800">
                    @if($firstItem && !empty($firstItem->image_url))
                        <img src="{{ $firstItem->image_url }}" alt="{{ $firstItem->alt_text ?? $trans->title }}" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" loading="lazy" decoding="async">
                    @else
                        <div class="w-full h-full flex items-center justify-center font-semibold text-slate-700 text-xs">
                            [ Topología & Multimedia ]
                        </div>
                    @endif
                    <div class="absolute top-3 right-3 px-2.5 py-1 bg-slate-950/80 backdrop-blur border border-slate-700 rounded-md text-[10px] font-semibold text-indigo-300">
                        {{ $gal->items->count() }} {{ app()->getLocale() === 'es' ? 'activos' : 'assets' }}
                    </div>
                </div>

                <div class="p-6 space-y-3 flex-grow flex flex-col justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white group-hover:text-indigo-400 transition-colors">
                            <a href="{{ $albumUrl }}">{{ $trans->title ?? $gal->title }}</a>
                        </h2>
                        <p class="text-xs text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                            {{ $trans->description ?? $gal->description ?? 'Colección de diagramas técnicos y activos de ingeniería.' }}
                        </p>
                    </div>

                    <div class="pt-4 border-t border-slate-800/80 flex items-center justify-between text-xs">
                        <span class="text-slate-500">{{ $gal->created_at->format('Y-m-d') }}</span>
                        <a href="{{ $albumUrl }}" class="font-semibold text-indigo-400 hover:underline flex items-center gap-1">
                            <span>{{ __('Ver Álbum') }}</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-span-full py-16 text-center bg-slate-900 border border-slate-800 rounded-2xl text-slate-400 text-sm">{{ __('No hay galerías publicadas en este mercado todavía.') }}</div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="pt-6">
        {{ $galleries->links() }}
    </div>
</div>
@endsection
