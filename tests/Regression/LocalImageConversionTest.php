<?php

declare(strict_types=1);

use App\Contracts\Services\GeneralSettingsServiceInterface;
use App\Services\Media\MediaManagerService;
use App\Services\Media\MediaUrlResolver;
use App\Services\Media\SvgSanitizer;
use App\Services\Storage\ExternalStorageService;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

uses(TestCase::class);

it('stores one converted image with a MIME type matching its WebP bytes', function (): void {
    $directory = sys_get_temp_dir().'/dbcms-media-audit-'.bin2hex(random_bytes(8));
    File::makeDirectory($directory, 0755, true);
    $settings = Mockery::mock(GeneralSettingsServiceInterface::class);
    $settings->shouldReceive('get')->with('external_storage_provider', 'local')->andReturn('local');
    $storage = new ExternalStorageService($settings);
    $service = Mockery::mock(MediaManagerService::class, [$storage, new MediaUrlResolver($storage), new SvgSanitizer])->makePartial();
    $service->shouldReceive('getUploadDir')->andReturn($directory);
    Schema::shouldReceive('hasTable')->with('media_files')->once()->andReturnFalse();
    try {
        $result = $service->upload(UploadedFile::fake()->image('audit.png', 64, 36));
        expect($result['mime_type'])->toBe('image/webp');
        expect(File::mimeType($directory.'/'.$result['filename']))->toBe($result['mime_type']);
        expect(File::files($directory))->toHaveCount(1);
    } finally {
        File::deleteDirectory($directory);
    }
});
