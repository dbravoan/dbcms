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
<html lang="{{ $currentLocale }}" dir="{{ $__dir }}" class="h-full front-db-brutalist theme-{{ $adminTheme ?? 'dbcms' }} bg-[#FFFDF8] text-black antialiased">
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
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full flex flex-col justify-between selection:bg-[#FFE600] selection:text-black font-sans">
    <!-- Header -->
    <!--cms-private:themes.db-brutalist.partials.header:start-->
    @include('themes.db-brutalist.partials.header')
    <!--cms-private:end-->

    <!-- Main Content -->
    <main class="flex-grow max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-12">
        @yield('content')
    </main>

    <!-- Footer -->
    @include('themes.db-brutalist.partials.footer')

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
