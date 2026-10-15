<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Connectors;

use App\DTOs\Marketing\CreativePackage;
use Modules\SocialPublishing\DTOs\OperationResult;
use Modules\SocialPublishing\Models\SocialAccount;

final class InstagramConnector extends AbstractConnector
{
    public function capabilities(): array
    {
        return ['formats' => ['image', 'carousel', 'short_video'], 'login' => 'instagram_login', 'publish_grant' => 'instagram_business_content_publish', 'requires_professional' => true];
    }

    public function validate(CreativePackage $package, array $metadata): void
    {
        $this->media($package, ['image/jpeg', 'video/mp4'], 10);
        $this->ensure(count($package->artifacts) >= 1 && mb_strlen($package->text) <= 2200, 'Instagram necesita archivos finales y un texto de hasta 2200 caracteres.');
        foreach ($package->artifacts as $file) {
            if ($file->mimeType === 'image/jpeg') {
                $ratio = $file->width !== null && $file->height !== null ? $file->width / $file->height : 0;
                $this->ensure($file->sizeBytes <= 8388608 && $ratio >= 0.8 && $ratio <= 1.91, 'La variante de imagen debe ser JPEG de hasta 8 MB y proporción entre 4:5 y 1.91:1. Adapta el archivo antes de aprobar.');
            }
            if ($file->mimeType === 'video/mp4') {
                $this->ensure($file->durationSeconds !== null && $file->durationSeconds >= 3 && $file->durationSeconds <= 60, 'La versión inicial requiere vídeo corto validado de 3–60 segundos.');
                $this->ensure(($file->metadata['video_codec'] ?? null) === 'h264', 'La variante de vídeo necesita el códec H264 verificado.');
            }
        }
    }

    public function step(string $step, SocialAccount $account, CreativePackage $package, array $receipt, array $metadata): OperationResult
    {
        $child = preg_match('/^child:([0-9]+)$/D', $step, $parts) === 1;
        if ((! in_array($step, ['prepare', 'parent', 'poll', 'publish', 'confirm', 'reconcile'], true) && ! $child) || ($child && ! isset($package->artifacts[(int) $parts[1]]))) {
            return new OperationResult('failed', error: 'unsupported_connector_operation');
        }
        if ($step === 'parent' && (count($package->artifacts) < 2 || count((array) ($receipt['children'] ?? [])) !== count($package->artifacts))) {
            return new OperationResult('failed', error: 'carousel_children_not_confirmed');
        }
        $base = 'https://graph.instagram.com/'.config('social_publishing.graph_version', 'v26.0');
        $this->ensure((bool) preg_match('/^[0-9]+$/D', $account->remote_id), 'Cuenta de Instagram no válida.');
        if ($step === 'confirm' || $step === 'reconcile') {
            $id = (string) ($receipt['id'] ?? '');
            if ($id === '' || ! preg_match('/^[0-9]+$/D', $id)) {
                return new OperationResult('pending_confirmation', error: 'instagram_correlated_media_identity_missing');
            }
            $r = $this->http($account)->get($base.'/'.rawurlencode($id), ['fields' => 'id,owner,caption']);

            return $r->successful() && $r->json('id') === $id && $r->json('owner.id') === $account->remote_id && $r->json('caption') === $package->text
                ? new OperationResult('published', remoteId: $id, receipt: $receipt) : new OperationResult('pending_confirmation', error: 'instagram_media_not_confirmed');
        }
        if ($step === 'poll') {
            $r = $this->http($account)->get($base.'/'.rawurlencode((string) ($receipt['container'] ?? '')), ['fields' => 'status_code']);
            if (! $r->successful()) {
                return new OperationResult('remote_processing', 'poll', receipt: $receipt, error: 'provider_poll_unavailable', retryAfter: 120);
            }

            return match ($r->json('status_code')) {
                'FINISHED' => new OperationResult('ready_to_publish', 'publish', receipt: $receipt, retryAfter: 0),
                'ERROR','EXPIRED' => new OperationResult('failed', receipt: $receipt, error: 'container_failed'),
                'PUBLISHED' => new OperationResult('pending_confirmation', receipt: $receipt, error: 'published_container_missing_media_identity'),
                default => new OperationResult('remote_processing', 'poll', receipt: $receipt),
            };
        }
        if ($step === 'publish') {
            return $this->result($this->http($account)->asForm()->post($base.'/'.$account->remote_id.'/media_publish', ['creation_id' => (string) ($receipt['container'] ?? '')]));
        }
        if (str_starts_with($step, 'child:') || ($step === 'prepare' && count($package->artifacts) > 1)) {
            $index = $step === 'prepare' ? 0 : (int) substr($step, 6);
            $file = $package->artifacts[$index];
            $body = ['is_carousel_item' => 'true'];
            $body[$file->mimeType === 'video/mp4' ? 'video_url' : 'image_url'] = (string) ($receipt['asset_urls'][$index] ?? '');
            if ($file->mimeType === 'video/mp4') {
                $body['media_type'] = 'VIDEO';
            }
            $result = $this->result($this->http($account)->asForm()->post($base.'/'.$account->remote_id.'/media', $body), 'id', 'prepared');
            if ($result->state !== 'prepared') {
                return $result;
            }
            $receipt['children'][] = $result->receipt['id'];

            return new OperationResult('preparing', $index + 1 < count($package->artifacts) ? 'child:'.($index + 1) : 'parent', receipt: $receipt, retryAfter: 0);
        }
        $body = ['caption' => $package->text];
        if ($step === 'parent') {
            $body += ['media_type' => 'CAROUSEL', 'children' => implode(',', (array) ($receipt['children'] ?? []))];
        } else {
            $file = $package->artifacts[0];
            $body[$file->mimeType === 'video/mp4' ? 'video_url' : 'image_url'] = (string) ($receipt['asset_urls'][0] ?? '');
            if ($file->mimeType === 'video/mp4') {
                $body['media_type'] = 'REELS';
            }
        }
        $result = $this->result($this->http($account)->asForm()->post($base.'/'.$account->remote_id.'/media', $body), 'id', 'prepared');

        return $result->state === 'prepared' ? new OperationResult('remote_processing', 'poll', receipt: $receipt + ['container' => $result->receipt['id']]) : $result;
    }

    public function metrics(SocialAccount $account, string $remote): array
    {
        $r = $this->http($account)->get('https://graph.instagram.com/'.config('social_publishing.graph_version', 'v26.0').'/'.rawurlencode($remote), ['fields' => 'id,owner,like_count,comments_count']);
        $this->ensure($r->successful() && $r->json('id') === $remote && $r->json('owner.id') === $account->remote_id, 'No se ha confirmado la propiedad del medio.');
        $values = [];
        foreach (['likes' => 'like_count', 'comments' => 'comments_count'] as $name => $field) {
            $v = $r->json($field);
            $values[$name] = ['value' => is_int($v) && $v >= 0 ? $v : null, 'availability' => is_int($v) && $v >= 0 ? 'available' : 'unavailable'];
        }

        return ['source' => 'instagram-owned-organic-media', 'window' => 'lifetime', 'availability' => 'available', 'values' => $values];
    }
}
