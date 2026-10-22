<?php

declare(strict_types=1);

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Models\MediaFile;
use App\Services\Media\MediaManagerService;
use App\Services\Media\MediaUrlResolver;
use App\Services\Media\SvgSanitizer;
use App\Services\Storage\ExternalStorageService;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

function mediaDeletionWithRemoteFailure(bool $throw): MediaManagerService
{
    $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
    $values = ['external_storage_provider' => 's3', 'external_storage_bucket' => 'audit', 'external_storage_access_key' => 'audit', 'external_storage_secret_key' => Crypt::encryptString('audit')];
    $settings->shouldReceive('get')->andReturnUsing(fn ($key, $default = null) => $values[$key] ?? $default);
    $external = new ExternalStorageService($settings);
    $uploads = Storage::disk('uploads');
    $disk = Mockery::mock(FilesystemAdapter::class);
    $expectation = $disk->shouldReceive('delete')->with('remote.png')->once();
    if ($throw) {
        $expectation->andThrow(new RuntimeException('Remote failure'));
    } else {
        $expectation->andReturn(false);
    }
    Storage::shouldReceive('disk')->with('uploads')->andReturn($uploads);
    Storage::shouldReceive('disk')->with(ExternalStorageService::DISK)->andReturn($disk);
    Storage::shouldReceive('forgetDisk')->with(ExternalStorageService::DISK)->andReturnNull();

    return new MediaManagerService($external, app(MediaUrlResolver::class), app(SvgSanitizer::class));
}

test('binary image conversion leaves one asset and listing does not duplicate it', function (): void {
    $file = UploadedFile::fake()->image('original.png', 20, 20);
    $manager = app(MediaManagerService::class);
    $result = $manager->saveFromBinary((string) file_get_contents($file->getPathname()), 'png', 'Binary image');
    expect($result['filename'])->toEndWith('.webp');
    expect(File::files($manager->getUploadDir()))->toHaveCount(1);
    expect(MediaFile::count())->toBe(1);
    $manager->listMedia();
    expect(MediaFile::count())->toBe(1);
});

test('failed remote deletion retains metadata and local cache for retry', function (bool $throw): void {
    $record = MediaFile::create(['uuid' => (string) Str::uuid(), 'filename' => 'remote.png', 'original_name' => 'remote.png', 'mime_type' => 'image/png', 'size_bytes' => 1, 'url' => 'https://example.test/remote.png']);
    Storage::disk('uploads')->put('remote.png', 'cached');
    $manager = mediaDeletionWithRemoteFailure($throw);
    expect($manager->delete('remote.png'))->toBeFalse();
    expect(MediaFile::whereKey($record->id)->exists())->toBeTrue();
    expect(Storage::disk('uploads')->exists('remote.png'))->toBeTrue();
})->with([true, false]);

test('successful local deletion removes file and metadata together', function (): void {
    $manager = app(MediaManagerService::class);
    $result = $manager->saveFromBinary('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'svg', 'Delete me');
    expect($manager->delete($result['filename']))->toBeTrue();
    expect(MediaFile::whereKey($result['id'])->exists())->toBeFalse();
    expect(File::exists($manager->getUploadDir().'/'.$result['filename']))->toBeFalse();
});
