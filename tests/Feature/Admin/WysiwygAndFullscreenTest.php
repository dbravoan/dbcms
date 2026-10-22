<?php

declare(strict_types=1);

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Models\User;
use App\Services\Media\MarkdownRendererService;
use BlockPackages\Basic\Blocks\RichTextBlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CmsFixtures;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    CmsFixtures::localization();
    CmsFixtures::identities();
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
    $this->registry = app(BlockRegistryInterface::class);
    $this->markdown = app(MarkdownRendererService::class);
});

test('rich text block default payload includes format markdown', function (): void {
    /** @var RichTextBlock $block */
    $block = $this->registry->get('text');

    expect($block)->toBeInstanceOf(RichTextBlock::class);
    $payload = $block->getDefaultPayload();

    expect($payload)->toHaveKey('format');
    expect($payload['format'])->toBe('markdown');
    expect($payload)->toHaveKey('content');
});

test('rich text block renders markdown into html with prose styling', function (): void {
    /** @var RichTextBlock $block */
    $block = $this->registry->get('text');

    $html = $block->render([
        'title' => 'Sección de Prueba',
        'format' => 'markdown',
        'content' => "## Subtítulo Técnico\n\nTexto en **negrita** y una lista:\n- Item Alpha\n- Item Beta",
    ]);

    expect($html)->toContain('Sección de Prueba');
    expect($html)->toContain('Subtítulo Técnico');
    expect($html)->toContain('<strong>negrita</strong>');
    expect($html)->toContain('<li>Item Alpha</li>');
    expect($html)->toContain('prose prose-invert');
});

test('rich text block renders raw html directly when format is html', function (): void {
    /** @var RichTextBlock $block */
    $block = $this->registry->get('text');

    $rawHtmlContent = '<div class="custom-cyber-box"><table class="w-full"><tr><td>Dato Celda</td></tr></table><p>Párrafo directo</p></div>';

    $html = $block->render([
        'title' => 'Bloque HTML',
        'format' => 'html',
        'content' => $rawHtmlContent,
    ]);

    expect($html)->toContain('Bloque HTML');
    expect($html)->toContain('<div class="custom-cyber-box">');
    expect($html)->toContain('<td>Dato Celda</td>');
    expect($html)->toContain('<p>Párrafo directo</p>');
});

test('rich text block preserves embedded html tags inside markdown format', function (): void {
    /** @var RichTextBlock $block */
    $block = $this->registry->get('text');

    $mixedContent = "### Encabezado MD\n\n<div class=\"badge-box\"><span class=\"text-neon\">Aviso</span></div>\n\n- Punto 1\n- Punto 2";

    $html = $block->render([
        'format' => 'markdown',
        'content' => $mixedContent,
    ]);

    expect($html)->toContain('Encabezado MD');
    expect($html)->toContain('<div class="badge-box">');
    expect($html)->toContain('<span class="text-neon">Aviso</span>');
    expect($html)->toContain('<li>Punto 1</li>');
});

test('markdown renderer removes unknown widgets while preserving surrounding html', function (): void {
    $htmlWithWidget = '<div class="banner">Información previa [widget:unknown_test_widget] fin</div>';

    $output = $this->markdown->renderHtml($htmlWithWidget);

    expect($output)->toContain('<div class="banner">');
    expect($output)->toContain('Información previa  fin')->not->toContain('unknown_test_widget');
});

test('admin pages create view renders block manager with fullscreen mode and dual wysiwyg', function (): void {
    $response = $this->actingAs($this->admin)->get(route('admin.pages.create'));

    $response->assertOk();
    $response->assertSee('Pantalla completa', false);
    $response->assertSee('toggleFullscreen()', false);
    $response->assertSee('FULLSCREEN CANVAS', false);
    $response->assertSee('Sintaxis:', false);
    $response->assertSee('Markdown', false);
    $response->assertSee('HTML Puro', false);
    $response->assertSee('Dividida', false);
    $response->assertSee('Vista Previa', false);
    $response->assertSee('renderContentPreview', false);
});

test('global modals have elevated z-index for fullscreen compatibility', function (): void {
    $response = $this->actingAs($this->admin)->get(route('admin.pages.create'));

    $response->assertOk();
    // Verify filemanager modal and delete modal are elevated to z-[200]
    $response->assertSee('z-[200]', false);
});

test('rich text editor keeps each mode inside one complete editor for sections and nested blocks', function (bool $nested): void {
    $html = '<main id="fixture">'.view('basic::editor.rich-text', [
        'blockKey' => $nested ? 'sub' : 'block',
        'isSubBlock' => $nested,
    ])->render().'<aside id="next-field"></aside></main>';
    $dom = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        // libxml's HTML4 parser cannot read Alpine's @ shorthand. Its equivalent
        // long form preserves attributes and lets us inspect the actual nesting.
        $html = preg_replace('/(\s)@([a-z][a-z0-9.:-]*)=/', '$1x-on:$2=', $html);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
    $xpath = new DOMXPath($dom);
    $editors = $xpath->query('//*[@id="fixture"]/div[@x-data]');
    expect($editors->length)->toBe(1);
    $editor = $editors->item(0);
    // A truncated editor must not absorb the next field in the surrounding form.
    expect($xpath->query('//*[@id="fixture"]/aside[@id="next-field"]')->length)->toBe(1);
    foreach (['edit', 'split', 'preview'] as $mode) {
        $panels = $xpath->query('.//*[@x-show="viewMode === \''.$mode.'\'"]', $editor);
        expect($panels->length)->toBe(1, 'Every selectable mode needs exactly one panel.');
        $contents = $xpath->query($mode === 'edit' ? './/textarea' : './/*[@x-html]', $panels->item(0));
        expect($contents->length)->toBe(1, 'The selected mode must expose its content.');
    }
})->with([false, true]);
