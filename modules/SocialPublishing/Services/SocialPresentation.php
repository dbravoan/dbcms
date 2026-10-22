<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialDelivery;
use Modules\SocialPublishing\Models\SocialMetric;
use Modules\SocialPublishing\Models\SocialPublication;
use Modules\SocialPublishing\Models\SocialRevision;

final readonly class SocialPresentation
{
    public function __construct(private SocialAuthorization $auth, private ConnectorRegistry $connectors) {}

    /** @return array<string,mixed> */
    public function dashboard(): array
    {
        $publications = SocialPublication::where('installation_id', $this->auth->installation())->latest()->paginate(20);

        return ['publications' => $publications, 'accounts' => SocialAccount::orderBy('label')->get(), 'deliveries' => SocialDelivery::where('installation_id', $this->auth->installation())->latest()->limit(100)->get(), 'providers' => ['facebook' => 'Facebook', 'instagram' => 'Instagram Login', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok · exportación manual'], 'timezone' => (string) config('social_publishing.timezone', config('app.timezone', 'UTC'))];
    }

    /** @return array<string,mixed> */
    public function preview(string $id): array
    {
        $p = SocialPublication::where('installation_id', $this->auth->installation())->findOrFail($id);
        $account = SocialAccount::findOrFail($p->account_id);

        return ['publication' => $p, 'revision' => SocialRevision::findOrFail($p->revision_id), 'account' => $account, 'accounts' => SocialAccount::orderBy('label')->get(), 'capabilities' => $this->connectors->get($account->provider)->capabilities(), 'deliveries' => SocialDelivery::where('publication_id', $id)->latest()->get(), 'metrics' => SocialMetric::whereIn('delivery_id', SocialDelivery::where('publication_id', $id)->select('id'))->latest('observed_at')->get(), 'timezone' => (string) config('social_publishing.timezone', config('app.timezone', 'UTC'))];
    }
}
