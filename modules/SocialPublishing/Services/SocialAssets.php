<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\Services;

use App\Core\Capabilities\ActorContext;
use Modules\Marketing\Contracts\MarketingArtifactStoreInterface;
use Modules\Marketing\DTOs\CreativePackage;
use Modules\SocialPublishing\Models\SocialDelivery;
use Modules\SocialPublishing\Models\SocialPublication;
use Modules\SocialPublishing\Models\SocialRevision;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final readonly class SocialAssets
{
    public function __construct(private MarketingArtifactStoreInterface $files, private PublicationService $publications, private SocialAuthorization $auth) {}

    public function preview(string $publication, int $index, ActorContext $actor): BinaryFileResponse
    {
        $this->auth->authorize($actor, 'read:social-publishing');
        $p = SocialPublication::where('installation_id', $this->auth->installation())->findOrFail($publication);

        return $this->file(SocialRevision::findOrFail($p->revision_id), $index);
    }

    public function publicFile(string $delivery, int $index): BinaryFileResponse
    {
        $d = SocialDelivery::where('installation_id', $this->auth->installation())->findOrFail($delivery);
        $p = SocialPublication::findOrFail($d->publication_id);
        abort_unless(! $d->cancel_requested && $p->revision_id === $d->revision_id && $this->publications->approved($p), 404);

        return $this->file(SocialRevision::findOrFail($d->revision_id), $index);
    }

    private function file(SocialRevision $revision, int $index): BinaryFileResponse
    {
        $package = CreativePackage::fromArray($revision->package);
        $file = $package->artifacts[$index] ?? null;
        abort_unless($file && $this->files->verify($file), 404);

        return response()->file($this->files->absolutePath($file), ['Content-Type' => $file->mimeType, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow, noarchive']);
    }

    public function export(string $publication, ActorContext $actor): BinaryFileResponse
    {
        $this->auth->authorize($actor, 'read:social-publishing');
        $p = SocialPublication::where('installation_id', $this->auth->installation())->findOrFail($publication);
        $r = SocialRevision::findOrFail($p->revision_id);
        $package = CreativePackage::fromArray($r->package);
        $path = tempnam(sys_get_temp_dir(), 'social-export-');
        if ($path === false) {
            throw new \RuntimeException('No se pudo preparar la exportación.');
        }
        $zip = new \ZipArchive;
        if ($zip->open($path, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('No se pudo abrir la exportación.');
        }
        $zip->addFromString('texto.txt', $package->text."\n\n".($package->destinationUrl ?? ''));
        $zip->addFromString('revision.json', json_encode(['revision' => $r->id, 'payload_hash' => $r->payload_hash, 'metadata' => $r->metadata, 'provenance' => $package->provenance], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        foreach ($package->artifacts as $index => $file) {
            abort_unless($this->files->verify($file), 409);
            $zip->addFile($this->files->absolutePath($file), sprintf('%02d-', $index + 1).basename($file->path));
        }
        $zip->close();

        return response()->download($path, 'publicacion-'.$r->id.'.zip', ['Cache-Control' => 'private, no-store'])->deleteFileAfterSend(true);
    }
}
