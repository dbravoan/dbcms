@php
    $seoMeta = $seoMeta ?? null;
    $siteName = trim((string) setting('general.site_name', '')) ?: (string) config('cms.name');
    $pageTitle = $pageTitle ?? (trim(strip_tags((string) view()->yieldContent('title'))) ?: null);
    $pageDescription = $pageDescription ?? (trim(strip_tags((string) view()->yieldContent('meta_description'))) ?: null);
    $direction = app(\App\Contracts\Services\LocalizationServiceInterface::class)->activeLanguages()
        ->firstWhere('code', app()->getLocale())?->direction ?? 'ltr';
@endphp
<!DOCTYPE html>
<html lang="{{ $currentLocale }}" dir="{{ $direction }}" class="front-ia-practica theme-{{ $adminTheme ?? 'dbcms' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f6f3eb">
    <title>{{ $seoMeta?->title ?? $pageTitle ?? config('cms.name') }}</title>
    @include('themes::components.seo-head')
    @vite(cms_asset_entries(true))
</head>
<body>
    <a class="ia-skip" href="#contenido">{{ __('Saltar al contenido') }}</a>
    <!--cms-private:themes::ia-practica.partials.header:start-->
    @include('themes::ia-practica.partials.header')
    <!--cms-private:end-->
    <main id="contenido" class="ia-main cms-text" tabindex="-1">
        @yield('content')
    </main>
    @include('themes::ia-practica.partials.footer')
    <!--cms-private:themes::components.visitor-geo-modal:start-->
    @include('themes::components.visitor-geo-modal')
    <!--cms-private:end-->
    <!--cms-private:themes::components.cookie-consent-modal:start-->
    @include('themes::components.cookie-consent-modal')
    <!--cms-private:end-->
    @include('components.frontoffice-admin-bar')
    <!--cms-private:themes::components.analytics:start-->
    @include('themes::components.analytics')
    <!--cms-private:end-->
    @include('themes::components.scroll-magic')
    @stack('admin_scripts')
    @stack('scripts')
</body>
</html>
