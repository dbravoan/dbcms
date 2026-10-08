<?php

declare(strict_types=1);

namespace Modules\Analytics\Services;

use Illuminate\Support\Facades\Vite;

class AnalyticsManagerService
{
    public function measurementId(?string $measurementId = null): ?string
    {
        $id = trim((string) ($measurementId ?: module_setting('analytics', 'ga4_measurement_id', config('cms.ga4_measurement_id', ''))));
        if (empty($id)) {
            $id = trim((string) config('cms.ga4_measurement_id', ''));
        }
        if (! preg_match('/^G-[A-Z0-9]{4,20}$/D', $id) || $id === 'G-XXXXXXXXXX') {
            return null;
        }

        return $id;
    }

    /**
     * Renders Google Analytics 4 (GA4) injection script with cookie consent gating.
     */
    public function renderGa4Script(?string $measurementId = null): string
    {
        $id = $this->measurementId($measurementId);
        if ($id === null) {
            return '';
        }

        $encodedId = json_encode($id, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $nonce = htmlspecialchars((string) (Vite::cspNonce() ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
        <!-- Google Analytics 4 (GDPR Consent Gated) -->
        <script nonce="{$nonce}">
            (function() {
                var disableKey = 'ga-disable-' + {$encodedId};
                window[disableKey] = true;
                function updateConsent(granted) {
                    window[disableKey] = !granted;
                    if (granted) loadGa4();
                }
                function loadGa4() {
                    if (window.__ga4_loaded) return;
                    window.__ga4_loaded = true;
                    var s = document.createElement('script');
                    s.async = true;
                    s.src = 'https://www.googletagmanager.com/gtag/js?id=' + {$encodedId};
                    document.head.appendChild(s);
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                    gtag('config', {$encodedId}, { 'anonymize_ip': true, 'allow_google_signals': false, 'allow_ad_personalization_signals': false });
                }

                // Check existing consent cookie or storage
                var cookieVal = null;
                var parts = ('; ' + document.cookie).split('; cms_cookie_consent=');
                if (parts.length >= 2) {
                    cookieVal = parts.pop().split(';').shift();
                }
                if (!cookieVal) {
                    try { cookieVal = localStorage.getItem('cms_cookie_consent'); } catch(e) {}
                }
                if (cookieVal) {
                    try {
                        var consent = JSON.parse(decodeURIComponent(cookieVal));
                        if (consent && (consent.analytics === true || consent.analytics === 'true')) {
                            updateConsent(true);
                        }
                    } catch(e) {}
                }

                // Listen for live consent update from consent modal
                window.addEventListener('cookie-consent-updated', function(e) {
                    updateConsent(!!(e.detail && (e.detail.analytics === true || e.detail.analytics === 'true')));
                });
            })();
        </script>
        HTML;
    }
}
