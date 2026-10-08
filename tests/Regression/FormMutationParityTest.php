<?php

declare(strict_types=1);

use App\Contracts\Services\LocalizationServiceInterface;
use App\Core\Content\Contracts\EditorialWorkflowInterface;
use App\Core\Content\Contracts\EntitySchemaRegistryInterface;
use App\DTOs\Content\PendingEditorialDraft;
use App\Models\User;
use App\Services\Capabilities\ContentSnapshotService;
use App\Services\Capabilities\EntityCrudService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Schema\FormEntitySchemas;
use Modules\Forms\Services\FormAdministrationService;
use Modules\Forms\Services\FormMutationHandler;

uses(TestCase::class);

beforeEach(function (): void {
    config(['database.default' => 'forms_parity', 'database.connections.forms_parity' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true]]);
    Schema::create('markets', static fn (Blueprint $table) => $table->id());
    (require base_path('modules/Forms/Database/Migrations/2026_09_01_000032_create_form_tables.php'))->up();
    $locale = Mockery::mock(LocalizationServiceInterface::class);
    $locale->shouldReceive('defaultLocale')->andReturn('es');
    app()->instance(LocalizationServiceInterface::class, $locale);
    $registry = app(EntitySchemaRegistryInterface::class);
    foreach (FormEntitySchemas::all() as $schema) {
        $registry->register($schema);
    }
    $this->entities = new EntityCrudService($registry, $locale);
    $this->input = ['translations' => ['es' => ['name' => 'QA formulario']], 'attributes' => ['fields' => [['field_key' => 'Full name', 'type' => 'text', 'is_required' => true, 'translations' => ['es' => ['label' => 'Nombre']]]]]];
});

it('creates native forms with the same structural fields and defaults as the editor', function (): void {
    $result = $this->entities->create('form', $this->input);
    $form = CustomForm::findOrFail($result['id']);

    expect($form->slug)->toBe('qa-formulario')
        ->and($form->is_active)->toBeFalse()
        ->and($form->fields()->firstOrFail()->field_key)->toBe('full_name')
        ->and($result['relations']['fields'][0]['translations']['es']['label'])->toBe('Nombre');
});

it('keeps fields when a partial metadata patch omits them and replaces them only when supplied', function (): void {
    $result = $this->entities->create('form', $this->input);
    $this->entities->update('form', $result['id'], ['attributes' => ['notification_email' => 'qa@example.test']]);
    expect(CustomForm::findOrFail($result['id'])->fields()->count())->toBe(1);
    $this->entities->update('form', $result['id'], ['attributes' => ['fields' => []]]);
    expect(CustomForm::findOrFail($result['id'])->fields()->count())->toBe(0);
});

it('preserves omitted translation attributes in a partial locale patch', function (): void {
    $this->input['translations']['es']['success_message'] = 'Mensaje conservado';
    $result = $this->entities->create('form', $this->input);
    $updated = $this->entities->update('form', $result['id'], ['translations' => ['es' => ['name' => 'Nombre revisado']]]);

    expect($updated['translations']['es']['name'])->toBe('Nombre revisado')
        ->and($updated['translations']['es']['success_message'])->toBe('Mensaje conservado');
});

it('rejects unsupported field types and duplicate normalized keys', function (array $fields): void {
    $this->input['attributes']['fields'] = $fields;
    expect(fn () => $this->entities->create('form', $this->input))->toThrow(ValidationException::class);
    expect(CustomForm::count())->toBe(0);
})->with([
    [[['field_key' => 'bad', 'type' => 'executable']]],
    [[['field_key' => 'Full name', 'type' => 'text'], ['field_key' => 'full_name', 'type' => 'text']]],
]);

it('captures restores and hydrates field revisions without changing their ids or translations', function (): void {
    $result = $this->entities->create('form', $this->input);
    $form = CustomForm::findOrFail($result['id']);
    $handler = app(FormMutationHandler::class);
    $live = $handler->captureRevision($form);
    $this->entities->update('form', $result['id'], ['attributes' => ['fields' => []]]);
    $handler->restoreRevision($form, $live);
    expect($handler->captureRevision($form))->toEqual($live);
    $draft = clone $form;
    $handler->hydrateRevision($draft, $live);
    expect($draft->fields[0]->translations[0]->label)->toBe('Nombre');
});

it('uses the same writer for the HTTP editor adapter and native input', function (): void {
    $service = new FormAdministrationService($this->entities);
    $input = $this->input['attributes'] + ['translations' => $this->input['translations']];
    $form = $service->create($input, null);
    $service->update($form, ['fields' => [['field_key' => 'email', 'type' => 'email', 'translations' => ['es' => ['label' => 'Correo']]]]]);
    $read = $this->entities->find('form', $form->id);

    expect($read['relations']['fields'][0]['field_key'])->toBe('email')
        ->and($read['relations']['fields'][0]['translations']['es']['label'])->toBe('Correo');
});

it('stages active form fields while keeping the live definition intact until restoration of the reviewed snapshot', function (): void {
    $result = $this->entities->create('form', $this->input);
    $form = CustomForm::findOrFail($result['id']);
    CustomForm::withoutEvents(fn () => $form->update(['is_active' => true]));
    $actor = new User;
    $actor->setAttribute('id', 1);
    Auth::setUser($actor);
    $pending = [];
    $audits = Mockery::mock(EditorialWorkflowInterface::class);
    $audits->shouldReceive('isEnabled')->andReturn(true);
    $audits->shouldReceive('latestRevisionId')->andReturn(0);
    $audits->shouldReceive('requiresAudit')->andReturn(false);
    $audits->shouldReceive('pendingDraft')->andReturn(null);
    $audits->shouldReceive('stageDraft')->once()->andReturnUsing(function ($entity, $user, $snapshot, $hash) use (&$pending): PendingEditorialDraft {
        $pending = $snapshot;

        return new PendingEditorialDraft(99, $snapshot);
    });
    app()->instance(EditorialWorkflowInterface::class, $audits);
    $update = $this->entities->update('form', $form->id, ['attributes' => ['fields' => []]]);

    expect($update['live_preserved'])->toBeTrue()
        ->and($update['relations']['fields'])->toBe([])
        ->and($form->fresh()->fields()->count())->toBe(1);
    $snapshots = app(ContentSnapshotService::class);
    $draft = $snapshots->hydrate($form, $pending);
    expect($draft->getRelation('fields'))->toHaveCount(0);
    $snapshots->restore($form, $pending);
    expect($form->fresh()->fields()->count())->toBe(0);
});
