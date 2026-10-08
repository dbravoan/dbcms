<?php

declare(strict_types=1);

namespace Modules\Analytics\Services;

use App\Core\Modules\ModuleManager;
use App\Core\Security\Contracts\ContentSecurityPolicyContributorInterface;

final class Ga4CspContributor implements ContentSecurityPolicyContributorInterface
{
    public function __construct(private readonly ModuleManager $modules, private readonly AnalyticsManagerService $analytics) {}

    /** @return list<string> */
    public function connectOrigins(): array
    {
        if (! $this->modules->isModuleEnabled('analytics') || $this->analytics->measurementId() === null) {
            return [];
        }

        // Exact hosts for gtag and GA4 global/EU collection; no Ads features.
        return [
            'https://www.googletagmanager.com',
            'https://www.google-analytics.com',
            'https://google-analytics.com',
            'https://region1.google-analytics.com',
            'https://analytics.google.com',
            'https://region1.analytics.google.com',
        ];
    }
}
