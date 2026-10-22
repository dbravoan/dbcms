<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use App\Models\User;
use Modules\Forms\Models\FormSubmission;

final class FormsDashboard
{
    /** @return array{views: array<string, string>, data: array<string, mixed>}|null */
    public function data(User $user): ?array
    {
        if (! $user->isAdmin() && ! $user->hasPermission('manage:forms')) {
            return null;
        }

        return [
            'views' => ['metrics' => 'forms::admin.dashboard.metrics', 'actions' => 'forms::admin.dashboard.actions', 'details' => 'forms::admin.dashboard.details'],
            'data' => ['stats' => ['total_submissions' => FormSubmission::count()], 'recentSubmissions' => FormSubmission::with('form')->latest()->take(5)->get()],
        ];
    }
}
