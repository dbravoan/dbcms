<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Enums;

/**
 * Transport shape required by a given model behind a gateway.
 *
 * OpenCode Zen/Go expose different models through different API families,
 * so the concrete HTTP contract is derived from the model identifier.
 */
enum AiEndpointFamily: string
{
    case ChatCompletions = 'chat_completions';
    case Messages = 'messages';
    case Responses = 'responses';
    case Google = 'google';
    case SystemOne = 'systemone';

    public static function fromModelId(string $modelId): self
    {
        $id = strtolower(trim($modelId));

        return match (true) {
            str_starts_with($id, 'claude-'),
            str_starts_with($id, 'qwen') => self::Messages,
            str_starts_with($id, 'gemini-') => self::Google,
            str_starts_with($id, 'gpt-'),
            str_starts_with($id, 'grok-'),
            str_starts_with($id, 'muse-spark') => self::Responses,
            str_starts_with($id, 'jev-') => self::SystemOne,
            default => self::ChatCompletions,
        };
    }

    public function isConversational(): bool
    {
        return $this !== self::SystemOne;
    }
}
