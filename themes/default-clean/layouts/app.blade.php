@php
    $adminTheme = $adminTheme ?? (string) config('cms.admin_theme', 'dbcms');
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
<html lang="{{ $currentLocale ?? app()->getLocale() }}" dir="{{ $__dir }}" class="h-full front-default-clean theme-{{ $adminTheme }} bg-white text-slate-900 antialiased">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
     <title>{{ $seoMeta?->title ?? $pageTitle ?? config('cms.name') }} | {{ config('cms.name') }}</title>

    @include('themes::components.seo-head')

    @if(isset($hreflangs) && is_array($hreflangs))
        @foreach($hreflangs as $localeTag => $href)
            <link rel="alternate" hreflang="{{ $localeTag }}" href="{{ $href }}" />
        @endforeach
    @endif
    @vite(cms_asset_entries(true))
    @include('themes::components.analytics')
</head>
<body class="min-h-full flex flex-col justify-between">
    <!--cms-private:themes::default-clean.partials.header:start-->
    @include('themes::default-clean.partials.header')
    <!--cms-private:end-->

    <main class="flex-grow">
        @yield('content')
    </main>

    @include('themes::default-clean.partials.footer')

    <!--cms-private:themes::components.visitor-geo-modal:start-->
    @include('themes::components.visitor-geo-modal')
    <!--cms-private:end-->
    <!--cms-private:themes::components.cookie-consent-modal:start-->
    @include('themes::components.cookie-consent-modal')
    <!--cms-private:end-->
    @include('components.frontoffice-admin-bar')
    @include('themes::components.scroll-magic')

    @stack('admin_scripts')
    @stack('scripts')
</body>
</html>
