<?php

declare(strict_types=1);

namespace Modules\Blog\DTOs;

final readonly class CommentSubmission
{
    public function __construct(public string $authorName, public string $authorEmail, public string $content, public ?int $parentId = null, public bool $honeypotFilled = false) {}
}
