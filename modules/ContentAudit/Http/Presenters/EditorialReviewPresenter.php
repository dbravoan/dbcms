<?php

declare(strict_types=1);

namespace Modules\ContentAudit\Http\Presenters;

use Illuminate\Support\Str;

/** Only named content fields are exposed; credentials and raw snapshots stay private. */
final class EditorialReviewPresenter
{
    /**
     * @param  array<string,mixed>  $snapshot
     * @return list<array{label:string,value:string}>
     */
    public function summary(array $snapshot): array
    {
        $rows = $this->fields((array) ($snapshot['attributes'] ?? []), '');
        $ids = (array) ($snapshot['market_ids'] ?? []);
        if ($ids !== []) {
            $rows[] = ['label' => 'Mercados de esta revisión', 'value' => implode(', ', array_map(static fn (mixed $id): string => '#'.(string) $id, $ids))];
        }
        foreach ((array) ($snapshot['market_pivots'] ?? []) as $pivot) {
            if (is_array($pivot)) {
                $rows = array_merge($rows, $this->fields($pivot, 'Mercado #'.($pivot['market_id'] ?? '?').' · '));
            }
        }
        foreach ((array) ($snapshot['section_records'] ?? []) as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $prefix = 'Bloque '.($index + 1).' · ';
            $rows = array_merge($rows, $this->fields((array) ($section['attributes'] ?? []), $prefix));
            foreach ((array) ($section['translations'] ?? []) as $translation) {
                if (! is_array($translation)) {
                    continue;
                }
                $content = $translation['content_json'] ?? [];
                if (is_string($content)) {
                    $content = json_decode($content, true);
                }
                if (is_array($content)) {
                    $locale = is_scalar($translation['locale'] ?? null) ? strip_tags((string) $translation['locale']) : '?';
                    $rows = array_merge($rows, $this->fields($content, $prefix.strtoupper($locale).' · '));
                }
            }
        }
        foreach ((array) ($snapshot['module_relations']['items'] ?? []) as $index => $item) {
            if (is_array($item)) {
                $rows = array_merge($rows, $this->fields($item, 'Elemento '.($index + 1).' · '));
            }
        }
        foreach ((array) ($snapshot['module_relations']['fields'] ?? []) as $index => $field) {
            if (! is_array($field)) {
                continue;
            }
            $prefix = 'Campo '.($index + 1).' · ';
            $rows = array_merge($rows, $this->fields((array) ($field['attributes'] ?? []), $prefix));
            foreach ((array) ($field['translations'] ?? []) as $translation) {
                if (is_array($translation)) {
                    $locale = is_scalar($translation['locale'] ?? null) ? strip_tags((string) $translation['locale']) : '?';
                    $rows = array_merge($rows, $this->fields($translation, $prefix.strtoupper($locale).' · '));
                }
            }
        }

        return $rows;
    }

    /**
     * @param  array<string,mixed>  $values
     * @return list<array{label:string,value:string}>
     */
    private function fields(array $values, string $prefix): array
    {
        $rows = [];
        $labels = ['sku' => 'Referencia', 'price' => 'Precio', 'price_override' => 'Precio específico', 'stock' => 'Existencias', 'stock_quantity' => 'Existencias', 'custom_price' => 'Precio específico', 'stock_override' => 'Existencias específicas', 'is_published' => 'Visibilidad de la revisión', 'is_active' => 'Activación de la revisión', 'title' => 'Título', 'name' => 'Nombre', 'layout' => 'Diseño', 'template' => 'Plantilla', 'currency' => 'Moneda', 'image_url' => 'Imagen', 'alt_text' => 'Texto alternativo', 'caption' => 'Pie de imagen', 'order' => 'Posición', 'field_key' => 'Identificador', 'type' => 'Tipo de campo', 'is_required' => 'Obligatorio', 'label' => 'Etiqueta', 'placeholder' => 'Texto de ayuda', 'options' => 'Opciones', 'content' => 'Contenido', 'text' => 'Texto', 'summary' => 'Resumen', 'description' => 'Descripción', 'subtitle' => 'Subtítulo', 'heading' => 'Encabezado', 'question' => 'Pregunta', 'answer' => 'Respuesta'];
        foreach ($labels as $key => $label) {
            if ($key === 'options' && array_key_exists($key, $values)) {
                $values[$key] = $this->optionsText($values[$key]);
            }
            if (! array_key_exists($key, $values) || (! is_scalar($values[$key]) && $values[$key] !== null)) {
                continue;
            }
            $value = $values[$key];
            if (in_array($key, ['is_published', 'is_active', 'is_required'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
            }
            if ($key === 'image_url') {
                $value = preg_replace('/[?#].*$/', '', (string) $value) ?? '';
            }
            $rows[] = ['label' => $prefix.$label, 'value' => $value === null ? 'Sin valor específico' : Str::limit(strip_tags((string) $value), 500)];
        }

        return $rows;
    }

    private function optionsText(mixed $options): ?string
    {
        if (is_string($options)) {
            $decoded = json_decode($options, true);
            if (! is_array($decoded)) {
                return $options;
            }
            $options = $decoded;
        }
        if (! is_array($options)) {
            return null;
        }
        $labels = [];
        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = $option['value'] ?? '';
                $label = $option['label'] ?? '';
                if (is_scalar($value) && is_scalar($label)) {
                    $labels[] = (string) $value.': '.(string) $label;
                }
            } elseif (is_scalar($option)) {
                $labels[] = (string) $key.': '.(string) $option;
            }
        }

        return implode('; ', $labels);
    }
}
