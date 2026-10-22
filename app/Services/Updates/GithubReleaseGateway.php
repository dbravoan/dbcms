<?php

declare(strict_types=1);

namespace App\Services\Updates;

use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

final class GithubReleaseGateway
{
    /**
     * @return list<array<string,mixed>> */
    public function listStable(): array
    {
        $data = json_decode($this->contents($this->api().'/releases?per_page=100'), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($data) || ! array_is_list($data)) {
            throw new \DomainException('Respuesta de releases no válida.');
        }
        $releases = [];
        foreach ($data as $release) {
            if (is_array($release) && $this->stable($release)) {
                $release['version'] = substr($release['tag_name'], 1);
                $releases[] = $release;
            }
        }
        usort($releases, fn (array $a, array $b): int => version_compare($b['version'], $a['version']));

        return $releases;
    }

    /**
     * @return array<string,mixed> */
    public function release(string $version): array
    {
        if (! preg_match('/^\d+\.\d+\.\d+$/D', $version)) {
            throw new \DomainException('Versión no válida.');
        }
        $release = json_decode($this->contents($this->api().'/releases/tags/v'.$version), true, 64, JSON_THROW_ON_ERROR);
        if (! is_array($release) || ! $this->stable($release) || $release['tag_name'] !== 'v'.$version) {
            throw new \DomainException('La versión no es una release estable publicada.');
        }
        $release['version'] = $version;

        return $release;
    }

    /**
     * @param array<string,mixed> $release
     * @return array<string,mixed> */
    public function asset(array $release, string $basename): array
    {
        foreach (($release['assets'] ?? []) as $asset) {
            if (is_array($asset) && ($asset['name'] ?? '') === $basename && is_string($asset['browser_download_url'] ?? null)) {
                $this->validateUrl($asset['browser_download_url']);

                return $asset;
            }
        }
        throw new \DomainException('La release no contiene el artefacto firmado requerido.');
    }

    public function contents(string $url, int $maxBytes = 2097152): string
    {
        $stream = $this->request($url);
        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(65536);
            if (strlen($body) > $maxBytes) {
                throw new \DomainException('La respuesta de actualización excede el límite.');
            }
        }

        return $body;
    }

    public function download(string $url, string $target, int $maxBytes): void
    {
        $stream = $this->request($url);
        $handle = fopen($target, 'xb');
        if ($handle === false) {
            throw new \RuntimeException('No se puede almacenar la descarga.');
        }
        $written = 0;
        try {
            chmod($target, 0600);
            while (! $stream->eof()) {
                $chunk = $stream->read(65536);
                $written += strlen($chunk);
                if ($written > $maxBytes || fwrite($handle, $chunk) !== strlen($chunk)) {
                    throw new \DomainException('Descarga interrumpida o demasiado grande.');
                }
            }
        } finally {
            fclose($handle);
        }
    }

    public function validateUrl(string $url): void
    {
        $parts = parse_url($url);
        $hosts = ['api.github.com', 'github.com', 'objects.githubusercontent.com', 'release-assets.githubusercontent.com'];
        if (! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || ! in_array($parts['host'] ?? '', $hosts, true) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new \DomainException('Destino de actualización no autorizado.');
        }
        if (($parts['host'] ?? '') === 'github.com' && ! str_starts_with($parts['path'] ?? '', '/'.$this->repository().'/releases/download/')) {
            throw new \DomainException('Descarga ajena al repositorio de dbCMS.');
        }
    }

    private function request(string $url): StreamInterface
    {
        for ($redirect = 0; $redirect <= 5; $redirect++) {
            $this->validateUrl($url);
            $response = Http::withHeaders(['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'])->connectTimeout(10)->timeout(120)->withOptions(['allow_redirects' => false, 'stream' => true])->get($url);
            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                $url = $response->header('Location');

                continue;
            }
            $response->throw();
            if (! $response->successful()) {
                throw new \DomainException('No se pudo descargar la actualización.');
            }

            return $response->toPsrResponse()->getBody();
        }
        throw new \DomainException('Demasiadas redirecciones de actualización.');
    }

    private function api(): string
    {
        return 'https://api.github.com/repos/'.$this->repository();
    }

    private function repository(): string
    {
        $repository = (string) config('updates.repository', 'dbravoan/dbcms');
        if ($repository !== 'dbravoan/dbcms') {
            throw new \DomainException('Origen del núcleo no admitido.');
        }

        return $repository;
    }

    /**
     * @param array<string,mixed> $release */
    private function stable(array $release): bool
    {
        return ($release['draft'] ?? true) === false && ($release['prerelease'] ?? true) === false && is_string($release['published_at'] ?? null) && is_string($release['tag_name'] ?? null) && preg_match('/^v\d+\.\d+\.\d+$/D', $release['tag_name']) === 1;
    }
}
