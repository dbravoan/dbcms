<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Notifications;

use App\Contracts\Services\NotificationRecipientResolverInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\NotificationEmailPreferenceRequest;
use Illuminate\Http\JsonResponse;

final class NotificationEmailPreferencePostController extends Controller
{
    public function __construct(private readonly NotificationRecipientResolverInterface $recipients) {}

    public function __invoke(NotificationEmailPreferenceRequest $request): JsonResponse
    {
        $this->recipients->setPreference($request->actor(), $request->string('module', '*')->toString(), $request->boolean('enabled'));

        return response()->json(['email_enabled' => $this->recipients->emailEnabled($request->actor())]);
    }
}
