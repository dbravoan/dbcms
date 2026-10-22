<?php

declare(strict_types=1);

namespace Modules\AiAssistant\DTOs;

final readonly class GenerationContext
{
    /** @param list<int> $marketIds */
    public function __construct(public string $locale, public string $market, public array $marketIds = [], public ?string $question = null) {}

    /** @return array{locale:string,market:string,market_ids:list<int>} */
    public function toArray(): array
    {
        return ['locale' => $this->locale, 'market' => $this->market, 'market_ids' => $this->marketIds];
    }
}
