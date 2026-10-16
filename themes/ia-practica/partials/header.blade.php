@php
    $nav = $frontNav ?? ['layout' => 'inline', 'toggle' => true, 'toggleDesktop' => false, 'admin' => false];
    $navFallback = [['title' => __('Inicio'), 'url' => $localization->url('/', $currentMarket)]];
    $desktopVisible = in_array($nav['layout'], ['inline', 'stacked'], true);
@endphp
<header x-data="{}" class="ia-header" @keydown.escape.window="$store.nav.open = false">
    <div class="ia-header-inner">
        <a class="ia-wordmark" href="{{ $localization->url('/', $currentMarket) }}" aria-label="{{ $siteName ?? config('cms.name') }} — {{ __('Inicio') }}">
            <span class="ia-brand-symbol" aria-hidden="true">i<span>a</span><i>↗</i></span>
            <span class="ia-brand-name">{{ $siteName ?? config('cms.name') }}<small>{{ __('Formación para el trabajo real') }}</small></span>
        </a>
        <div class="ia-header-links">
            @if($desktopVisible)
                @include('themes::components.nav-items', [
                    'items' => $headerMenu, 'fallback' => $navFallback,
                    'class' => 'ia-desktop-nav'.(($nav['toggle'] ?? true) ? '' : ' ia-nav-always'),
                    'linkClass' => 'ia-nav-link',
                ])
            @endif
            @include('themes::components.nav-admin', ['frontNav' => $nav, 'class' => 'ia-admin-link', 'label' => __('Administrar')])
            @include('themes::components.nav-toggle', ['frontNav' => $nav, 'class' => 'ia-nav-toggle'])
        </div>
    </div>
    @include('themes::components.nav-panel', [
        'frontNav' => $nav, 'items' => $headerMenu, 'fallback' => $navFallback,
        'class' => 'ia-mobile-panel', 'navClass' => 'ia-mobile-nav',
        'linkClass' => 'ia-nav-link', 'adminClass' => 'ia-admin-link',
    ])
</header>
