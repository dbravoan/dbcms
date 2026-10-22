<?php

declare(strict_types=1);

namespace Modules\Meta\Services;

use App\Core\Modules\ModuleManager;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaCapiService
{
    public function __construct(private readonly ModuleManager $modules) {}

    /**
     * Sends server-side conversion event (e.g. Lead, Purchase) to Meta Graph API.
     *
     * @param  array<string, mixed>  $userData
     * @param  array<string, mixed>  $customData
     */
    public function sendConversionEvent(string $eventName, array $userData = [], array $customData = []): bool
    {
        if (! $this->modules->isModuleEnabled('meta')) {
            return false;
        }

        if (! (bool) module_setting('meta', 'enable_capi', true)) {
            return false;
        }

        $pixelId = (string) module_setting('meta', 'pixel_id', config('cms.meta_pixel_id', ''));
        if (empty($pixelId)) {
            $pixelId = (string) config('cms.meta_pixel_id', '');
        }

        $accessToken = (string) module_setting('meta', 'capi_token', config('cms.meta_capi_token', ''));
        if (empty($accessToken)) {
            $accessToken = (string) config('cms.meta_capi_token', '');
        }

        $version = (string) module_setting('meta', 'graph_api_version', 'v26.0');
        if (! preg_match('/\Av[1-9][0-9]*\.0\z/', $version) || ! preg_match('/\A[0-9]{1,30}\z/', $pixelId) || empty($accessToken)) {
            return false;
        }

        try {
            $eventData = [
                'event_name' => $eventName,
                'event_time' => time(),
                'action_source' => 'website',
                'user_data' => $userData,
                'custom_data' => $customData,
            ];

            $payload = [
                'data' => [$eventData],
                'access_token' => $accessToken,
            ];

            $testEventCode = (string) module_setting('meta', 'test_event_code', '');
            if (! empty($testEventCode)) {
                $payload['test_event_code'] = $testEventCode;
            }

            $response = Http::timeout(15)->withoutRedirecting()->post("https://graph.facebook.com/{$version}/{$pixelId}/events", $payload);

            return $response->successful();
        } catch (\Throwable $e) {
            Log::warning('Meta CAPI dispatch failed.', ['exception_class' => $e::class]);

            return false;
        }
    }
}
