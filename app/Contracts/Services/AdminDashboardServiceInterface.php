<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\Models\User;

interface AdminDashboardServiceInterface
{
    /** @return array<string, mixed> */
    public function data(User $user): array;
}
