<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Connectors;

use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\DTOs\OperationResult;
use Modules\SocialPublishing\Models\SocialAccount;

final class LinkedInConnector extends AbstractConnector
{
    public function capabilities(): array
    {
        return ['formats' => ['text', 'link', 'image', 'multi_image', 'short_video'], 'version' => (string) config('social_publishing.linkedin_version', '202609'), 'publish_grant' => 'w_organization_social'];
    }

    protected function http(SocialAccount $account): PendingRequest
    {
        return parent::http($account)->withHeaders(['Linkedin-Version' => (string) config('social_publishing.linkedin_version', '202609'), 'X-Restli-Protocol-Version' => '2.0.0']);
    }

    public function validate(CreativePackage $package, array $metadata): void
    {
        $this->media($package, ['image/jpeg', 'image/png', 'video/mp4'], 20);
        $this->ensure(mb_strlen($package->text) <= 3000, 'LinkedIn admite hasta 3000 caracteres.');
        foreach ($package->artifacts as $file) {
            if (str_starts_with($file->mimeType, 'image/')) {
                $this->ensure($file->width !== null && $file->height !== null && $file->width * $file->height < 36152320, 'La imagen de LinkedIn supera el límite de píxeles.');
            }
        }
        if (count($package->artifacts) > 1) {
            foreach ($package->artifacts as $f) {
                $this->ensure($f->mimeType !== 'video/mp4', 'La publicación multimagen solo admite imágenes.');
            }
        }
    }

