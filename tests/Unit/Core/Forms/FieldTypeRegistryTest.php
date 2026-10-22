<?php

declare(strict_types=1);

use App\Core\Forms\Contracts\FieldType;
use App\Core\Forms\CoreFieldType;
use App\Core\Forms\FieldSchema;
use App\Core\Forms\FieldTypeRegistry;

/**
 * The registry is a singleton shared by the whole application, so these tests
 * must not overwrite a real type (`captcha`) with a fixture: it used to leak
 * into the rest of the suite and hid the real captcha field type from the
 * backoffice form builder. Fixtures use their own keys and are removed again.
 */
test('the core registers the primitive html field types', function (): void {
    $registry = app(FieldTypeRegistry::class);

    foreach (['text', 'email', 'tel', 'number', 'textarea', 'select', 'checkbox', 'radio'] as $key) {
        expect($registry->has($key))->toBeTrue("Missing core field type [{$key}].");
    }
});

test('available() filters out types whose module is disabled', function (): void {
    $registry = app(FieldTypeRegistry::class);

    $registry->register(new class implements FieldType
    {
        public function key(): string
        {
            return 'fixture_unknown_type';
        }

        public function available(): bool
        {
            return false;
        }

        public function stores(): bool
        {
            return false;
        }

        public function rules(FieldSchema $field): array
        {
            return [];
        }

        public function view(): string
        {
            return 'components.cms-form.field';
        }
    });

    try {
        expect($registry->has('fixture_unknown_type'))->toBeTrue()
            ->and(array_key_exists('fixture_unknown_type', $registry->available()))->toBeFalse();
    } finally {
        $registry->forget('fixture_unknown_type');
    }
});

test('resolve() ignores unavailable and unknown field types', function (): void {
    $registry = app(FieldTypeRegistry::class);

    // Known + available.
    expect($registry->resolve('email'))->toBeInstanceOf(CoreFieldType::class);

    // Declared in config/forms.php but not registered (its module is off) → ignored.
    expect($registry->resolve('captcha'))->toBeNull();

    expect($registry->resolve('mystery'))->toBeNull();
});

test('resolve() ignores a registered type that reports itself unavailable', function (): void {
    $registry = app(FieldTypeRegistry::class);

    // `captcha` is the declared module-provided type; registering an unavailable
    // instance of it is exactly what a disabled module produces. The real type
    // is restored afterwards so nothing leaks into the rest of the suite.
    $registry->register(new class implements FieldType
    {
        public function key(): string
        {
            return 'captcha';
        }

        public function available(): bool
        {
            return false;
        }

        public function stores(): bool
        {
            return false;
        }

        public function rules(FieldSchema $field): array
        {
            return [];
        }

        public function view(): string
        {
            return 'components.cms-form.field';
        }
    });

    try {
        expect($registry->resolve('captcha'))->toBeNull();
    } finally {
        $registry->forget('captcha');
    }

    // Back to the catalog rule: declared but unregistered → ignored.
    expect($registry->resolve('captcha'))->toBeNull();
});
