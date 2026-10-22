<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use Modules\SocialPublishing\Connectors\FacebookConnector;
use Modules\SocialPublishing\Connectors\InstagramConnector;
use Modules\SocialPublishing\Connectors\LinkedInConnector;
use Modules\SocialPublishing\Connectors\ManualConnector;
use Modules\SocialPublishing\Connectors\YouTubeConnector;
use Modules\SocialPublishing\Contracts\SocialConnector;

final class ConnectorRegistry
{
    public function get(string $provider): SocialConnector
    {
        return match ($provider) {
            'facebook' => app(FacebookConnector::class), 'instagram' => app(InstagramConnector::class),
            'linkedin' => app(LinkedInConnector::class), 'youtube' => app(YouTubeConnector::class), 'tiktok' => app(ManualConnector::class),
            default => throw new \InvalidArgumentException('Red social no compatible.'),
        };
    }
}
