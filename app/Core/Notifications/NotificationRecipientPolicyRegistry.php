<?php

declare(strict_types=1);

namespace App\Core\Notifications;

use App\Models\SystemNotification;
use App\Models\User;

final class NotificationRecipientPolicyRegistry
{
    /** @var array<string, callable(SystemNotification, User): bool> */
    private array $policies = [];

    /** @param callable(SystemNotification, User): bool $policy */
    public function register(string $module, callable $policy): void
    {
        $this->policies[$module] = $policy;
    }

    public function allows(SystemNotification $notification, User $user): bool
    {
        $policy = $this->policies[$notification->module] ?? null;

        return $policy === null || $policy($notification, $user);
    }
}
