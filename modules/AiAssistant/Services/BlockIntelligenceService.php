<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Blocks\Contracts\BlockRegistryInterface;
use App\Core\Blocks\Contracts\BlockTypeInterface;

class BlockIntelligenceService
{
    public function __construct(
        protected BlockRegistryInterface $blockRegistry
    ) {}

    /**
     * Get active block definition by type.
     */
    public function getBlockDefinition(string $type): ?BlockTypeInterface
    {
        return $this->blockRegistry->get($type);
    }

    /**
     * Get list of all available block types with their schemas.
     *
     * @return array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>
     */
    public function getBlockTaxonomy(): array
    {
        $taxonomy = [];
        $activeBlocks = $this->blockRegistry->getActiveBlocks();

        foreach ($activeBlocks as $type => $block) {
            $taxonomy[$type] = [
                'name' => $block->getName(),
                'description' => $block->getDescription(),
                'category' => $block->getCategory(),
                'default_payload' => $block->getDefaultPayload(),
            ];
        }

        return $taxonomy;
    }

    /**
     * Alias for getBlockTaxonomy.
     *
     * @return array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>
     */
    public function getAvailableBlocks(): array
    {
        return $this->getBlockTaxonomy();
    }

    /**
     * Real, currently available entities usable by dynamic blocks
     * (forms, blog posts/categories, shop products/categories, gallery albums
     * and images, chart types). Lets the AI reference real data, not placeholders.
     *
     * @return array<string, mixed>
     */
    public function getDynamicDataPayloads(): array
    {
        return $this->blockRegistry->getDynamicDataPayloads();
    }

    /**
     * Reactive list of every active block, ready to feed selectors/pickers.
     *
     * @return list<array{type: string, name: string, description: string, icon: string, category: string}>
     */
    public function getBlockOptions(): array
    {
        $options = [];

        foreach ($this->blockRegistry->getActiveBlocks() as $type => $block) {
            $options[] = [
                'type' => (string) $type,
                'name' => $block->getName(),
                'description' => $block->getDescription(),
                'icon' => $block->getIcon(),
                'category' => $block->getCategory(),
            ];
        }

        return $options;
    }

