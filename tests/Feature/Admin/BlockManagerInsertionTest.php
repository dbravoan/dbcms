<?php

declare(strict_types=1);

use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
});

test('block manager blade component contains hover insertion zones at any position', function (): void {
    $rendered = Blade::render('<x-admin.block-manager :blocks="[]" :languages="[]" :currentLocale="\'es\'" />');

    // Hover insertion triggers (start + between blocks, including the end via index + 1)
    expect($rendered)->toContain('openAddModalAt(0)')
        ->and($rendered)->toContain('openAddModalAt(index + 1)');

    // The redundant standalone footer button must be gone (the last block's
    // hover zone already offers "add section at the end").
    expect($rendered)->not->toContain('Añadir nueva sección al final');

    // Responsive hover dropzones
    expect($rendered)->toContain('group/insert')
        ->and($rendered)->toContain('Añadir sección al principio')
        ->and($rendered)->toContain('Añadir sección al final');

    // Unified empty state
    expect($rendered)->toContain('Añadir primera sección');

    // Agnostic external insertion event listener
    expect($rendered)->toContain('cms-insert-block');
});

test('block manager component contains position badge indicator in modal', function (): void {
    $rendered = Blade::render('<x-admin.block-manager :blocks="[]" :languages="[]" :currentLocale="\'es\'" />');

    expect($rendered)->toContain('Al principio (#1)')
        ->and($rendered)->toContain('En posición #')
        ->and($rendered)->toContain('Al final');
});

test('block manager has zero hardcoded references to ai_assistant', function (): void {
    $content = file_get_contents(resource_path('views/components/admin/block-manager.blade.php'));
    expect($content)->not->toContain('ai_assistant')
        ->and($content)->not->toContain('route(\'admin.ai_assistant');
});
