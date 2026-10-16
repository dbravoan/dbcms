<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Contracts\Services\ModuleSettingsServiceInterface;
use App\Services\Media\RemoteMediaUrlPolicy;
use Illuminate\Validation\ValidationException;
use Modules\AiAssistant\Contracts\AiProviderRegistryInterface;

/**
 * Single source of truth for reading/writing AI provider credentials.
 *
 * Encapsulates the settings key naming convention so both the config bridge
 * and the model catalog share it without duplication.
 */
final class AiProviderSettings
{
    public const MODULE = 'ai_assistant';

    public const MASKED_KEY = '••••••••';

    public function __construct(private readonly ModuleSettingsServiceInterface $settings, private readonly AiProviderRegistryInterface $registry) {}

    public function activeProvider(string $modality = 'text'): string
    {
        if ($modality === 'image') {
            return (string) ($this->settings->get(self::MODULE, 'default_image_provider') ?: 'openai');
        }

        if ($modality === 'video') {
            return (string) ($this->settings->get(self::MODULE, 'default_video_provider') ?: 'higgsfield');
        }

        return (string) ($this->settings->get(self::MODULE, 'default_text_provider')
            ?: $this->settings->get(self::MODULE, 'default_provider')
            ?: 'gemini');
    }

    public function setActiveProvider(string $provider, string $modality = 'text'): void
    {
        if ($modality === 'image') {
            $this->settings->set(self::MODULE, 'default_image_provider', $provider);

            return;
        }

        if ($modality === 'video') {
            $this->settings->set(self::MODULE, 'default_video_provider', $provider);

            return;
        }

        $this->settings->set(self::MODULE, 'default_text_provider', $provider);
        $this->settings->set(self::MODULE, 'default_provider', $provider);
    }

    public function apiKey(string $provider): ?string
    {
        if ($provider === 'higgsfield') {
            $parts = $this->higgsfieldCredentials();

            return $parts['id'] !== null && $parts['secret'] !== null ? $parts['id'].':'.$parts['secret'] : null;
        }
        $stored = $this->settings->get(self::MODULE, $this->key($provider, 'api_key'))
            ?: config("services.{$provider}.key");

        return ! empty($stored) ? (string) $stored : null;
    }

    public function baseUrl(string $provider): ?string
    {
        $stored = $this->settings->get(self::MODULE, $this->key($provider, 'base_url'));

        return ! empty($stored) ? (string) $stored : null;
    }

    public function model(string $provider): ?string
    {
        $stored = $this->settings->get(self::MODULE, $this->key($provider, 'model'));

        return ! empty($stored) ? (string) $stored : null;
    }

    public function modelForModality(string $provider, string $modality): ?string
    {
        $stored = $this->settings->get(self::MODULE, $this->key($provider, $modality.'_model'));
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }
        $definition = $this->registry->resolve($provider)->definition();

