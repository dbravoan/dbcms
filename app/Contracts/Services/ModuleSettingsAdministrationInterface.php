<?php

declare(strict_types=1);

namespace App\Contracts\Services;

use App\DTOs\Modules\ModuleSettingsUpdateDTO;
use App\DTOs\Modules\ModuleSettingsViewDTO;

interface ModuleSettingsAdministrationInterface
{
    public function view(string $identifier): ModuleSettingsViewDTO;

    public function update(string $identifier, ModuleSettingsUpdateDTO $settings): string;
}
