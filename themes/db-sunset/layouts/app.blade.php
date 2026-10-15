@php
    $localization = app(\App\Contracts\Services\LocalizationServiceInterface::class);
    $currentLocale = app()->getLocale();
@endphp
@php
    // Resolve the page title/description from the rendered sections (or explicit
    // variables) so the unified seo-head always emits a complete head.
    $pageTitle = $pageTitle ?? (trim(strip_tags((string) view()->yieldContent('title'))) ?: null);
    $pageDescription = $pageDescription ?? (trim(strip_tags((string) view()->yieldContent('meta_description'))) ?: null);
@endphp
<!DOCTYPE html>
@php
    $__dir = app(\App\Contracts\Services\LocalizationServiceInterface::class)->activeLanguages()
        ->firstWhere("code", app()->getLocale())?->direction ?? "ltr";
@endphp
<html lang="{{ $currentLocale }}" dir="{{ $__dir }}" class="h-full front-db-sunset theme-{{ $adminTheme ?? 'dbcms' }} bg-[#0a0712] text-slate-100 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
     <title>{{ $seoMeta?->title ?? $pageTitle ?? config('cms.name') }} | {{ config('cms.name') }}</title>

    @include('themes.components.seo-head')

    @if(isset($hreflangs) && is_array($hreflangs))
        @foreach($hreflangs as $localeTag => $href)
            <link rel="alternate" hreflang="{{ $localeTag }}" href="{{ $href }}" />
        @endforeach
    @endif
    @vite(cms_asset_entries(true))
</head>
<body class="min-h-full flex flex-col justify-between selection:bg-rose-500 selection:text-white relative overflow-x-clip">
    <!-- Ambient Glow Background -->
    <div class="fixed inset-0 pointer-events-none -z-10 overflow-hidden">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-rose-600/10 rounded-full blur-[120px]"></div>
        <div class="absolute top-1/3 -right-40 w-96 h-96 bg-amber-500/10 rounded-full blur-[120px]"></div>
        <div class="absolute -bottom-40 left-1/3 w-96 h-96 bg-purple-600/10 rounded-full blur-[120px]"></div>
    </div>

    <!-- Header -->
    <!--cms-private:themes.db-sunset.partials.header:start-->
    @include('themes.db-sunset.partials.header')
    <!--cms-private:end-->

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @yield('content')
    </main>

    <!-- Footer -->
    @include('themes.db-sunset.partials.footer')

    <!-- First-Time Visitor Geolocation Preference Modal -->
    <!--cms-private:themes.components.visitor-geo-modal:start-->
    @include('themes.components.visitor-geo-modal')
    <!--cms-private:end-->

    <!-- Dynamic GDPR Cookie Consent Banner & Modal -->
    <!--cms-private:themes.components.cookie-consent-modal:start-->
    @include('themes.components.cookie-consent-modal')
    <!--cms-private:end-->

    <!-- Frontoffice Admin Bar & Real-Time SEO/GEO Quality Inspector -->
    @include('components.frontoffice-admin-bar')

    <!-- Google Analytics 4 (GDPR Consent Gated) -->
    <!--cms-private:themes.components.analytics:start-->
    @include('themes.components.analytics')
    <!--cms-private:end-->

    <!-- Universal Scroll-Magic & Interactive Scrub Engine -->
    @include('themes.components.scroll-magic')
    @stack('admin_scripts')
    @stack('scripts')
</body>
</html>
