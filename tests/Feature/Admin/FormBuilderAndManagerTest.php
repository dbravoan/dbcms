<?php

declare(strict_types=1);

use App\Models\Market;
use App\Models\User;
use App\Services\Content\EditorialDraftService;
use Database\Seeders\PlatformShowcaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\ContentAudit\Contracts\Services\ContentAuditServiceInterface;
use Modules\Forms\Models\CustomForm;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(PlatformShowcaseSeeder::class);
    $this->admin = User::where('email', 'admin@db-cms.io')->firstOrFail();
});

test('admin can access form builder create page', function (): void {
    $response = $this->actingAs($this->admin, 'web')->get(route('admin.forms.create'));

    $response->assertOk();
    $response->assertSee('Constructor de Formularios');
    $response->assertSee('Campos Dinámicos del Formulario');
});

test('admin can create a form with dynamic fields and store them in database', function (): void {
    $payload = [
        'slug' => 'auditoria-edge',
        'notification_email' => 'tech@empresa.com',
        'is_active' => '1',
        'translations' => [
            'es' => [
                'name' => 'Formulario Auditoría Edge',
                'description' => 'Solicita un diagnóstico de rendimiento de red.',
                'submit_button_text' => 'Solicitar Diagnóstico',
                'success_message' => '¡Diagnóstico programado!',
            ],
        ],
        'fields' => [
            [
                'field_key' => 'nombre_contacto',
                'type' => 'text',
                'is_required' => '1',
                'validation_rules' => 'required|string|max:255',
                'translations' => [
                    'es' => ['label' => 'Nombre del Solicitante', 'placeholder' => 'Juan Pérez', 'options' => ''],
                ],
            ],
            [
                'field_key' => 'email_contacto',
                'type' => 'email',
                'is_required' => '1',
                'validation_rules' => 'required|email',
                'translations' => [
                    'es' => ['label' => 'Correo Corporativo', 'placeholder' => 'juan@empresa.com', 'options' => ''],
                ],
            ],
            [
                'field_key' => 'tipo_infraestructura',
                'type' => 'select',
                'is_required' => '0',
                'validation_rules' => 'nullable|string',
                'translations' => [
                    'es' => [
                        'label' => 'Tipo de Infraestructura',
                        'placeholder' => 'Selecciona...',
                        'options' => "on-premise | On-premise\ncloud-aws | Cloud AWS\nbare-metal | Bare Metal",
                    ],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->admin, 'web')->post(route('admin.forms.store'), $payload);

    $form = CustomForm::where('slug', 'auditoria-edge')->first();
    expect($form)->not->toBeNull();
    expect($form->name)->toBe('Formulario Auditoría Edge');
    expect($form->fields)->toHaveCount(3);

    $selectField = $form->fields()->where('field_key', 'tipo_infraestructura')->first();
    expect($selectField)->not->toBeNull();
    expect($selectField->label)->toBe('Tipo de Infraestructura');
    expect(array_column($selectField->options, 'value'))->toBe(['on-premise', 'cloud-aws', 'bare-metal']);
    expect(array_column($selectField->options, 'label'))->toBe(['On-premise', 'Cloud AWS', 'Bare Metal']);

    $response->assertRedirect(route('admin.forms.edit', $form));
});

test('admin can access form edit page with preloaded dynamic fields', function (): void {
    $form = CustomForm::where('slug', 'contacto')->firstOrFail();

    $response = $this->actingAs($this->admin, 'web')->get(route('admin.forms.edit', $form));

    $response->assertOk();
    $response->assertSee('Editar Formulario:');
    $response->assertSee('contacto');
    $response->assertSee('Definición de Campos del Formulario');
});

test('admin can update form attributes and reorder/sync fields', function (): void {
    $form = CustomForm::where('slug', 'contacto')->firstOrFail();
    $liveName = $form->name;
    $liveKeys = $form->fields->pluck('field_key')->all();

    $updatePayload = [
        'slug' => 'contacto',
        'is_active' => '1',
        'translations' => [
            'es' => [
                'name' => 'Formulario de Contacto Actualizado',
                'description' => 'Nueva descripción del formulario de contacto',
                'submit_button_text' => 'Enviar Mensaje Rápido',
                'success_message' => '¡Recibido con éxito!',
            ],
        ],
        'fields' => [
            [
                'field_key' => 'email',
                'type' => 'email',
                'is_required' => '1',
                'validation_rules' => 'required|email',
                'translations' => [
                    'es' => ['label' => 'Email Principal', 'placeholder' => 'tu@email.com', 'options' => ''],
                ],
            ],
            [
                'field_key' => 'telefono',
                'type' => 'tel',
                'is_required' => '0',
                'validation_rules' => 'nullable|string',
                'translations' => [
                    'es' => ['label' => 'Teléfono Móvil', 'placeholder' => '+34 600000000', 'options' => ''],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->admin, 'web')->put(route('admin.forms.update', $form), $updatePayload);

    $response->assertRedirect(route('admin.forms.edit', $form));

    $form->refresh();
    expect($form->name)->toBe($liveName);
    expect($form->fields->pluck('field_key')->all())->toBe($liveKeys);
    $draft = app(EditorialDraftService::class)->forEditor($form);
    expect($draft->name)->toBe('Formulario de Contacto Actualizado');
    expect($draft->fields->pluck('field_key')->all())->toBe(['email', 'telefono']);
    $this->get(route('admin.forms.edit', $form))->assertOk()->assertSee(route('admin.audit.review', ['type' => 'forms', 'id' => $form->id]));
    app(ContentAuditServiceInterface::class)->approve($form, $this->admin);
    $form->refresh();
    expect($form->name)->toBe('Formulario de Contacto Actualizado');
    expect($form->fields)->toHaveCount(2);

    $keys = $form->fields->pluck('field_key')->toArray();
    expect($keys)->toBe(['email', 'telefono']);
});

test('admin can persist per-locale form and field translations', function (): void {
    $payload = [
        'slug' => 'formulario-multilingue',
        'is_active' => '1',
        'translations' => [
            'es' => ['name' => 'Formulario Multilingüe', 'submit_button_text' => 'Enviar', 'success_message' => 'Gracias'],
            'en' => ['name' => 'Multilingual Form', 'submit_button_text' => 'Send', 'success_message' => 'Thanks'],
        ],
        'fields' => [
            [
                'field_key' => 'nombre',
                'type' => 'text',
                'is_required' => '1',
                'translations' => [
                    'es' => ['label' => 'Nombre', 'placeholder' => 'Tu nombre'],
                    'en' => ['label' => 'Name', 'placeholder' => 'Your name'],
                ],
            ],
        ],
    ];

    $response = $this->actingAs($this->admin, 'web')->post(route('admin.forms.store'), $payload);

    $form = CustomForm::where('slug', 'formulario-multilingue')->firstOrFail();
    $response->assertRedirect(route('admin.forms.edit', $form));

    expect($form->translations)->toHaveCount(2);
    expect($form->translation('es')?->name)->toBe('Formulario Multilingüe');
    expect($form->translation('en')?->name)->toBe('Multilingual Form');

    $field = $form->fields()->firstOrFail();
    expect($field->translation('es')?->label)->toBe('Nombre');
    expect($field->translation('en')?->label)->toBe('Name');
});

test('a form can be assigned to several markets', function (): void {
    $marketIds = Market::query()->where('is_active', true)->pluck('id')->all();

    $response = $this->actingAs($this->admin, 'web')->post(route('admin.forms.store'), [
        'slug' => 'formulario-multimercado',
        'is_active' => '1',
        'market_ids' => $marketIds,
        'translations' => [
            'es' => ['name' => 'Formulario Multimercado'],
        ],
    ]);

    $form = CustomForm::where('slug', 'formulario-multimercado')->firstOrFail();
    $response->assertRedirect(route('admin.forms.edit', $form));

    expect($form->markets)->toHaveCount(count($marketIds));
});

test('the forms listing can be filtered by assigned market', function (): void {
    $markets = Market::query()->where('is_active', true)->take(2)->get();
    $first = $markets[0];
    $second = $markets[1];

    $this->actingAs($this->admin, 'web')->post(route('admin.forms.store'), [
        'slug' => 'formulario-solo-primer-mercado',
        'is_active' => '1',
        'market_ids' => [$first->id],
        'translations' => ['es' => ['name' => 'Formulario Primer Mercado']],
    ])->assertRedirect();

    $forSecond = $this->actingAs($this->admin, 'web')
        ->get(route('admin.forms.index', ['market_id' => $second->id]));
    $forSecond->assertOk();
    expect($forSecond->viewData('forms')->pluck('slug')->all())
        ->not->toContain('formulario-solo-primer-mercado');

    $forFirst = $this->actingAs($this->admin, 'web')
        ->get(route('admin.forms.index', ['market_id' => $first->id]));
    $forFirst->assertOk();
    expect($forFirst->viewData('forms')->pluck('slug')->all())
        ->toContain('formulario-solo-primer-mercado');
});