    /**
     * Validates and sanitizes a generated block payload against registered block defaults.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function sanitizeBlockPayload(string $type, array $data): array
    {
        $block = $this->getBlockDefinition($type);
        if ($block === null) {
            return $data;
        }

        $default = $block->getDefaultPayload();
        // Editor examples are not generated content or evidence. Keep layout
        // settings, but never fill missing copy or collections with demo data.
        $layoutKeys = ['align', 'columns', 'grid_columns', 'chart_type', 'layout', 'position', 'color', 'background_color', 'text_color', 'icon', 'mode', 'sort', 'order', 'limit'];
        foreach ($default as $key => $value) {
            if (is_array($value)) {
                $default[$key] = [];
            } elseif (is_string($value) && ! in_array($key, $layoutKeys, true)) {
                $default[$key] = '';
            } elseif (in_array($key, ['donut_percentage', 'metric_value'], true)) {
                $default[$key] = null;
            }
        }

        // Merge generated data over default payload schema to ensure no required fields are missing
        $merged = array_merge($default, $data);

        // Sanitize string fields
        array_walk_recursive($merged, function (&$val): void {
            if (is_string($val)) {
                $val = trim($val);
            }
        });

        return $merged;
    }

    /** Quantitative blocks may only reproduce labelled values supplied by the user.
     * @param  array<string,mixed>  $payload
     */
    public function hasSuppliedQuantitativeData(string $type, array $payload, string $brief): bool
    {
        if (! in_array($type, ['charts', 'kpi'], true)) {
            return true;
        }
        $lower = mb_strtolower($brief);
        if (! preg_match('/\b(chart|charts|graph|graphs|gráfica|gráficas|grafica|graficas|kpi|metrics|métricas|metricas|estadísticas|statistics)\b/u', $lower)
            || preg_match('/\b(sin|without|no)\b[^.!?;]*(?:charts?|graphs?|gráfic|grafic|kpi|metrics|métric|metric|estadístic|statistics)/u', $lower)) {
            return false;
        }
        $chartType = (string) ($payload['chart_type'] ?? 'bar');
        $rows = match (true) {
            $type === 'charts' && $chartType === 'metric' => [['label' => $payload['metric_label'] ?? '', 'value' => $payload['metric_value'] ?? null]],
            $type === 'charts' && $chartType === 'donut' => [['label' => $payload['donut_label'] ?? '', 'value' => $payload['donut_percentage'] ?? null]],
            default => (array) ($payload['items'] ?? $payload['metrics'] ?? []),
        };
        if ($rows === []) {
            return false;
        }
        foreach ($rows as $row) {
            if (! is_array($row)) {
                return false;
            }
            $label = trim((string) ($row['label'] ?? $row['point'] ?? ''));
            $value = $row['value'] ?? $row['number'] ?? null;
            if ($label === '' || (! is_string($value) && ! is_int($value) && ! is_float($value)) || ! str_contains($lower, mb_strtolower($label))) {
                return false;
            }
            $literal = trim((string) $value);
            if ($literal === '' || ! preg_match('/(?<![\p{L}\p{N}.])'.preg_quote($literal, '/').'(?![\p{L}\p{N}.])/u', $brief)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Preferred block order per intent. Only blocks that are actually
     * registered/active are emitted — never a hardcoded type that may not exist.
     *
     * @var array<string, list<string>>
     */
    private const SEQUENCES = [
        'landing' => ['hero', 'features', 'pricing', 'faq', 'cta'],
        'contact' => ['hero', 'features', 'faq', 'widget_form'],
        'default' => ['hero', 'features', 'faq', 'cta'],
    ];

    /**
     * @var array<string, string>
     */
    private const RATIONALES = [
        'hero' => 'Captura la atención con una propuesta de valor clara y una llamada a la acción.',
        'text' => 'Desarrolla el mensaje con texto estructurado.',
        'features' => 'Detalla los beneficios clave para el usuario objetivo.',
        'kpi' => 'Aporta prueba social cuantitativa y autoridad inmediata.',
        'pricing' => 'Compara planes y facilita la decisión de compra.',
        'testimonials' => 'Refuerza la confianza con voces reales de clientes.',
        'faq' => 'Resuelve objeciones frecuentes antes de la conversión.',
        'cta' => 'Cierra con una acción destacada hacia registro o contacto.',
        'before_after' => 'Compara el antes y el después de forma visual.',
        'code_embed' => 'Incrusta código o HTML personalizado.',
        'roi' => 'Ayuda a estimar el retorno con una calculadora.',
        'video' => 'Muestra un clip cinemático o vídeo explicativo de alto impacto.',
        'widget_posts' => 'Enlaza contenido relacionado del blog.',
        'widget_products' => 'Muestra productos relevantes del catálogo.',
        'widget_form' => 'Captura leads con un formulario contextual.',
        'gallery' => 'Refuerza el mensaje con imágenes reales.',
        'charts' => 'Presenta los datos con gráficas claras.',
    ];

    /**
     * Detects the most appropriate block type from user natural language instruction.
     */
    public function detectBlockTypeFromIntent(string $intent): string
    {
        $lower = mb_strtolower($intent);

        if (str_contains($lower, 'roi') || str_contains($lower, 'retorno') || str_contains($lower, 'inversion') || str_contains($lower, 'calculadora')) {
            return 'roi';
        }

        if (str_contains($lower, 'video') || str_contains($lower, 'vídeo') || str_contains($lower, 'clip') || str_contains($lower, 'trailer') || str_contains($lower, 'teaser')) {
            return 'video';
        }

        if (str_contains($lower, 'hero') || str_contains($lower, 'portada') || str_contains($lower, 'cabecera') || str_contains($lower, 'encabezado')) {
            return 'hero';
        }

        if (str_contains($lower, 'precio') || str_contains($lower, 'pricing') || str_contains($lower, 'tarifa') || str_contains($lower, 'plan')) {
            return 'pricing';
        }

        if (str_contains($lower, 'faq') || str_contains($lower, 'pregunta') || str_contains($lower, 'duda')) {
            return 'faq';
        }

        if (str_contains($lower, 'testimonio') || str_contains($lower, 'opinion') || str_contains($lower, 'review') || str_contains($lower, 'cliente')) {
            return 'testimonials';
        }

        if (str_contains($lower, 'kpi') || str_contains($lower, 'metrica') || str_contains($lower, 'métrica') || str_contains($lower, 'cifra') || str_contains($lower, 'numero')) {
            return 'kpi';
        }

        if (str_contains($lower, 'feature') || str_contains($lower, 'caracteristica') || str_contains($lower, 'beneficio') || str_contains($lower, 'ventaja')) {
            return 'features';
        }

        if (str_contains($lower, 'cta') || str_contains($lower, 'llamada') || str_contains($lower, 'accion') || str_contains($lower, 'acción') || str_contains($lower, 'boton')) {
            return 'cta';
        }

        if (str_contains($lower, 'before') || str_contains($lower, 'antes') || str_contains($lower, 'comparativa')) {
            return 'before_after';
        }

        if (str_contains($lower, 'carrusel') || str_contains($lower, 'carousel') || str_contains($lower, 'slider')) {
            return 'carousel';
        }

        return 'text';
    }

    /**
     * Recommends a sequenced list of blocks based on semantic user intent.
     *
     * @return list<array{type: string, name: string, rationale: string}>
     */
    public function recommendSequence(string $intent, string $locale = 'es'): array
    {
        $lower = mb_strtolower($intent);

        $has = fn (string ...$keywords) => array_any($keywords, fn ($k) => str_contains($lower, $k));

        $sequence = match (true) {
            $has('landing', 'inicio', 'home', 'principal', 'saas', 'producto', 'servicio', 'app') => 'landing',
            $has('contacto', 'contact', 'lead', 'presupuesto') => 'contact',
            default => 'default',
        };

        $types = self::SEQUENCES[$sequence];

        // Pricing only makes sense when the intent is about plans/prices.
        if ($sequence === 'landing' && ! $has('precio', 'tarifa', 'plan', 'suscripcion')) {
            $types = array_values(array_diff($types, ['pricing']));
        }

        $recommendations = [];

        foreach ($types as $type) {
            $block = $this->blockRegistry->get($type);

            if ($block === null) {
                continue; // Never emit a type that is not available.
            }

            $recommendations[] = [
                'type' => $type,
                'name' => $block->getName(),
                'rationale' => self::RATIONALES[$type] ?? 'Sección recomendada para este contenido.',
            ];
        }

        return $recommendations;
    }
}
