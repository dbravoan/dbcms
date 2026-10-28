<?php

declare(strict_types=1);

use App\Core\Blocks\Contracts\BlockRegistryInterface;

/** @return array<string,array<string,mixed>> */
function flatBlockSchema(array $schema): array
{
    $fields = [];
    foreach ($schema as $key => $field) {
        $fields[$key] = $field;
        if (($field['type'] ?? null) === 'repeater') {
            foreach (flatBlockSchema((array) ($field['fields'] ?? [])) as $childKey => $child) {
                $fields[$key.'.'.$childKey] = $child;
            }
        }
    }

    return $fields;
}

test('every block schema exposes supported controls, labels and media metadata', function (): void {
    $supported = ['text', 'string', 'textarea', 'url', 'email', 'number', 'integer', 'boolean', 'select', 'json', 'array', 'list', 'repeater', 'media'];
    $registry = app(BlockRegistryInterface::class);

    foreach ($registry->all() as $type => $block) {
        foreach (flatBlockSchema($block->getEditorSchema()) as $key => $field) {
            $fieldType = (string) ($field['type'] ?? 'text');
            expect(in_array($fieldType, $supported, true))->toBeTrue("{$type}.{$key} uses unsupported type {$fieldType}");
            expect(trim((string) ($field['label'] ?? '')))->not->toBe('', "{$type}.{$key} needs a label");
            if ($fieldType === 'media') {
                expect(in_array($field['media_kind'] ?? null, ['image', 'video'], true))->toBeTrue("{$type}.{$key} needs an image or video media kind");
            }
            if ($fieldType === 'repeater') {
                expect((array) ($field['fields'] ?? []))->not->toBeEmpty("{$type}.{$key} needs child fields");
            }
        }
    }
});

test('known media payload fields use the media picker contract', function (string $type, string $path, string $kind): void {
    $schema = app(BlockRegistryInterface::class)->get($type)?->getEditorSchema() ?? [];
    $field = $schema;
    foreach (explode('.', $path) as $segment) {
        if ($segment === '*') {
            $field = (array) ($field['fields'] ?? []);
        } else {
            $field = (array) ($field[$segment] ?? []);
        }
    }

    expect($field['type'] ?? null)->toBe('media')
        ->and($field['media_kind'] ?? null)->toBe($kind);
})->with([
    ['hero', 'image_url', 'image'],
    ['hero', 'video_url', 'video'],
    ['carousel', 'slides.*.image_url', 'image'],
    ['video', 'video_url', 'video'],
    ['video', 'poster_url', 'image'],
    ['testimonials', 'items.*.avatar', 'image'],
    ['before_after', 'image_url', 'image'],
    ['before_after', 'after_image_url', 'image'],
]);
