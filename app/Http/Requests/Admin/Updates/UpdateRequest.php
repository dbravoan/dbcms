<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Updates;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ! (bool) $user->getAttribute('is_service_account') && $user->hasPermission('access:admin') && $user->hasPermission('manage:updates');
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return match ($this->route()?->getName()) {
            'admin.updates.prepare', 'admin.updates.adopt' => ['version' => ['required', 'regex:/^\d+\.\d+\.\d+$/'], 'distribution' => ['sometimes', 'in:source,runtime']],
            'admin.updates.confirm-password' => ['password' => ['required', 'string', 'current_password:web']],
            'admin.updates.approve' => ['plan_hash' => ['required', 'regex:/^[a-f0-9]{64}$/'], 'hosting.single_host' => ['required', 'accepted'], 'hosting.no_unmanaged_workers' => ['required', 'accepted'], 'backup.provider' => ['nullable', 'string', 'max:200'], 'backup.reference' => ['nullable', 'string', 'max:500'], 'backup.created_at' => ['nullable', 'date', 'before_or_equal:now'], 'backup.restored_at' => ['nullable', 'date', 'before_or_equal:now'], 'verification_report' => ['nullable', 'file', 'max:1024', 'mimetypes:text/plain,application/pdf']],
            default => [],
        };
    }
}
