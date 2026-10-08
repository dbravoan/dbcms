<?php

declare(strict_types=1);

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Services\Storage\ExternalStorageService;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

final class StorageCredentialBoundaryTest extends TestCase
{
    public function test_changing_destination_does_not_reuse_credentials_or_accept_masked_replacements(): void
    {
        $saved = [];
        $values = ['external_storage_provider' => 'minio', 'external_storage_endpoint' => 'https://first.example.test', 'external_storage_access_key' => 'old-access', 'external_storage_secret_key' => Crypt::encryptString('old-secret')];
        $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
        $settings->shouldReceive('get')->andReturnUsing(static function (string $key, mixed $default = null) use (&$values): mixed {
            return $values[$key] ?? $default;
        });
        $settings->shouldReceive('setMany')->once()->andReturnUsing(function (array $payload) use (&$saved): void {
            $saved = $payload;
        });
        (new ExternalStorageService($settings))->save(['endpoint' => 'https://second.example.test', 'access_key' => '••••••••', 'secret_key' => '••••••••']);
        self::assertSame('', $saved['external_storage_access_key']);
        self::assertSame('', $saved['external_storage_secret_key']);
    }

    public function test_an_explicit_new_pair_is_encrypted_and_configuration_never_exposes_credentials(): void
    {
        $values = ['external_storage_provider' => 'minio', 'external_storage_endpoint' => 'https://first.example.test'];
        $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
        $settings->shouldReceive('get')->andReturnUsing(static function (string $key, mixed $default = null) use (&$values): mixed {
            return $values[$key] ?? $default;
        });
        $settings->shouldReceive('setMany')->once()->andReturnUsing(function (array $payload) use (&$values): void {
            $values = array_replace($values, $payload);
        });
        $storage = new ExternalStorageService($settings);
        $storage->save(['endpoint' => 'https://second.example.test', 'bucket' => 'media', 'access_key' => 'new-access', 'secret_key' => 'new-secret']);
        self::assertNotSame('new-secret', $values['external_storage_secret_key']);
        self::assertSame('new-secret', Crypt::decryptString($values['external_storage_secret_key']));
        self::assertSame('••••••••', $storage->configuration()['access_key']);
        self::assertSame('••••••••', $storage->configuration()['secret_key']);
    }
}
