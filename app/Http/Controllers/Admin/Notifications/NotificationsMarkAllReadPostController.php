<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Notifications;

use App\Contracts\Services\ModuleNotificationServiceInterface;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

final class NotificationsMarkAllReadPostController extends Controller
{
    public function __construct(
        private readonly ModuleNotificationServiceInterface $notificationService
    ) {}

    public function __invoke(): JsonResponse
    {
        $count = $this->notificationService->markAllAsRead();

        return response()->json([
            'success' => true,
            'marked_count' => $count,
            'unread_count' => 0,
        ]);
    }
}
