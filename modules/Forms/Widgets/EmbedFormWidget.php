<?php

declare(strict_types=1);

namespace Modules\Forms\Widgets;

use App\Core\Widgets\BaseWidgetComponent;
use App\Models\Market;
use Illuminate\Contracts\View\View;
use Modules\Forms\Services\FormDomainService;

class EmbedFormWidget extends BaseWidgetComponent
{
    public static function getKey(): string
    {
        return 'forms.embed_form';
    }

    public static function getTitle(): string
    {
        return 'Embedded Dynamic Custom Form';
    }

    public static function getOptionsSchema(): array
    {
        return [
            'form_slug' => ['type' => 'string', 'label' => 'Form Slug', 'required' => true],
        ];
    }

    public function render(array $parameters = []): View|string
    {
        $slug = isset($parameters['form_slug']) ? (string) $parameters['form_slug'] : 'contact';

        /** @var Market|null $market */
        $market = app()->bound('current.market') ? app('current.market') : null;

        $form = app(FormDomainService::class)->getFormBySlug($slug, $market);

        return $this->renderWidgetView('forms', 'embed_form', [
            'form' => $form,
            'parameters' => $parameters,
        ]);
    }
}
