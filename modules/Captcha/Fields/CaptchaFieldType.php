<?php

declare(strict_types=1);

namespace Modules\Captcha\Fields;

use App\Core\Forms\Contracts\FieldType;
use App\Core\Forms\FieldSchema;
use App\Core\Forms\FieldTypeRegistry;
use Modules\Captcha\Services\CaptchaService;

/**
 * The `captcha` form field type.
 *
 * Registered by the Captcha module only while the module is active, so a
 * disabled module removes the type from the form builder AND makes any stored
 * captcha field be ignored entirely (see {@see FieldTypeRegistry::resolve()}).
 *
 * It validates `captcha_response` (verified against the signed token) and its
 * inputs are never part of the stored submission payload.
 */
final class CaptchaFieldType implements FieldType
{
    public function key(): string
    {
        return 'captcha';
    }

    public function available(): bool
    {
        return captcha_available();
    }

    public function stores(): bool
    {
        return false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(FieldSchema $field): array
    {
        $service = app(CaptchaService::class);

        $rules = [];

        // The field that must be present depends on the active driver (an
        // external-only widget never posts `captcha_response`).
        foreach ($service->requiredInputs() as $input) {
            $rules[$input] = ['required', 'captcha'];
        }

        return $rules;
    }

    public function view(): string
    {
        return 'captcha::captcha_input';
    }
}
