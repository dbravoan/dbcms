<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Shop\Services\ShopDomainService;

final class CheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,string> */
    public function rules(): array
    {
        if (! app(ShopDomainService::class)->isShopEnabled()) {
            return [];
        }

        return [
            'customer_name' => 'required|string|max:255', 'customer_email' => 'required|email|max:255',
            'customer_phone' => 'nullable|string|max:50', 'address' => 'required|string|max:255',
            'city' => 'required|string|max:100', 'postal_code' => 'required|string|max:20',
            'country' => 'required|string|max:100', 'payment_method' => 'required|in:bank_transfer,cod',
            'notes' => 'nullable|string',
        ];
    }
}
