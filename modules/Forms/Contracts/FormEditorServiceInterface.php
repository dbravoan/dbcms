<?php

declare(strict_types=1);

namespace Modules\Forms\Contracts;

use Modules\Forms\DTOs\FormEditorData;
use Modules\Forms\Models\CustomForm;

interface FormEditorServiceInterface
{
    public function creation(): FormEditorData;

    public function editing(CustomForm $form): FormEditorData;
}
