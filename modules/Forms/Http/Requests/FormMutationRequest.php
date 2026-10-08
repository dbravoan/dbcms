<?php

declare(strict_types=1);

namespace Modules\Forms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Forms\Http\Controllers\Admin\Concerns\ValidatesFormPayload;
use Modules\Forms\Models\CustomForm;

final class FormMutationRequest extends FormRequest
{
    use ValidatesFormPayload;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $form = $this->route('form');

        return $this->formValidationRules($form instanceof CustomForm ? $form : null);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', $this->isMethod('POST'))]);
    }
}
