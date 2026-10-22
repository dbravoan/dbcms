<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Market;
use Modules\Forms\Models\CustomForm;

final class FormFixtures
{
    public static function contact(): CustomForm
    {
        $form = CustomForm::create(['slug' => 'contacto', 'is_active' => true]);
        $form->translations()->create(['locale' => 'es', 'name' => 'Formulario de contacto', 'submit_button_text' => 'Enviar', 'success_message' => 'Recibido']);
        $form->markets()->sync(Market::pluck('id')->all());
        $form->submissions()->create(['locale' => 'es', 'payload' => ['name' => 'Marta Ruiz', 'email' => 'marta@example.test'], 'is_reviewed' => false]);

        return $form;
    }
}
