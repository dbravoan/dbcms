<?php

declare(strict_types=1);

use Modules\AiAssistant\Enums\AiEndpointFamily;

test('open code model ids map to the correct transport family', function (string $model, AiEndpointFamily $family): void {
    expect(AiEndpointFamily::fromModelId($model))->toBe($family);
})->with([
    ['deepseek-v4.1-flash', AiEndpointFamily::ChatCompletions],
    ['glm-5.3', AiEndpointFamily::ChatCompletions],
    ['kimi-k3', AiEndpointFamily::ChatCompletions],
    ['minimax-m3', AiEndpointFamily::ChatCompletions],
    ['mimo-v2.5-free', AiEndpointFamily::ChatCompletions],
    ['claude-opus-5', AiEndpointFamily::Messages],
    ['qwen3.8-flash', AiEndpointFamily::Messages],
    ['gemini-3.8-flash', AiEndpointFamily::Google],
    ['gpt-5.6-sol', AiEndpointFamily::Responses],
    ['grok-4.6', AiEndpointFamily::Responses],
    ['muse-spark-1.3', AiEndpointFamily::Responses],
    ['jev-1.13', AiEndpointFamily::SystemOne],
]);

test('non conversational families are flagged', function (): void {
    expect(AiEndpointFamily::SystemOne->isConversational())->toBeFalse()
        ->and(AiEndpointFamily::ChatCompletions->isConversational())->toBeTrue()
        ->and(AiEndpointFamily::Google->isConversational())->toBeTrue();
});
