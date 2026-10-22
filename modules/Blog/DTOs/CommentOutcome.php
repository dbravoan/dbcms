<?php

declare(strict_types=1);

namespace Modules\Blog\DTOs;

final readonly class CommentOutcome
{
    /** @param array<string,string> $flash
     * @param  array<string,string>  $errors
     */
    public function __construct(public ?string $returnUrl, public array $flash = [], public array $errors = []) {}
}
