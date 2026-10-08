<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class InquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id', 'name' => 'required|string|max:255',
            'email' => 'required|email|max:255', 'phone' => 'nullable|string|max:50',
            'message' => 'required|string|max:2000',
        ];
    }
}
