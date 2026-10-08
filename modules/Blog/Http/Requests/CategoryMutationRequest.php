<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Blog\Services\CategoryMutationRules;

final class CategoryMutationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return CategoryMutationRules::all();
    }
}
