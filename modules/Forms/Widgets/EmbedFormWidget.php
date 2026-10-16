<?php

declare(strict_types=1);

namespace Modules\Forms\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Modules\Forms\Services\FormDomainService;

class EmbedFormWidget extends BaseWidgetComponent
{
    public function getRequiredModule(): ?string
    {
        return 'forms';
    }

    public function getType(): string
    {
        return 'forms.embed_form';
    }

    public function getName(): string
    {
        return 'Embedded Dynamic Custom Form';
    }

    public function getEditorSchema(): array
    {
        return [
            'form_slug' => ['type' => 'text', 'label' => 'Form Slug', 'required' => true],
        ];
    }

    public function render(array $parameters = [], ?Market $market = null, ?string $locale = null): string
    {
        $slug = isset($parameters['form_slug']) ? (string) $parameters['form_slug'] : 'contact';

        /** @var Market|null $market */
        $market ??= app()->bound('current.market') ? app('current.market') : null;

        $form = app(FormDomainService::class)->getFormBySlug($slug, $market);

        return $this->renderWidgetView('forms', 'embed_form', [
            'form' => $form,
            'parameters' => $parameters,
        ]);
    }
}
