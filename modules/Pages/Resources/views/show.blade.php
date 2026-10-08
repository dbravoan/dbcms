<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="scroll-smooth">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $translation->meta_title ?? $translation->title }} | {{ config('app.name') }}</title>
    <meta name="description" content="{{ $translation->meta_description ?? '' }}">

    {{-- Automated Multi-Directional Hreflang Cluster --}}
    @foreach($hreflangs as $hreflangTag => $url)
        <link rel="alternate" hreflang="{{ $hreflangTag }}" href="{{ $url }}" />
    @endforeach
    <link rel="canonical" href="{{ url()->current() }}" />

    {{-- Schema.org JSON-LD --}}

    <style>
        [data-scroll-magic="fade-up"] { opacity: 0; transform: translateY(30px); transition: all 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
        [data-scroll-magic="fade-up"].in-view { opacity: 1; transform: translateY(0); }
        [data-scroll-magic="reveal-left"] { opacity: 0; transform: translateX(-40px); transition: all 0.7s cubic-bezier(0.16, 1, 0.3, 1); }
        [data-scroll-magic="reveal-left"].in-view { opacity: 1; transform: translateX(0); }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-50 text-slate-900 font-sans antialiased min-h-screen flex flex-col justify-between">
    {{-- Global Zone Navigation Header --}}
    <header class="sticky top-0 z-50 bg-white/90 backdrop-blur-md border-b border-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
            <a href="{{ url(($currentMarket->code ?? 'es') . '/' . app()->getLocale()) }}" class="font-black text-xl tracking-tight text-slate-900">
                {{ config('cms.name') }}
            </a>
            <nav class="hidden md:flex items-center gap-6 text-sm font-medium text-slate-600">
                <a href="{{ url(($currentMarket->code ?? 'es') . '/' . app()->getLocale()) }}" class="hover:text-indigo-600">Home</a>
                <a href="{{ url(($currentMarket->code ?? 'es') . '/' . app()->getLocale() . '/blog') }}" class="hover:text-indigo-600">{{ __('Blog') }}</a>
            </nav>
            <div class="text-xs font-semibold text-slate-500 bg-slate-100 px-3 py-1 rounded-full uppercase">
                {{ $currentMarket->code ?? 'es' }} / {{ app()->getLocale() }}
            </div>
        </div>
    </header>

    {{-- Composite Sections Container --}}
    <main class="flex-grow">
        @foreach($renderedSections as $sectionHtml)
            {!! $sectionHtml !!}
        @endforeach
    </main>

    {{-- Global Zone Navigation Footer --}}
    <footer class="bg-slate-900 text-slate-400 py-12 border-t border-slate-800">
        <div class="max-w-7xl mx-auto px-4 text-center text-xs">
            &copy; {{ date('Y') }} {{ config('cms.name') }} Enterprise Multi-Market. {{ app()->getLocale() === 'ca' ? 'Tots els drets reservats.' : (app()->getLocale() === 'en' ? 'All rights reserved.' : 'Todos los derechos reservados.') }}
        </div>
    </footer>

    {{-- Scroll-Magic Observer --}}
    <script nonce="{{ $cspNonce ?? '' }}">
        document.addEventListener('DOMContentLoaded', () => {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('in-view');
                    }
                });
            }, { threshold: 0.15 });

            document.querySelectorAll('[data-scroll-magic]').forEach(el => observer.observe(el));
        });
    </script>
</body>
</html>
