<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Modules\ContentAudit\Http\Presenters\EditorialReviewPresenter;
use PHPUnit\Framework\TestCase;

final class EditorialReviewPresenterTest extends TestCase
{
    public function test_private_fields_are_excluded_from_the_human_review(): void
    {
        $summary = (new EditorialReviewPresenter)->summary([
            'attributes' => ['price' => 0, 'stock_quantity' => 0, 'is_active' => false, 'password' => 'private-hash', 'api_key' => 'private-api-key'],
            'market_pivots' => [['market_id' => 2, 'custom_price' => 0]],
            'module_relations' => ['items' => [['title' => '<b>Visible image</b>', 'image_url' => 'https://images.example/test.jpg?token=private-token', 'caption' => '<img src=x onerror=alert(1)>Safe caption', 'secret' => 'private-secret']]],
        ]);
        $labels = array_column($summary, 'value', 'label');
        self::assertSame('0', $labels['Precio']);
        self::assertSame('0', $labels['Existencias']);
        self::assertSame('No', $labels['Activación de la revisión']);
        self::assertSame('0', $labels['Mercado #2 · Precio específico']);
        self::assertSame('Visible image', $labels['Elemento 1 · Título']);
        self::assertSame('https://images.example/test.jpg', $labels['Elemento 1 · Imagen']);
        self::assertSame('Safe caption', $labels['Elemento 1 · Pie de imagen']);
        self::assertStringNotContainsString('private-', json_encode($summary, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('<', json_encode($summary, JSON_THROW_ON_ERROR));
    }

    public function test_staged_form_definitions_and_localized_labels_are_visible_without_private_data(): void
    {
        $summary = (new EditorialReviewPresenter)->summary(['module_relations' => ['fields' => [[
            'attributes' => ['field_key' => 'interest', 'type' => 'select', 'is_required' => false, 'order' => 2, 'password' => 'private-password'],
            'translations' => [
                ['locale' => 'es', 'label' => '<b>Interés</b>', 'placeholder' => 'Selecciona una opción', 'options' => '[{"value":"a","label":"Servicio A","secret":"private-option"},{"value":"b","label":"Servicio B"}]', 'secret' => 'private-token'],
                ['locale' => 'en', 'label' => 'Interest', 'placeholder' => 'Choose one', 'options' => 'Service A|Service B'],
            ],
        ]]]]);
        $labels = array_column($summary, 'value', 'label');
        self::assertSame('interest', $labels['Campo 1 · Identificador']);
        self::assertSame('select', $labels['Campo 1 · Tipo de campo']);
        self::assertSame('No', $labels['Campo 1 · Obligatorio']);
        self::assertSame('Interés', $labels['Campo 1 · ES · Etiqueta']);
        self::assertSame('Choose one', $labels['Campo 1 · EN · Texto de ayuda']);
        self::assertSame('a: Servicio A; b: Servicio B', $labels['Campo 1 · ES · Opciones']);
        self::assertStringNotContainsString('private-', json_encode($summary, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('<', json_encode($summary, JSON_THROW_ON_ERROR));
    }
}