        return count($definition->modalities) === 1 && $definition->supportsModality($modality) ? $this->model($provider) : null;
    }

    public function hasApiKey(string $provider): bool
    {
        return ! empty($this->apiKey($provider));
    }

    /**
     * Persist provider config while preserving masked/blank secrets.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(string $provider, array $data): void
    {
        // The domain policy must hold for every implementation of the persistence port.
        $payload = $this->prepare($provider, $data);
        if ($payload !== []) {
            $this->settings->setMany(self::MODULE, $payload);
        }
    }

    /** @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public function prepareModuleSettings(array $settings): array
    {
        $normalized = $settings;
        foreach ($this->registry->ids() as $provider) {
            $data = [];
            foreach (array_merge(['api_key', 'model', 'text_model', 'image_model', 'video_model', 'base_url'], $provider === 'higgsfield' ? ['api_key_id', 'api_key_secret'] : []) as $field) {
                $key = $this->key($provider, $field);
                if (array_key_exists($key, $settings)) {
                    if ($settings[$key] !== null && ! is_string($settings[$key])) {
                        throw new \InvalidArgumentException('Provider settings must be strings.');
                    }
                    $data[$field] = $settings[$key];
                    unset($normalized[$key]);
                }
            }
            if ($data !== []) {
                $normalized = array_merge($normalized, $this->prepare($provider, $data));
            }
        }

        return $normalized;
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function prepare(string $provider, array $data): array
    {
        $payload = [];
        if (! $this->registry->has($provider)) {
            throw new \InvalidArgumentException('Unknown provider.');
        }
        foreach (['text', 'image', 'video'] as $modality) {
            $value = $data[$modality.'_model'] ?? null;
            if (is_string($value) && $value !== '') {
                if (! $this->registry->resolve($provider)->definition()->supportsModality($modality)) {
                    throw new \InvalidArgumentException('Provider does not support the requested model modality.');
                }
                $payload[$this->key($provider, $modality.'_model')] = $value;
            }
        }

        $apiKey = $data['api_key'] ?? null;
        if ($provider === 'higgsfield' && $this->unmasked($apiKey) !== null) {
            throw ValidationException::withMessages(['api_key' => 'Higgsfield requires separate api_key_id and api_key_secret fields.']);
        }
        if ($provider !== 'higgsfield' && is_string($apiKey) && trim($apiKey) !== '' && ! str_starts_with($apiKey, '••••')) {
            $payload[$this->key($provider, 'api_key')] = $apiKey;
        }

        $explicitReplacement = $this->unmasked($apiKey) !== null;
        if ($provider === 'higgsfield') {
            $payload = array_merge($payload, $this->prepareHiggsfield($data));
            $explicitReplacement = $explicitReplacement || ($this->unmasked($data['api_key_id'] ?? null) !== null && $this->unmasked($data['api_key_secret'] ?? null) !== null);
        }

        $model = $data['model'] ?? null;
        if (is_string($model) && $model !== '') {
            $payload[$this->key($provider, 'model')] = $model;
        }

        $baseUrl = $data['base_url'] ?? null;
        if (is_string($baseUrl) && $baseUrl !== '') {
            $parts = parse_url($baseUrl);
            if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
                || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
                throw new \InvalidArgumentException('Invalid provider endpoint.');
            }
            $definition = $this->registry->resolve($provider)->definition();
            if (! in_array($provider, ['ollama', 'vllm', 'custom'], true)) {
                if (($parts['scheme'] ?? '') !== 'https') {
                    throw new \InvalidArgumentException('Cloud providers require HTTPS.');
                }
                app(RemoteMediaUrlPolicy::class)->resolve($baseUrl);
            }
            $previous = rtrim((string) ($this->baseUrl($provider) ?? $definition->defaultBaseUrl), '/');
            $changed = rtrim($baseUrl, '/') !== $previous;
            if ($changed && $this->hasApiKey($provider) && ! $explicitReplacement) {
                throw new \InvalidArgumentException('Changing the endpoint requires an explicit replacement API key.');
            }
            $payload[$this->key($provider, 'base_url')] = rtrim($baseUrl, '/');
        }

        return $payload;
    }

    /** @return array{id:?string,secret:?string} */
    public function higgsfieldCredentials(): array
    {
        $id = $this->settings->get(self::MODULE, 'higgsfield_api_key_id');
        $secret = $this->settings->get(self::MODULE, 'higgsfield_api_key_secret');

        return ['id' => is_string($id) && $id !== '' ? $id : null, 'secret' => is_string($secret) && $secret !== '' ? $secret : null];
    }

    /**
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    private function prepareHiggsfield(array $data): array
    {
        $parts = $this->higgsfieldCredentials();
        $id = $this->unmasked($data['api_key_id'] ?? null);
        $secret = $this->unmasked($data['api_key_secret'] ?? null);
        if ($id === null && $secret === null) {
            return [];
        }
        $id = $id ?? $parts['id'];
        $secret = $secret ?? $parts['secret'];
        if ($id === null || $secret === null || $id === '' || $secret === '' || preg_match('/[\s:]/u', $id) || preg_match('/[\r\n\x00]/', $secret)) {
            throw ValidationException::withMessages(['api_key_id' => 'Introduce el ID y el secreto del mismo par de Higgsfield. Los campos vacíos conservan una credencial completa existente.']);
        }

        return ['higgsfield_api_key_id' => $id, 'higgsfield_api_key_secret' => $secret];
    }

    private function unmasked(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' && ! str_starts_with($value, '••••') ? trim($value) : null;
    }

    public function isConfigured(string $provider): bool
    {
        return $this->registry->resolve($provider)->definition()->requiresApiKey
            ? $this->hasApiKey($provider) : $this->baseUrl($provider) !== null;
    }

    /**
     * @param  list<string>  $candidateProviders
     */
    public function hasAvailableProviderForModality(string $modality, array $candidateProviders): bool
    {
        foreach ($candidateProviders as $providerId) {
            if ($this->registry->resolve($providerId)->definition()->supportsModality($modality) && $this->isConfigured($providerId)) {
                return true;
            }
        }

        return false;
    }

    public function key(string $provider, string $field): string
    {
        return "{$provider}_{$field}";
    }
}
