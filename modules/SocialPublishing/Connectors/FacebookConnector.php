<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Connectors;

use App\DTOs\Marketing\CreativePackage;
use Modules\SocialPublishing\DTOs\OperationResult;
use Modules\SocialPublishing\Models\SocialAccount;

final class FacebookConnector extends AbstractConnector
{
    public function capabilities(): array
    {
        return ['formats' => ['text', 'link', 'image'], 'publish_grant' => 'pages_manage_posts', 'metrics_grant' => 'pages_read_engagement', 'version' => (string) config('social_publishing.graph_version', 'v26.0')];
    }

    public function validate(CreativePackage $package, array $metadata): void
    {
        $this->media($package, ['image/jpeg', 'image/png'], 1);
        $this->ensure(mb_strlen($package->text) <= 63206, 'El texto supera el límite de Facebook.');
        foreach ($package->artifacts as $file) {
            $this->ensure($file->sizeBytes <= 4000000, 'La variante inicial de Facebook requiere una imagen de hasta 4 MB.');
        }
    }

    public function step(string $step, SocialAccount $account, CreativePackage $package, array $receipt, array $metadata): OperationResult
    {
        if (! in_array($step, ['prepare', 'confirm'], true)) {
            return new OperationResult('failed', error: 'unsupported_connector_operation');
        }
        $this->ensure((bool) preg_match('/^[0-9]+$/D', $account->remote_id), 'Página no válida.');
        $base = 'https://graph.facebook.com/'.config('social_publishing.graph_version', 'v26.0').'/'.$account->remote_id;
        if ($step === 'confirm') {
            $id = (string) ($receipt['id'] ?? '');
            $r = $this->http($account)->get('https://graph.facebook.com/'.config('social_publishing.graph_version', 'v26.0').'/'.rawurlencode($id), ['fields' => 'id,from,is_published']);

            return $r->successful() && $r->json('id') === $id && $r->json('from.id') === $account->remote_id && $r->json('is_published') === true ? new OperationResult('published', remoteId: $id, receipt: $receipt) : new OperationResult('pending_confirmation', error: 'publication_not_confirmed');
        }
        if ($package->artifacts !== []) {
            $file = $package->artifacts[0];
            $stream = $this->files->readStream($file);
            try {
                $r = $this->http($account)->attach('source', $stream, 'final.'.($file->mimeType === 'image/png' ? 'png' : 'jpg'))->post($base.'/photos', ['caption' => $package->text, 'published' => 'true']);
            } finally {
                fclose($stream);
            }
            $result = $this->result($r, 'post_id');
            if ($result->state === 'published' && ! str_starts_with((string) $result->remoteId, $account->remote_id.'_')) {
                return new OperationResult('pending_confirmation', error: 'photo_post_identity_uncertain');
            }

            return $result;
        }

        return $this->result($this->http($account)->asForm()->post($base.'/feed', array_filter(['message' => $package->text, 'link' => $package->destinationUrl, 'published' => 'true'], static fn (mixed $v): bool => $v !== null)));
    }

    public function metrics(SocialAccount $account, string $remote): array
    {
        $r = $this->http($account)->get('https://graph.facebook.com/'.config('social_publishing.graph_version', 'v26.0').'/'.rawurlencode($remote), ['fields' => 'id,from,is_published,shares,comments.summary(true),reactions.summary(true)']);
        $this->ensure($r->successful() && $r->json('id') === $remote && $r->json('from.id') === $account->remote_id && $r->json('is_published') === true, 'La publicación no pertenece a esta página.');
        $values = [];
        foreach (['shares' => 'shares.count', 'comments' => 'comments.summary.total_count', 'reactions' => 'reactions.summary.total_count'] as $name => $field) {
            $value = $r->json($field);
            $values[$name] = ['value' => is_int($value) && $value >= 0 ? $value : null, 'availability' => is_int($value) && $value >= 0 ? 'available' : 'unavailable'];
        }

        return ['source' => 'facebook-page-post', 'window' => 'lifetime', 'availability' => 'available', 'values' => $values];
    }
}
