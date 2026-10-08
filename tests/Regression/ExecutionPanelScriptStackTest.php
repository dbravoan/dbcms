<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Blade;

uses(TestCase::class);

it('renders the approval component initializer through each backoffice layout script stack', function (string $layout): void {
    config(['session.driver' => 'array']);
    session()->put('_token', 'csrf-fixture-token');
    $source = file_get_contents(resource_path('views/'.$layout));
    preg_match_all("/@stack\\('[^']+'\\)/", $source, $stacks);
    preg_match_all('/<meta\\b[^>]*>/', $source, $meta);
    $html = Blade::render(implode("\n", $meta[0])."\n@include('admin.orchestration.panel', ['initialExecutionId' => \$executionId])\n".implode("\n", $stacks[0]), [
        'executionId' => '00000000-0000-4000-8000-000000000001',
        'cspNonce' => 'fixture-nonce',
    ]);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<html><body>'.$html.'</body></html>');
        $xpath = new DOMXPath($document);
        expect($xpath->query('//meta[@name="csrf-token"]')->length)->toBe(1);
        expect($xpath->query('//meta[@name="csrf-token"]/@content')->item(0)?->nodeValue === session()->token())->toBeTrue();
        expect($xpath->query('//section[@x-data="cmsExecution()"]')->length)->toBe(1);
        expect($xpath->query('//script[contains(text(), "function cmsExecution()")]')->length)->toBe(1);
        expect($xpath->query('//script[contains(text(), "function cmsExecution()")]/@nonce')->item(0)?->nodeValue)->toBe('fixture-nonce');
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
})->with([
    'admin/layout.blade.php',
    'themes/admin/dbcms/admin/layout.blade.php',
    'themes/admin/termocms/admin/layout.blade.php',
]);
