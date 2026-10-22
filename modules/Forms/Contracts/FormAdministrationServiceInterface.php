<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

use App\Models\User;
use Modules\Forms\Models\CustomForm;

interface FormAdministrationServiceInterface
{
    /** @param array<string,mixed> $input */
    public function create(array $input, ?User $actor): CustomForm;

    /** @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function update(CustomForm $form, array $input): array;

    public function delete(CustomForm $form): void;
}
