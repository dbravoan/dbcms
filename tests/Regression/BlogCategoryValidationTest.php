<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Validator;
use Illuminate\Translation\ArrayLoader;
use Illuminate\Translation\Translator;
use Illuminate\Validation\Factory;
use Illuminate\Validation\ValidationException;
use Modules\Blog\Models\Category;
use Modules\Blog\Services\CategoryMutationHandler;

it('rejects malformed blog category values before any persistence', function (array $attributes): void {
    Validator::swap(new Factory(new Translator(new ArrayLoader, 'en')));
    expect(fn () => (new CategoryMutationHandler)->prepare(new Category, ['attributes' => $attributes], true))->toThrow(ValidationException::class);
})->with([
    'negative parent' => [['parent_id' => -1]],
    'fractional parent' => [['parent_id' => 1.5]],
    'invalid active flag' => [['is_active' => 'maybe']],
]);
