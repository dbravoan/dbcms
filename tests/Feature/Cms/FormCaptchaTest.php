<?php

declare(strict_types=1);

use App\Models\CmsModule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ViewErrorBag;
use Modules\Captcha\CaptchaModuleServiceProvider;
use Modules\Captcha\Services\CaptchaService;
use Modules\Forms\Blocks\CustomFormBlock;
use Modules\Forms\Http\Controllers\Admin\Concerns\ValidatesFormPayload;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Models\CustomFormTranslation;
use Modules\Forms\Models\FormField;
use Modules\Forms\Models\FormFieldTranslation;
use Modules\Forms\Models\FormSubmission;
use Modules\Forms\Services\FormSchemaService;
use Modules\Forms\Support\FieldTypeOptions;

uses(RefreshDatabase::class);

function setCaptchaModule(bool $active): void
{
    CmsModule::updateOrCreate(
        ['identifier' => 'captcha'],
        [
            'name' => 'Security & Dynamic Captcha',
            'version' => '1.0.0',
            'description' => 'test',
            'provider' => CaptchaModuleServiceProvider::class,
            'is_pageable' => false,
            'is_installed' => true,
            'is_active' => $active,
            'settings' => null,
        ],
    );
}

function makeContactForm(bool $withCaptcha = true): CustomForm
{
    $form = CustomForm::create(['slug' => 'contacto', 'is_active' => true]);

    CustomFormTranslation::create([
        'custom_form_id' => $form->id,
        'locale' => 'es',
        'name' => 'Formulario de Contacto',
        'submit_button_text' => 'Enviar',
        'success_message' => '¡Gracias!',
    ]);

    FormField::create([
        'custom_form_id' => $form->id,
        'field_key' => 'nombre',
        'type' => 'text',
        'is_required' => true,
        'order' => 1,
    ]);

    if ($withCaptcha) {
        $captcha = FormField::create([
            'custom_form_id' => $form->id,
            'field_key' => 'captcha',
            'type' => 'captcha',
            'is_required' => true,
            'order' => 2,
        ]);

        FormFieldTranslation::create([
            'form_field_id' => $captcha->id,
            'locale' => 'es',
            'label' => 'Verificación de seguridad',
        ]);
    }

    return $form;
}

beforeEach(function (): void {
    view()->share('errors', new ViewErrorBag);
});

test('the builder offers the captcha type only while the module is active', function (): void {
    setCaptchaModule(false);
    $withoutModule = collect(app(FieldTypeOptions::class)->list())->pluck('key')->all();

    setCaptchaModule(true);
    $withModule = collect(app(FieldTypeOptions::class)->list())->pluck('key')->all();

    expect($withoutModule)->not->toContain('captcha')
        ->and($withModule)->toContain('captcha');
});

test('saving a captcha field normalizes it (fixed key, always required, no rules)', function (): void {
    $form = makeContactForm(false);

    app(FormSchemaService::class)->syncFields($form, [
        [
            'field_key' => 'lo-que-sea',
            'type' => 'captcha',
            'is_required' => false,
            'validation_rules' => 'nullable|string',
            'translations' => ['es' => ['label' => 'Verificación', 'placeholder' => '', 'options' => '']],
        ],
    ]);

    $field = $form->fresh()->fields->firstOrFail();

    expect($field->field_key)->toBe('captcha')
        ->and($field->type)->toBe('captcha')
        ->and($field->is_required)->toBeTrue()
        ->and($field->validation_rules)->toBeNull();
});

test('a form cannot carry more than one captcha field', function (): void {
    $rules = (new class
    {
        use ValidatesFormPayload {
            formValidationRules as public;
        }
    })->formValidationRules();

    $payload = [
        'translations' => ['es' => ['name' => 'Contacto']],
        'fields' => [
            ['field_key' => 'a', 'type' => 'captcha'],
            ['field_key' => 'b', 'type' => 'captcha'],
        ],
    ];

    expect(Validator::make($payload, $rules)->errors()->has('fields'))->toBeTrue();

    $payload['fields'] = [['field_key' => 'a', 'type' => 'captcha'], ['field_key' => 'b', 'type' => 'text']];

    expect(Validator::make($payload, $rules)->errors()->has('fields'))->toBeFalse();
});

test('the public form renders the captcha only when the form declares the field', function (): void {
    setCaptchaModule(true);
    $form = makeContactForm(true);

    $html = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');

    expect($html)->toContain('name="captcha_response"')
        ->and($html)->toContain('_captcha_token')
        ->and($html)->toContain('Verificación de seguridad');

    // Same slug, no captcha field declared.
    $form->fields()->delete();
    $form->delete();
    makeContactForm(false);

    $withoutField = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');

    expect($withoutField)->not->toContain('name="captcha_response"');
});

test('submitting without a captcha is rejected when the form declares the field', function (): void {
    setCaptchaModule(true);
    makeContactForm(true);

    $response = $this->post('/forms/contacto/submit', ['nombre' => 'Ana']);

    $response->assertSessionHasErrors(['captcha_response']);

    expect(FormSubmission::count())->toBe(0);
});

test('submitting with a valid captcha is accepted and never persists the challenge', function (): void {
    setCaptchaModule(true);
    makeContactForm(true);

    $challenge = app(CaptchaService::class)->generateChallenge();
    preg_match('/(\d+)\s*\+\s*(\d+)/', $challenge['svg'], $matches);
    $answer = (string) ((int) $matches[1] + (int) $matches[2]);

    $response = $this->post('/forms/contacto/submit', [
        'nombre' => 'Ana',
        '_captcha_token' => $challenge['token'],
        'captcha_response' => $answer,
    ]);

    $response->assertSessionHasNoErrors();

    $submission = FormSubmission::firstOrFail();

    expect($submission->payload)->toHaveKey('nombre')
        ->and($submission->payload)->not->toHaveKey('captcha_response')
        ->and($submission->payload)->not->toHaveKey('_captcha_token');
});

test('a captcha field is ignored while the module is disabled', function (): void {
    setCaptchaModule(false);
    makeContactForm(true);

    // Not rendered at all.
    $html = (new CustomFormBlock)->render(['form_slug' => 'contacto'], null, 'es');
    expect($html)->not->toContain('name="captcha_response"');

    // Not required either: the submission goes through.
    $response = $this->post('/forms/contacto/submit', ['nombre' => 'Ana']);

    $response->assertSessionHasNoErrors();
    expect(FormSubmission::count())->toBe(1);
});
