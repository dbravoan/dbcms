<?php

declare(strict_types=1);

namespace Modules\Analytics\Services;

use InvalidArgumentException;
use Modules\Analytics\Contracts\Ga4SettingsNormalizerInterface;

final class Ga4SettingsNormalizer implements Ga4SettingsNormalizerInterface
{
    /** @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public function normalize(array $settings): array
    {
        if (array_key_exists('ga4_property_id', $settings)) {
            if (! is_string($settings['ga4_property_id']) && ! is_int($settings['ga4_property_id']) && $settings['ga4_property_id'] !== null) {
                throw new InvalidArgumentException('El ID de propiedad de GA4 debe contener sólo dígitos.');
            }
            $id = trim((string) $settings['ga4_property_id']);
            if ($id !== '' && ! preg_match('/\A[0-9]{1,20}\z/', $id)) {
                throw new InvalidArgumentException('El ID de propiedad de GA4 debe contener sólo dígitos.');
            }
            $settings['ga4_property_id'] = $id;
        }
        if (array_key_exists('ga4_service_account_json', $settings)) {
            $json = $settings['ga4_service_account_json'];
            if (! is_string($json) && $json !== null) {
                throw new InvalidArgumentException('La credencial de GA4 debe ser un documento JSON.');
            }
            if (is_string($json) && trim($json) !== '' && ! str_starts_with($json, '••••')) {
                $this->credentials($json);
            }
        }
        if (array_key_exists('ga4_conversion_events', $settings)) {
            if (! is_string($settings['ga4_conversion_events']) && $settings['ga4_conversion_events'] !== null) {
                throw new InvalidArgumentException('Los eventos deben ser una lista de nombres separados por comas.');
            }
            $names = preg_split('/[\s,]+/', (string) $settings['ga4_conversion_events'], -1, PREG_SPLIT_NO_EMPTY) ?: [];
            foreach ($names as $name) {
                if (! preg_match('/\A[a-zA-Z][a-zA-Z0-9_]{0,39}\z/', $name)) {
                    throw new InvalidArgumentException('Los eventos deben tener nombres de hasta 40 letras, números o guiones bajos.');
                }
            }
            $settings['ga4_conversion_events'] = implode(',', array_unique($names));
        }

        return $settings;
    }

    /** @return array{email:string,key:string,key_id:?string} */
    public function credentials(string $json): array
    {
        try {
            if (strlen($json) > 65536) {
                throw new InvalidArgumentException;
            }
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ($data['type'] ?? null) !== 'service_account'
                || ! is_string($data['client_email'] ?? null) || ! filter_var($data['client_email'], FILTER_VALIDATE_EMAIL)
                || ! str_ends_with($data['client_email'], '.gserviceaccount.com') || ! is_string($data['private_key'] ?? null)) {
                throw new InvalidArgumentException;
            }
            $key = @openssl_pkey_get_private($data['private_key']);
            $details = $key === false ? false : openssl_pkey_get_details($key);
            if ($details === false || $details['type'] !== OPENSSL_KEYTYPE_RSA || $details['bits'] < 2048) {
                throw new InvalidArgumentException;
            }
            $keyId = $data['private_key_id'] ?? null;
            if ($keyId !== null && (! is_string($keyId) || ! preg_match('/\A[a-zA-Z0-9_-]{1,128}\z/', $keyId))) {
                throw new InvalidArgumentException;
            }

            return ['email' => $data['client_email'], 'key' => $data['private_key'], 'key_id' => $keyId];
        } catch (\Throwable) {
            throw new InvalidArgumentException('La credencial no es una cuenta de servicio RSA válida. No se ha guardado.');
        }
    }
}
