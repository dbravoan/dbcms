<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class TranslationSnapshot
{
    /**
     * @param  array<string,mixed>  $original
     * @param  list<array{id:string,text:string}>  $segments
     * @param  list<array{path:list<int|string>,parts:list<array{literal?:string,id?:string}>}>  $templates
     * @param  list<string>  $protectedBlockTypes
     */
    public function __construct(public array $original, public array $segments, public array $templates, public array $protectedBlockTypes) {}

    public function fingerprint(): string
    {
        return hash('sha256', json_encode($this->original, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
}
