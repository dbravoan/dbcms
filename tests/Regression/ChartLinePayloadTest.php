<?php

declare(strict_types=1);

use Modules\Charts\Blocks\ChartsBlock;
use Modules\Charts\Services\ChartRendererService;

it('renders the editor label payload as a line chart', function (): void {
    $block = new ChartsBlock(new ChartRendererService);
    $payload = $block->getDefaultPayload();
    $payload['chart_type'] = 'line';

    expect($block->render($payload))->toContain('Velocidad de Carga', 'Conversión de Leads');
});

it('escapes labels while preserving legacy point payloads', function (): void {
    $html = (new ChartRendererService)->renderLine([
        ['point' => '<script>legacy()</script>', 'value' => 1],
        ['label' => '<img src=x onerror=alert(1)>', 'value' => 2],
    ]);

    expect($html)->toContain('&lt;script&gt;legacy()&lt;/script&gt;', '&lt;img src=x onerror=alert(1)&gt;')
        ->not->toContain('<script>', '<img src=x');
});
