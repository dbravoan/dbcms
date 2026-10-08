<?php

declare(strict_types=1);

use App\Services\Capabilities\CmsActionService;
use App\Services\Media\MediaManagerService;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

test('shared media boundary rejects a misleading extension before storing it', function (): void {
    $file = UploadedFile::fake()->createWithContent('portrait.jpg', '<html><script>alert(1)</script></html>');
    expect(fn () => app(MediaManagerService::class)->upload($file))->toThrow(ValidationException::class);
});

test('MCP upload rejects content that the backoffice also refuses', function (): void {
    expect(fn () => app(CmsActionService::class)->mediaUpload([
        'filename' => 'portrait.jpg', 'content_base64' => base64_encode('<html>not an image</html>'),
    ]))->toThrow(ValidationException::class);
});

test('shared media boundary stores an actual image with truthful metadata', function (): void {
    $result = app(MediaManagerService::class)->upload(UploadedFile::fake()->image('portrait.png', 10, 10));
    expect($result['is_image'])->toBeTrue()
        ->and($result['mime_type'])->toBeIn(['image/png', 'image/webp'])
        ->and($result['width'])->toBe(10);
});

test('shared media boundary enforces the backoffice 50 MB limit', function (): void {
    $file = UploadedFile::fake()->create('oversized.txt', 51201, 'text/plain');
    expect(fn () => app(MediaManagerService::class)->upload($file))->toThrow(ValidationException::class);
});

test('shared media boundary refuses a text payload renamed as an image', function (): void {
    $file = UploadedFile::fake()->createWithContent('portrait.png', 'Ordinary plain text');
    expect(fn () => app(MediaManagerService::class)->upload($file))->toThrow(ValidationException::class);
});
