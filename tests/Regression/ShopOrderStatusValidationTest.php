<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Validator;
use Modules\Shop\Capabilities\ShopResources;

uses(TestCase::class);

it('rejects unsupported and null order states through the native resource rules', function (array $attributes): void {
    expect(Validator::make($attributes, ShopResources::descriptor()->rules)->fails())->toBeTrue();
})->with([
    [['status' => 'shipped']],
    [['payment_status' => 'simulated_paid']],
    [['status' => null]],
    [['payment_status' => null]],
]);

it('accepts the same valid order states and partial patches as the backoffice', function (array $attributes): void {
    expect(Validator::make($attributes, ShopResources::descriptor()->rules)->passes())->toBeTrue();
})->with([
    [['status' => 'pending', 'payment_status' => 'unpaid']],
    [['status' => 'processing']],
    [['status' => 'completed', 'payment_status' => 'paid']],
    [['status' => 'cancelled', 'payment_status' => 'refunded']],
    [['notes' => 'QA-20261011 order reviewed']],
]);
