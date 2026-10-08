<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Modules\Shop\Models\Product;
use Modules\Shop\Services\ProductMutationHandler;

it('rejects invalid product amounts before any persistence', function (array $attributes): void {
    Validator::swap(new Factory(
        new Translator(new ArrayLoader, 'en')
    ));
    expect(fn () => (new ProductMutationHandler)->prepare(new Product, ['attributes' => $attributes], true))
        ->toThrow(ValidationException::class);
})->with([
    'negative price' => [['price' => -1]],
    'negative stock' => [['stock_quantity' => -1]],
    'fractional stock' => [['stock_quantity' => 1.5]],
    'negative custom price' => [['market_prices' => [1 => -1]]],
]);
