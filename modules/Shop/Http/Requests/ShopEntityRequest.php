<?php

declare(strict_types=1);

namespace Modules\Shop\Http\Requests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Modules\Shop\Services\ShopMutationRules;

final class ShopEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $entity = $this->route('product') ?? $this->route('category');

        return ShopMutationRules::forEntity($this->routeIs('admin.shop.products.*'), $entity instanceof Model ? $entity : null);
    }
}
