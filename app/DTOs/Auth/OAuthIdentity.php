<?php

declare(strict_types=1);

namespace App\DTOs\Auth;

use App\Models\User;

final readonly class OAuthIdentity
{
    public function __construct(public User $user, public bool $isService) {}
}
