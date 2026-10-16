<footer class="ia-footer">
    <div class="ia-footer-top">
        <div class="ia-footer-brand">
            <a href="{{ $localization->url('/', $currentMarket) }}">{{ $siteName ?? config('cms.name') }}<span aria-hidden="true"> ↗</span></a>
            <p>{{ __('Aprender. Probar. Revisar. Compartir.') }}</p>
        </div>
        <nav class="ia-footer-nav" aria-label="{{ __('Recursos') }}">
            @foreach($footerQuick ?? [] as $link)
                <a href="{{ $link['url'] }}" @if($link['target_blank'] ?? false) target="_blank" rel="noopener" @endif>{{ $link['title'] }}</a>
            @endforeach
        </nav>
    </div>
    <div class="ia-footer-bottom">
        <span>&copy; {{ date('Y') }} {{ $siteName ?? config('cms.name') }}</span>
        <nav aria-label="{{ __('Información y preferencias') }}">
            @foreach($footerLegal ?? [] as $link)
                <a href="{{ $link['url'] }}">{{ $link['title'] }}</a>
            @endforeach
            <a href="{{ $localization->url('/llms.txt', $currentMarket) }}">llms.txt</a>
            <a href="{{ $localization->url('/sitemap.xml', $currentMarket) }}">{{ __('Mapa del sitio') }}</a>
            <button type="button" data-dispatch="open-cookie-settings">{{ __('Preferencias de cookies') }}</button>
            @if($hasMarkets || $hasLangs)
                <button type="button" data-dispatch="open-geo-modal">{{ strtoupper($marketCode) }} / {{ strtoupper($currentLocale) }}</button>
            @endif
        </nav>
    </div>
</footer>
