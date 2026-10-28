<?php

declare(strict_types=1);

use App\Contracts\Services\AdminThemeServiceInterface;
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Models\Language;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Js;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\Support\CmsFixtures;
use Tests\Support\ReleaseSurfaceComposition;

uses(ReleaseSurfaceComposition::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
});

/** @return array{DOMDocument, DOMXPath} */
function blockEditorDom(string $html): array
{
    // libxml's HTML4 parser cannot tokenize Alpine's @ event shorthand.
    // Preserve the same browser expression through its supported long form.
    $html = preg_replace('/(\s)@([a-z][a-z0-9.:-]*)=/', '$1x-on:$2=', $html);
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }

    return [$dom, new DOMXPath($dom)];
}

test('the registered block catalogue renders its defaults and every schema control in both brands', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $registry = app(BlockRegistryInterface::class);
    $sections = [];
    foreach ($registry->getActiveBlocks() as $type => $definition) {
        $sections[] = ['type' => $type] + $definition->getDefaultPayload();
    }
    $languages = Language::where('is_active', true)->orderByDesc('is_default')->get();
    $html = Blade::render('<x-admin.block-manager :sections="$sections" :languages="$languages" name="sections_json" />', compact('sections', 'languages'));
    [$dom, $xpath] = blockEditorDom($html);
    $root = $xpath->query('//*[@x-data][starts-with(@x-data, "blockManager(")]')->item(0);
    expect($root)->not->toBeNull();
    expect($xpath->query('//input[@type="hidden"][@name="sections_json"]')->item(0)->getAttribute(':value'))->toBe('getCleanSerializedSections()');

    // Schema controls are real Alpine templates. Inspect the rendered DOM branch
    // and binding, so a missing control or broken field input is observable.
    $invalidLabels = [];
    foreach ($xpath->query('//label//label | //label[count(.//input | .//select | .//textarea) > 1]') as $label) {
        $invalidLabels[] = $dom->saveHTML($label);
    }
    expect(count($invalidLabels))->toBe(0, implode("\n", $invalidLabels));
    expect($xpath->query('//label[@x-text="field.label || field.key"]')->length)->toBeGreaterThan(0, 'Each generic field needs its own explicit label.');
    foreach ($xpath->query('//label[@x-text="field.label || field.key"] | //label[@x-text="child.label || childKey"]') as $label) {
        $identity = $label->getAttribute(':for');
        expect($identity)->not->toBe('');
        $associated = [];
        foreach ($xpath->query('//input | //select | //textarea') as $control) {
            if ($control->getAttribute(':id') === $identity) {
                $associated[] = $control;
            }
        }
        expect($associated)->not->toBeEmpty('Every rendered schema label must target its conditional input.');
        if ($label->getAttribute('x-text') === 'child.label || childKey') {
            expect($identity)->toContain('itemIndex', 'childKey');
        }
    }
    $branches = [];
    foreach ($xpath->query('//template[@x-if]') as $template) {
        foreach ($xpath->query('.//input | .//select | .//textarea', $template) as $control) {
            if ($control->getAttribute('x-model') === 'block[field.key]' || $control->getAttribute('x-model.number') === 'block[field.key]' || str_contains($control->getAttribute(':value'), 'block[field.key]')) {
                $branches[$template->getAttribute('x-if')][] = $control;
            }
        }
    }
    $branchFor = [
        'text' => "['text', 'string', 'url', 'email'].includes(field.type || 'text')",
        'string' => "['text', 'string', 'url', 'email'].includes(field.type || 'text')",
        'url' => "['text', 'string', 'url', 'email'].includes(field.type || 'text')",
        'email' => "['text', 'string', 'url', 'email'].includes(field.type || 'text')",
        'textarea' => "field.type === 'textarea'",
        'integer' => "['number', 'integer'].includes(field.type)",
        'number' => "['number', 'integer'].includes(field.type)",
        'boolean' => "field.type === 'boolean'",
        'select' => "field.type === 'select'",
        'media' => "field.type === 'media'",
        'json' => "['json', 'array'].includes(field.type)",
        'array' => "['json', 'array'].includes(field.type)",
        'list' => "field.type === 'list'",
    ];
    foreach ($registry->getActiveBlocks() as $type => $definition) {
        expect($root->getAttribute('x-data'))->toContain($type);
        if ($definition->getEditorView() !== null) {
            // Special editors are included in production, not rendered by a
            // test replica. Their real editable content must reach the DOM.
            expect($xpath->query('//textarea[@x-model="block.content"]')->length)->toBeGreaterThan(0);

            continue;
        }
        foreach ($definition->getEditorSchema() as $key => $field) {
            $fieldType = (string) ($field['type'] ?? 'text');
            if ($fieldType === 'repeater') {
                expect($xpath->query('//template[@x-for="(item, itemIndex) in (block[field.key] || [])"]')->length)->toBeGreaterThan(0);

                continue;
            }
            expect(array_key_exists($fieldType, $branchFor))->toBeTrue("{$type}.{$key} must have a supported editor input.");
            $controls = $branches[$branchFor[$fieldType]] ?? [];
            expect($controls)->not->toBeEmpty("{$type}.{$key} must render its actual control branch.");
            foreach ($controls as $control) {
                if ($fieldType === 'boolean') {
                    expect($control->getAttribute('type'))->toBe('checkbox');
                } else {
                    expect(explode(' ', $control->getAttribute('class')))->toContain('admin-control');
                }
            }
        }
    }
    expect($xpath->query('//textarea[@x-model="subBlock.content"]')->length)->toBeGreaterThan(0, 'Nested rich text keeps its own editable binding.');
})->with(['dbcms', 'termocms']);

test('empty and unavailable editor payloads stay intact and validation remains visible in both brands', function (string $theme): void {
    app(AdminThemeServiceInterface::class)->setActiveTheme($theme);
    $languages = Language::where('is_active', true)->orderByDesc('is_default')->get();
    $empty = Blade::render('<x-admin.block-manager :sections="[]" :languages="$languages" />', compact('languages'));
    [, $xpath] = blockEditorDom($empty);
    $root = $xpath->query('//*[@x-data][starts-with(@x-data, "blockManager(")]')->item(0);
    expect($root->getAttribute('x-data'))->toContain(Js::from(['es' => [], 'en' => []])->toHtml());
    $sections = ['es' => [['type' => 'unknown_preserved_block', 'private_value' => 'UNAVAILABLE_PAYLOAD']], 'en' => []];
    $unavailable = Blade::render('<x-admin.block-manager :sections="$sections" :languages="$languages" />', compact('sections', 'languages'));
    [, $xpath] = blockEditorDom($unavailable);
    $root = $xpath->query('//*[@x-data][starts-with(@x-data, "blockManager(")]')->item(0);
    expect($root->getAttribute('x-data'))->toContain(Js::from($sections)->toHtml());
    $placeholder = $xpath->query('//template[@x-if="!isBlockAvailable(block)"]//*[@role="status"]')->item(0);
    expect($placeholder)->not->toBeNull();
    expect($placeholder->textContent)->toContain('Bloque no disponible', 'Su contenido permanece guardado');

    $errors = new ViewErrorBag;
    $errors->put('default', new MessageBag(['sections_json' => ['Contenido de bloques inválido.']]));
    $this->actingAs(User::where('email', 'admin@db-cms.io')->firstOrFail())
        ->withSession(['errors' => $errors])
        ->get(route('admin.pages.create'))->assertOk()->assertSee('Contenido de bloques inválido.')->assertSee('name="sections_json"', false);
})->with(['dbcms', 'termocms']);