    public function step(string $step, SocialAccount $account, CreativePackage $package, array $receipt, array $metadata): OperationResult
    {
        $indexed = preg_match('/^(image-init|image-upload|poll:image|video-part):([0-9]+)$/D', $step, $parts) === 1;
        if (! in_array($step, ['prepare', 'publish', 'confirm', 'video-init', 'video-finalize', 'poll:video'], true) && ! $indexed) {
            return new OperationResult('failed', error: 'unsupported_connector_operation');
        }
        if ($indexed && (($parts[1] === 'video-part' && ! isset($receipt['video']['uploadInstructions'][(int) $parts[2]])) || ($parts[1] !== 'video-part' && ! isset($package->artifacts[(int) $parts[2]])))) {
            return new OperationResult('failed', error: 'connector_media_index_invalid');
        }
        $this->ensure((bool) preg_match('/^urn:li:(organization|person):[A-Za-z0-9_-]+$/D', $account->remote_id), 'Autor de LinkedIn no válido.');
        if ($step === 'prepare' && $package->artifacts !== []) {
            $step = $package->artifacts[0]->mimeType === 'video/mp4' ? 'video-init' : 'image-init:0';
        }
        if (str_starts_with($step, 'image-init:')) {
            $index = (int) substr($step, 11);
            $r = $this->http($account)->post('https://api.linkedin.com/rest/images?action=initializeUpload', ['initializeUploadRequest' => ['owner' => $account->remote_id]]);
            $result = $this->result($r, 'value.image', 'prepared');
            if ($result->state !== 'prepared') {
                return $result;
            }
            $receipt['images'][$index] = ['id' => $result->receipt['id'], 'url' => $this->signedUpload((string) $r->json('value.uploadUrl'), 'linkedin.com')];

            return new OperationResult('preparing', 'image-upload:'.$index, receipt: $receipt, retryAfter: 0);
        }
        if (str_starts_with($step, 'image-upload:')) {
            $index = (int) substr($step, 13);
            $file = $package->artifacts[$index];
            $stream = $this->files->readStream($file);
            try {
                $r = $this->http($account)->withBody(Utils::streamFor($stream), $file->mimeType)->send('PUT', $this->signedUpload((string) $receipt['images'][$index]['url'], 'linkedin.com'));
            } finally {
                fclose($stream);
            }
            if (! $r->successful()) {
                return new OperationResult($r->serverError() ? 'pending_confirmation' : 'failed', error: 'linkedin_asset_upload_unconfirmed');
            }

            return new OperationResult('remote_processing', 'poll:image:'.$index, receipt: $receipt);
        }
        if (str_starts_with($step, 'poll:image:')) {
            $index = (int) substr($step, 11);
            $image = (string) $receipt['images'][$index]['id'];
            $r = $this->http($account)->get('https://api.linkedin.com/rest/images/'.rawurlencode($image));
            if ($r->successful() && $r->json('status') === 'AVAILABLE' && $r->json('owner') === $account->remote_id) {
                return new OperationResult('preparing', $index + 1 < count($package->artifacts) ? 'image-init:'.($index + 1) : 'publish', receipt: $receipt, retryAfter: 0);
            }

            return new OperationResult('remote_processing', $step, receipt: $receipt, error: 'linkedin_processing_or_read_permission');
        }
        if ($step === 'video-init') {
            $r = $this->http($account)->post('https://api.linkedin.com/rest/videos?action=initializeUpload', ['initializeUploadRequest' => ['owner' => $account->remote_id, 'fileSizeBytes' => $package->artifacts[0]->sizeBytes, 'uploadCaptions' => false, 'uploadThumbnail' => false]]);
            $result = $this->result($r, 'value.video', 'prepared');
            if ($result->state !== 'prepared') {
                return $result;
            }
            $receipt['video'] = $r->json('value');

            return new OperationResult('preparing', 'video-part:0', receipt: $receipt, retryAfter: 0);
        }
        if (str_starts_with($step, 'video-part:')) {
            $i = (int) substr($step, 11);
            $part = $receipt['video']['uploadInstructions'][$i];
            $file = $package->artifacts[0];
            $stream = $this->files->readStream($file);
            try {
                $bytes = stream_get_contents($stream, (int) $part['lastByte'] - (int) $part['firstByte'] + 1, (int) $part['firstByte']);
            } finally {
                fclose($stream);
            }
            $this->ensure(is_string($bytes), 'No se pudo leer el fragmento de vídeo.');
            $r = $this->http($account)->withBody($bytes, 'application/octet-stream')->send('PUT', $this->signedUpload((string) $part['uploadUrl'], 'linkedin.com'));
            if (! $r->successful() || $r->header('ETag') === '') {
                return new OperationResult('pending_confirmation', error: 'linkedin_video_part_unconfirmed');
            }
            $receipt['video']['part_ids'][] = $r->header('ETag');

            return new OperationResult('preparing', $i + 1 < count($receipt['video']['uploadInstructions']) ? 'video-part:'.($i + 1) : 'video-finalize', receipt: $receipt, retryAfter: 0);
        }
        if ($step === 'video-finalize') {
            $r = $this->http($account)->post('https://api.linkedin.com/rest/videos?action=finalizeUpload', ['finalizeUploadRequest' => ['video' => $receipt['video']['video'], 'uploadToken' => $receipt['video']['uploadToken'], 'uploadedPartIds' => $receipt['video']['part_ids']]]);

            return $r->successful() ? new OperationResult('remote_processing', 'poll:video', receipt: $receipt) : new OperationResult('pending_confirmation', error: 'linkedin_finalize_unconfirmed');
        }
        if ($step === 'poll:video') {
            $r = $this->http($account)->get('https://api.linkedin.com/rest/videos/'.rawurlencode((string) $receipt['video']['video']));

            return $r->successful() && $r->json('status') === 'AVAILABLE' && $r->json('owner') === $account->remote_id ? new OperationResult('ready_to_publish', 'publish', receipt: $receipt, retryAfter: 0) : new OperationResult('remote_processing', $step, receipt: $receipt);
        }
        if ($step === 'confirm') {
            $id = (string) ($receipt['id'] ?? '');
            $r = $this->http($account)->get('https://api.linkedin.com/rest/posts/'.rawurlencode($id), ['viewContext' => 'AUTHOR']);

            return $r->successful() && $r->json('author') === $account->remote_id && $r->json('lifecycleState') === 'PUBLISHED' ? new OperationResult('published', remoteId: $id, receipt: $receipt) : new OperationResult('pending_confirmation', error: 'linkedin_post_not_confirmed');
        }
        $body = ['author' => $account->remote_id, 'commentary' => $package->text, 'visibility' => 'PUBLIC', 'distribution' => ['feedDistribution' => 'MAIN_FEED', 'targetEntities' => [], 'thirdPartyDistributionChannels' => []], 'lifecycleState' => 'PUBLISHED', 'isReshareDisabledByAuthor' => false];
        if (isset($receipt['video'])) {
            $body['content'] = ['media' => ['id' => $receipt['video']['video'], 'title' => (string) ($metadata['title'] ?? 'Vídeo')]];
        } elseif (count($receipt['images'] ?? []) > 1) {
            $body['content'] = ['multiImage' => ['images' => array_map(static fn (array $image): array => ['id' => $image['id']], $receipt['images'])]];
        } elseif (isset($receipt['images'][0])) {
            $body['content'] = ['media' => ['id' => $receipt['images'][0]['id']]];
        } elseif ($package->destinationUrl !== null) {
            $body['content'] = ['article' => ['source' => $package->destinationUrl, 'title' => (string) ($metadata['title'] ?? mb_substr($package->text, 0, 150)), 'description' => (string) ($metadata['description'] ?? '')]];
        }

        return $this->result($this->http($account)->post('https://api.linkedin.com/rest/posts', $body), '@restli');
    }
}
