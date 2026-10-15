<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Contracts\Services\CampaignAttributionGatewayInterface;

final class UnavailableCampaignAttributionGateway implements CampaignAttributionGatewayInterface
{
    public function report(string $campaign, string $content, string $dateFrom, string $dateTo): array
    {
        return ['status' => 'unavailable', 'source' => 'ga4', 'sessions' => null, 'key_events' => null, 'fetched_at' => null];
    }
}
