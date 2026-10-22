<?php

declare(strict_types=1);

namespace Modules\Ads\Services;

use App\Core\Modules\ModuleManager;
use Illuminate\Support\Facades\Vite;

class AdSenseManagerService
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * Renders an AdSense responsive ad unit with publisher and slot configuration.
     */
    public function renderAdUnit(string $slotId, string $adFormat = 'auto', bool $fullWidthResponsive = true): string
    {
        if (! $this->modules->isModuleEnabled('ads')) {
            return '';
        }

        $clientId = trim((string) module_setting('ads', 'adsense_client_id', config('cms.adsense_client_id', '')));
        if (empty($clientId)) {
            $clientId = trim((string) config('cms.adsense_client_id', ''));
        }
        if (! preg_match('/^ca-pub-[0-9]{16}$/D', $clientId) || ! preg_match('/^[0-9]{1,20}$/D', $slotId)) {
            return '';
        }
        $clientId = htmlspecialchars($clientId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $slotId = htmlspecialchars($slotId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $adFormat = htmlspecialchars($adFormat, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $responsive = $fullWidthResponsive ? 'true' : 'false';

        $nonce = htmlspecialchars((string) (Vite::cspNonce() ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
        <div class="my-6 text-center overflow-hidden">
            <ins class="adsbygoogle"
                 style="display:block"
                 data-ad-client="{$clientId}"
                 data-ad-slot="{$slotId}"
                 data-ad-format="{$adFormat}"
                 data-full-width-responsive="{$responsive}"></ins>
            <script nonce="{$nonce}">(adsbygoogle = window.adsbygoogle || []).push({});</script>
        </div>
        HTML;
    }
}
