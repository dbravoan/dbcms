<?php

declare(strict_types=1);

namespace App\Core\Updates;

final class SignedManifestVerifier
{
    /** @param array<string,string> $trustedKeys */
    public function verify(string $manifest, string $signature, array $trustedKeys): ReleaseManifest
    {
        try {
            $envelope = json_decode($signature, true, 16, JSON_THROW_ON_ERROR);
            $data = json_decode($manifest, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \DomainException('Manifiesto o firma ilegible.', 0, $e);
        }
        if (! is_array($envelope) || ! is_string($envelope['key_id'] ?? null) || ! isset($trustedKeys[$envelope['key_id']]) || ! is_string($envelope['signature'] ?? null)) {
            throw new \DomainException('Clave de publicación desconocida.');
        }
        $decoded = base64_decode($envelope['signature'], true);
        $key = openssl_pkey_get_public($trustedKeys[$envelope['key_id']]);
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if ($decoded === false || $key === false || ! is_array($details) || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 3072 || openssl_verify($manifest, $decoded, $key, OPENSSL_ALGO_SHA256) !== 1 || ! is_array($data)) {
            throw new \DomainException('Firma de actualización no válida.');
        }

        $validated = new ReleaseManifest($data, hash('sha256', $manifest));
        foreach ($data['distributions'] as $distribution) {
            foreach ($distribution['files'] as $path => $hash) {
                if (preg_match('~^resources/updates/trusted-keys/([a-zA-Z0-9_-]+)\.pub$~D', $path, $match) && ! isset($trustedKeys[$match[1]])) {
                    $next = $data['next_keys'][$match[1]] ?? null;
                    if (! is_string($next) || hash('sha256', $next) !== $hash) {
                        throw new \DomainException('La clave nueva no está autorizada por la firma vigente.');
                    }
                }
            }
        }

        return $validated;
    }
}
