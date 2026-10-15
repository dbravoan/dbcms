<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Connectors;

use App\DTOs\Marketing\CreativePackage;
use Modules\SocialPublishing\DTOs\OperationResult;
use Modules\SocialPublishing\Models\SocialAccount;

final class YouTubeConnector extends AbstractConnector
{
    public function capabilities(): array
    {
        return ['formats' => ['short_video'], 'resumable_upload' => true, 'publish_grant' => 'https://www.googleapis.com/auth/youtube.upload', 'separate_visibility_confirmation' => true];
    }

    public function validate(CreativePackage $package, array $metadata): void
    {
        $this->media($package, ['video/mp4'], 1);
        $this->ensure(count($package->artifacts) === 1 && trim((string) ($metadata['title'] ?? '')) !== '' && mb_strlen((string) $metadata['title']) <= 100, 'YouTube necesita un vídeo final y un título de hasta 100 caracteres.');
        $this->ensure(array_key_exists('made_for_kids', $metadata) && is_bool($metadata['made_for_kids']), 'Debes indicar si el vídeo está creado para niños.');
    }

    public function mutation(string $step): bool
    {
        return ! in_array($step, ['poll', 'confirm', 'reconcile'], true);
    }

    public function step(string $step, SocialAccount $account, CreativePackage $package, array $receipt, array $metadata): OperationResult
    {
        $upload = preg_match('/^upload:([0-9]+)$/D', $step, $parts) === 1;
        if (! in_array($step, ['prepare', 'poll', 'publish', 'confirm', 'reconcile'], true) && ! $upload) {
            return new OperationResult('failed', error: 'unsupported_connector_operation');
        }
        $file = $package->artifacts[0];
        if ($upload && (int) $parts[1] >= $file->sizeBytes) {
            return new OperationResult('pending_confirmation', error: 'resumable_offset_requires_confirmation');
        }
        if ($step === 'prepare') {
            $r = $this->http($account)->withHeaders(['X-Upload-Content-Length' => (string) $file->sizeBytes, 'X-Upload-Content-Type' => $file->mimeType])->post('https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status', ['snippet' => ['title' => (string) $metadata['title'], 'description' => $package->text, 'categoryId' => (string) ($metadata['category_id'] ?? '22')], 'status' => ['privacyStatus' => 'private', 'selfDeclaredMadeForKids' => (bool) $metadata['made_for_kids'], 'containsSyntheticMedia' => (bool) ($metadata['synthetic_media'] ?? false)]]);
            if (! $r->successful()) {
                return $this->result($r, 'id', 'prepared');
            }
            $url = $r->header('Location');
            if ($url === '') {
                return new OperationResult('pending_confirmation', error: 'resumable_session_missing');
            }

            return new OperationResult('preparing', 'upload:0', receipt: ['session' => $this->signedUpload($url, 'googleapis.com'), 'offset' => 0], retryAfter: 0);
        }
        if (str_starts_with($step, 'upload:')) {
            $offset = (int) substr($step, 7);
            $length = min(4194304, $file->sizeBytes - $offset);
            $stream = $this->files->readStream($file);
            try {
                $bytes = stream_get_contents($stream, $length, $offset);
            } finally {
                fclose($stream);
            }
            $this->ensure(is_string($bytes) && strlen($bytes) === $length, 'No se pudo leer el fragmento completo.');
            $r = $this->http($account)->withHeaders(['Content-Length' => (string) $length, 'Content-Range' => 'bytes '.$offset.'-'.($offset + $length - 1).'/'.$file->sizeBytes])->withBody($bytes, $file->mimeType)->send('PUT', $this->signedUpload((string) $receipt['session'], 'googleapis.com'));
            if ($r->status() === 308) {
                if (! preg_match('/^bytes=0-([0-9]+)$/D', $r->header('Range'), $m)) {
                    return new OperationResult('pending_confirmation', error: 'upload_offset_unconfirmed');
                }
                $receipt['offset'] = (int) $m[1] + 1;

                return new OperationResult('preparing', 'upload:'.$receipt['offset'], receipt: $receipt, retryAfter: 0);
            }
            $result = $this->result($r, 'id', 'prepared');

            return $result->state === 'prepared' ? new OperationResult('remote_processing', 'poll', receipt: $receipt + ['video_id' => $result->receipt['id']]) : $result;
        }
        if ($step === 'reconcile' && isset($receipt['session']) && ! isset($receipt['video_id'])) {
            $r = $this->http($account)->withHeaders(['Content-Length' => '0', 'Content-Range' => 'bytes */'.$file->sizeBytes])->withBody('', 'application/octet-stream')->send('PUT', $this->signedUpload((string) $receipt['session'], 'googleapis.com'));
            if ($r->status() === 308) {
                $offset = 0;
                if ($r->header('Range') !== '' && ! preg_match('/^bytes=0-([0-9]+)$/D', $r->header('Range'), $m)) {
                    return new OperationResult('pending_confirmation', error: 'upload_offset_unconfirmed');
                }
                if (isset($m[1])) {
                    $offset = (int) $m[1] + 1;
                }
                $receipt['offset'] = $offset;

                return new OperationResult('preparing', 'upload:'.$offset, receipt: $receipt, retryAfter: 0);
            }
            $result = $this->result($r, 'id', 'prepared');

            return $result->state === 'prepared' ? new OperationResult('remote_processing', 'poll', receipt: $receipt + ['video_id' => $result->receipt['id']]) : new OperationResult('pending_confirmation', error: 'upload_session_unresolved');
        }
        if ($step === 'publish') {
            $id = (string) $receipt['video_id'];
            $r = $this->http($account)->put('https://www.googleapis.com/youtube/v3/videos?part=status', ['id' => $id, 'status' => ['privacyStatus' => 'public', 'selfDeclaredMadeForKids' => (bool) $metadata['made_for_kids'], 'containsSyntheticMedia' => (bool) ($metadata['synthetic_media'] ?? false)]]);
            $result = $this->result($r, 'id', 'prepared');

            return $result->state === 'prepared' ? new OperationResult('remote_processing', 'confirm', receipt: $receipt) : $result;
        }
        $id = (string) ($receipt['video_id'] ?? '');
        if ($id === '') {
            return new OperationResult('pending_confirmation', error: 'video_identity_missing');
        }
        $r = $this->http($account)->get('https://www.googleapis.com/youtube/v3/videos', ['id' => $id, 'part' => 'snippet,status,processingDetails']);
        $v = $r->json('items.0');
        if (! $r->successful() || ! is_array($v) || ($v['id'] ?? null) !== $id || data_get($v, 'snippet.channelId') !== $account->remote_id) {
            return new OperationResult('pending_confirmation', error: 'youtube_owner_unconfirmed');
        }
        if (in_array(data_get($v, 'status.uploadStatus'), ['failed', 'rejected', 'deleted'], true)) {
            return new OperationResult('failed', error: 'youtube_processing_failed');
        }
        if (data_get($v, 'status.uploadStatus') === 'processed' && data_get($v, 'processingDetails.processingStatus') === 'succeeded') {
            if (data_get($v, 'status.privacyStatus') === 'public') {
                return new OperationResult('published', remoteId: $id, receipt: $receipt);
            }
            if ($step === 'poll') {
                return new OperationResult('ready_to_publish', 'publish', receipt: $receipt, retryAfter: 0);
            }

            return new OperationResult('remote_processing', 'confirm', receipt: $receipt, error: 'youtube_public_visibility_pending', retryAfter: 120);
        }

        return new OperationResult('remote_processing', $step === 'confirm' ? 'confirm' : 'poll', receipt: $receipt, retryAfter: 120);
    }

    public function metrics(SocialAccount $account, string $remote): array
    {
        $r = $this->http($account)->get('https://www.googleapis.com/youtube/v3/videos', ['id' => $remote, 'part' => 'snippet,statistics']);
        $this->ensure($r->successful() && $r->json('items.0.id') === $remote && $r->json('items.0.snippet.channelId') === $account->remote_id, 'Vídeo ajeno o métricas no disponibles.');
        $values = [];
        foreach (['views' => 'viewCount', 'likes' => 'likeCount', 'comments' => 'commentCount'] as $n => $f) {
            $v = $r->json('items.0.statistics.'.$f);
            $valid = is_string($v) && ctype_digit($v) && strlen($v) <= 15;
            $values[$n] = ['value' => $valid ? (int) $v : null, 'availability' => $valid ? 'available' : 'unavailable'];
        }

        return ['source' => 'youtube-owned-video', 'window' => 'lifetime', 'availability' => 'available', 'values' => $values];
    }
}
