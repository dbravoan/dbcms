<?php

declare(strict_types=1);

namespace Modules\Blog\Contracts;

use App\DTOs\Front\PublicContentContext;
use Modules\Blog\DTOs\CommentOutcome;
use Modules\Blog\DTOs\CommentSubmission;

interface CommentPublicationServiceInterface
{
    public function submit(PublicContentContext $context, CommentSubmission $submission): CommentOutcome;
}
