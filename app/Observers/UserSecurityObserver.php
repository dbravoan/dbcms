<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Services\Auth\TokenRevocationService;

final class UserSecurityObserver
{
    public function __construct(private readonly TokenRevocationService $tokens) {}

    public function deleting(User $user): void
    {
        $this->tokens->forUser($user);
    }
}
