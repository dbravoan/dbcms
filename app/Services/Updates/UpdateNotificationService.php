<?php

declare(strict_types=1);

namespace App\Services\Updates;

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Models\SystemNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UpdateNotificationService
{
    public function __construct(private readonly ModuleNotificationServiceInterface $notifications) {}

    /** @param array<string, mixed> $release */
    public function notify(array $release): void
    {
        $version = (string) $release['version'];
        foreach (User::query()->where('is_service_account', false)->cursor() as $user) {
            if (! $user->hasPermission('access:admin') || ! $user->hasPermission('manage:updates')) {
                continue;
            }
            DB::transaction(function () use ($user, $version): void {
                // Lock the recipient, so two discovery workers cannot notify them twice.
                User::whereKey($user->id)->lockForUpdate()->first();
                if (SystemNotification::where('module', 'core_updates')->where('user_id', $user->id)->where('data->version', $version)->exists()) {
                    return;
                }
                $this->notifications->notify('core_updates', 'alert', 'Actualización de dbCMS disponible', 'La versión '.$version.' está disponible. Revisa sus requisitos y prepara una copia antes de actualizar.', route('admin.updates.index'), ['version' => $version, 'required_permission' => 'manage:updates'], true, $user->id);
            });
        }
    }
}
