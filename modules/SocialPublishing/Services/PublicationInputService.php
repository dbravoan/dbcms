<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\Contracts\Services\CreativePackageProviderInterface;
use App\Contracts\Services\MarketingArtifactStoreInterface;
use App\Core\Capabilities\ActorContext;
use App\DTOs\Marketing\CreativePackage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Modules\SocialPublishing\Contracts\PublicationServiceInterface;
use Modules\SocialPublishing\Models\SocialAccount;
use Modules\SocialPublishing\Models\SocialPublication;
use Modules\SocialPublishing\Models\SocialRevision;

final readonly class PublicationInputService
{
    public function __construct(private SocialAuthorization $auth, private MarketingArtifactStoreInterface $files, private CreativePackageProviderInterface $packages, private PublicationServiceInterface $publications) {}

    /**
     * @param  array<string,mixed>  $data
     * @param  list<UploadedFile>  $uploads
     */
    public function create(array $data, array $uploads, ActorContext $actor): SocialPublication
    {
        $user = $this->auth->authorize($actor);
        if (isset($data['creative_revision_id'])) {
            $package = $this->packages->getPackage((string) $data['creative_revision_id'], $actor);
        } else {
            $artifacts = [];
            foreach ($uploads as $file) {
                $stream = fopen($file->getRealPath(), 'rb');
                if ($stream === false) {
                    throw new \RuntimeException('No se pudo leer el archivo subido.');
                }
                try {
                    $artifacts[] = $this->files->writeStream($stream, 'social-upload/'.$user->id, $file->getClientOriginalName());
                } finally {
                    fclose($stream);
                }
            }
            $id = (string) Str::uuid();
            if ($artifacts === [] && isset($data['publication_id']) && empty($data['remove_assets'])) {
                $existing = SocialPublication::where('installation_id', $this->auth->installation())->findOrFail((string) $data['publication_id']);
                $existingRevision = SocialRevision::findOrFail($existing->revision_id);
                $artifacts = CreativePackage::fromArray($existingRevision->package)->artifacts;
            }
            $package = new CreativePackage($id, $id, isset($data['campaign_id']) ? (string) $data['campaign_id'] : null, (string) ($data['locale'] ?? 'es'), (string) ($data['market'] ?? 'es'), (string) ($data['text'] ?? ''), isset($data['destination_url']) ? (string) $data['destination_url'] : null, $artifacts, ['source' => 'manual', 'created_by' => $user->id]);
        }
        $metadata = (array) ($data['metadata'] ?? []);
        if ($package->destinationUrl !== null) {
            $account = SocialAccount::findOrFail((string) $data['account_id']);
            $parts = parse_url($package->destinationUrl);
            if (! is_array($parts) || ! isset($parts['scheme'],$parts['host'])) {
                throw new \InvalidArgumentException('Destino inválido.');
            } $query = [];
            parse_str((string) ($parts['query'] ?? ''), $query);
            $query = array_replace($query, ['utm_source' => $account->provider, 'utm_medium' => 'social', 'utm_campaign' => $package->campaignId ?? $package->packageId, 'utm_content' => $package->revisionId]);
            $url = (string) $parts['scheme'].'://'.(string) $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '/').'?'.http_build_query($query).(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
            $package = new CreativePackage($package->packageId, $package->revisionId, $package->campaignId, $package->locale, $package->market, $package->text, $url, $package->artifacts, $package->provenance, $package->version);
        }
        if (isset($data['publication_id'])) {
            return $this->publications->revise((string) $data['publication_id'], (string) ($data['expected_revision'] ?? ''), $package, (string) $data['account_id'], $metadata, $actor);
        }

        return $this->publications->create($package, (string) $data['account_id'], $metadata, $actor);
    }
}
