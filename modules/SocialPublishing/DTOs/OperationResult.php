<?php

declare(strict_types=1);

namespace Modules\SocialPublishing\DTOs;

final readonly class OperationResult
{
    /** @param array<string,mixed> $receipt */
    public function __construct(public string $state, public ?string $nextStep = null, public ?string $remoteId = null, public array $receipt = [], public ?string $error = null, public int $retryAfter = 60) {}
}
