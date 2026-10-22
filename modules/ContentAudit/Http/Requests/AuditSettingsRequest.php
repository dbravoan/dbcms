<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class AuditSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && ($user->isAdmin() || $user->hasPermission('manage:modules'));
    }

    protected function prepareForValidation(): void
    {
        foreach (['audited_roles', 'approver_roles', 'audited_entities'] as $key) {
            $value = $this->input($key);
            if (is_string($value)) {
                $this->merge([$key => array_values(array_filter(array_map('trim', explode(',', $value))))]);
            }
        }
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $rules = [];
        foreach (['is_enabled', 'auto_publish_on_approval', 'track_all_modifications', 'require_rejection_reason', 'enforce_editorial_quality', 'notify_in_app', 'notify_email'] as $key) {
            $rules[$key] = 'sometimes|boolean';
        }
        $rules['min_anti_ai_score'] = 'sometimes|integer|min:0|max:100';
        $rules['audited_roles'] = 'sometimes|array';
        $rules['audited_roles.*'] = 'string|exists:roles,slug';
        $rules['approver_roles'] = 'sometimes|array';
        $rules['approver_roles.*'] = 'string|exists:roles,slug';
        $rules['audited_entities'] = 'sometimes|array';
        $rules['audited_entities.*'] = 'string|max:100';

        return $rules;
    }
}
