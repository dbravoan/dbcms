<?php

declare(strict_types=1);

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use Illuminate\Validation\ValidationException;

test('block url fields reject executable protocols through shared validation', function (string $type, array $payload): void {
    app(BlockRegistryInterface::class)->validate($type, $payload);
})->with([
    ['cta', ['cta_url' => 'javascript:alert(1)']],
    ['hero', ['secondary_cta_url' => "java\nscript:alert(1)"]],
    ['pricing', ['items' => [['cta_url' => 'data:text/html,<script>alert(1)</script>']]]],
])->throws(ValidationException::class);

test('stored unsafe block urls do not reach public links', function (string $type, array $payload): void {
    $html = app(BlockRegistryInterface::class)->render($type, $payload);
    expect($html)->not->toContain('javascript:', 'data:text/html');
})->with([
    ['cta', ['cta_text' => 'Action', 'cta_url' => 'javascript:alert(1)']],
    ['hero', ['cta_text' => 'Action', 'cta_url' => 'javascript:alert(1)']],
    ['pricing', ['items' => [['cta_text' => 'Action', 'cta_url' => 'data:text/html,active']]]],
]);

test('ordinary local and external action urls remain usable', function (string $url): void {
    $registry = app(BlockRegistryInterface::class);
    $registry->validate('cta', ['cta_url' => $url]);
    expect($registry->render('cta', ['cta_text' => 'Action', 'cta_url' => $url]))->toContain('href=');
})->with(['/contact', '#details', 'https://example.test/path', 'mailto:hello@example.test', 'tel:+34123456789']);
