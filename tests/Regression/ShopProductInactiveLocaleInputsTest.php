<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Modules\Shop\Services\ShopMutationRules;

uses(TestCase::class);

it('renders inactive product locale inputs in the validated nested translation contract', function (): void {
    $source = file_get_contents(resource_path('views/admin/shop/products/_form.blade.php'));
    preg_match('/<template x-for="code in allLanguages".*?<\/template>/s', $source, $fragment);
    $html = Blade::render($fragment[0]);
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    try {
        $document->loadHTML('<html><body>'.$html.'</body></html>');
        $data = [];
        foreach ((new DOMXPath($document))->query('//input') as $input) {
            $expression = $input->getAttribute(':name');
            expect($expression)->toMatch('/^\x27translations\[\x27 \+ code \+ \x27\]\[[a-z_]+\]\x27$/');
            $name = str_replace(["'translations[' + code + '", "'"], ['translations[en', ''], $expression);
            $data[] = rawurlencode($name).'='.rawurlencode('QA English value');
        }
        parse_str(implode('&', $data), $payload);
        $validated = Validator::make($payload, ShopMutationRules::forEntity(true))->validate();
        expect($validated['translations']['en'])->toHaveKeys(['name', 'slug', 'summary', 'description']);
    } finally {
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
    }
});
