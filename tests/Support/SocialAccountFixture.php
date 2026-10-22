<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use Modules\SocialPublishing\Services\SocialOAuthService;

final class SocialAccountFixture
{
    /** @return array{client_fingerprint: string} */
    public static function metadata(string $provider): array
    {
        app(ModuleSettingsServiceInterface::class)->setMany('social-publishing', [
            $provider.'_client_id' => 'fixture-'.$provider,
            $provider.'_client_secret' => 'fixture-client-secret',
        ]);

        return ['client_fingerprint' => hash('sha256', json_encode(app(SocialOAuthService::class)->client($provider), JSON_THROW_ON_ERROR))];
    }
}
