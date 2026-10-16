<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

final class BlockPackageToggleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('manage:blocks');
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        return ['enabled' => 'required|boolean'];
    }
}
