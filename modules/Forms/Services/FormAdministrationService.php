<?php

declare(strict_types=1);

namespace Modules\Forms\Services;

use App\DTOs\Content\EntityMutationData;
use App\Models\User;
use App\Services\Capabilities\EntityCrudService;
use Modules\Forms\Contracts\FormAdministrationServiceInterface;
use Modules\Forms\Models\CustomForm;

/** The HTTP editor and native executor share a single transactional writer. */
final class FormAdministrationService implements FormAdministrationServiceInterface
{
    public function __construct(private readonly EntityCrudService $entities) {}

    public function create(array $input, ?User $actor): CustomForm
    {
        $result = $this->entities->create('form', EntityMutationData::fromLegacy($input)->arguments, $actor);
        $form = $this->entities->resolveEntity('form', $result['id']);
        assert($form instanceof CustomForm);

        return $form;
    }

    public function update(CustomForm $form, array $input): array
    {
        $args = EntityMutationData::fromLegacy($input)->arguments;
        if ($form->is_active && ! empty($args['attributes']['is_active'])) {
            unset($args['attributes']['is_active']);
        }

        return $this->entities->update('form', $form->getKey(), $args);
    }

    public function delete(CustomForm $form): void
    {
        $this->entities->delete('form', $form->getKey());
    }
}
