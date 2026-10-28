<?php

declare(strict_types=1);

use Tests\TestCase;

uses(TestCase::class);

test('appearance media pickers target the active scope and include the video poster', function (): void {
    $source = (string) file_get_contents(resource_path('views/components/admin/block-manager.blade.php'));

    expect($source)
        ->toContain("openMediaPickerForObject(appearanceModel(), 'bg_image_url', 'image')")
        ->toContain("openMediaPickerForObject(appearanceModel(), 'bg_video_url', 'video')")
        ->toContain("openMediaPickerForObject(appearanceModel(), 'bg_video_poster', 'image')")
        ->not->toContain("openMediaPickerForObject(blocks[editingAppearanceIndex], 'bg_image_url')");
});

test('the media modal filters by requested kind and notifies cancellation', function (): void {
    $source = (string) file_get_contents(resource_path('views/components/admin/filemanager-modal.blade.php'));

    expect($source)
        ->toContain('allowsKind(file)')
        ->toContain("['image', 'video', 'doc'].includes(requested)")
        ->toContain('filemanager-closed')
        ->toContain(":accept=\"kind === 'image' ? 'image/*'")
        ->toContain('close()');
});
