<?php

declare(strict_types=1);

namespace Modules\Forms\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Core\Blocks\Contracts\TranslatableBlockInterface;
use App\Models\Market;
use Modules\Forms\Models\CustomForm;
use Modules\Forms\Models\FormField;

class CustomFormBlock extends AbstractBlockType implements TranslatableBlockInterface
{
    public function getType(): string
    {
        return 'widget_form';
    }

    public function getName(): string
    {
        return 'Formulario Interactivo de Captura';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-clipboard-list';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Formulario dinámico configurable con campos personalizados y protección anti-spam honeypot.';
    }

    public function getRequiredModule(): ?string
    {
        return 'forms';
    }

    /** @return list<string> */
    public function getTranslationPaths(): array
    {
        return ['title', 'button_text'];
    }

    public function getEditorData(): array
    {
        return ['forms' => CustomForm::where('is_active', true)->with(['translations', 'fields.translations'])->get()->map(fn (CustomForm $form): array => ['id' => $form->id, 'name' => $form->name, 'slug' => $form->slug, 'fields' => $form->fields->map(fn (FormField $field): array => ['key' => $field->field_key, 'label' => $field->label, 'type' => $field->type, 'required' => (bool) $field->is_required])->all()])->all()];
    }

    public function getEditorSchema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Título', 'description' => 'Título opcional sobre el formulario.'],
            'form_slug' => ['type' => 'select', 'label' => 'Formulario', 'options' => ['' => 'Selecciona un formulario'], 'options_source' => 'forms', 'option_label' => 'name', 'option_value' => 'slug', 'rules' => ['nullable', 'string', 'max:255'], 'description' => 'Formulario que se insertará en la página.'],
            'button_text' => ['type' => 'text', 'label' => 'Texto del botón', 'description' => 'Texto del botón de envío.'],
        ];
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Formulario de Contacto',
            'form_slug' => '',
            'button_text' => 'Enviar Mensaje',
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $slug = (string) ($payload['form_slug'] ?? '');
        $title = ! empty($payload['title']) ? (string) $payload['title'] : null;
        $btnText = ! empty($payload['button_text']) ? (string) $payload['button_text'] : null;

        return view('forms::components.form-engine', [
            'slug' => $slug,
            'market' => $market,
            'locale' => $locale,
            'title' => $title,
            'buttonText' => $btnText,
            'showCard' => true,
        ])->render();
    }
}
