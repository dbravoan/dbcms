<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Adapters;

use Illuminate\Http\RedirectResponse;
use Modules\Blog\DTOs\CommentOutcome;

final class CommentResponseMapper
{
    public function respond(CommentOutcome $outcome): RedirectResponse
    {
        abort_if($outcome->returnUrl === null, 404);

        $response = redirect()->to($outcome->returnUrl)->with($outcome->flash);

        return $outcome->errors === [] ? $response : $response->withErrors($outcome->errors);
    }
}
