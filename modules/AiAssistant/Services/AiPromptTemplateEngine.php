<?php

declare(strict_types=1);

namespace Modules\AiAssistant\Services;

use App\Core\Content\EntitySchema;
use App\Services\Ai\AntiAiEditorialService;
use Modules\AiAssistant\Contracts\AiBrandProfileServiceInterface;

class AiPromptTemplateEngine
{
    public function __construct(
        protected BlockIntelligenceService $blockIntelligence,
        protected AntiAiEditorialService $antiAiService,
        protected AiBrandProfileServiceInterface $brandProfileService
    ) {}

    /**
     * Builds the foundational system instructions with locale, anti-AI heuristics, and block taxonomy.
     */
    public function buildSystemPrompt(string $locale = 'es', string $market = 'es', string $mode = 'general'): string
    {
        $lang = strtolower(substr(trim($locale), 0, 2));
        $languageName = match ($lang) {
            'es' => 'Español (Castellano)',
            'en' => 'English',
            'fr' => 'Français',
            'de' => 'Deutsch',
            default => 'Español',
        };

        $brandSegment = $this->brandProfileService->buildBrandPromptSegment($locale);
        $profile = $this->brandProfileService->getProfile();
        $brandName = $profile['brand_name'];

        $cliches = $this->antiAiService->getClichesForLocale($lang);
        $forbiddenCliches = implode('", "', array_slice($cliches, 0, 15));

        $taxonomy = $this->blockIntelligence->getBlockTaxonomy();
        $blocksOverview = [];
        foreach ($taxonomy as $type => $info) {
            $blocksOverview[] = "- `{$type}` ({$info['name']}): {$info['description']}";
        }
        $blocksSummary = implode("\n", $blocksOverview);

        $spanishRules = '';
        if ($lang === 'es') {
            $spanishRules = <<<'TXT'
- PUNTUACIÓN REGLAMENTARIA: En castellano es OBLIGATORIO abrir interrogaciones con '¿' y exclamaciones con '¡'. Nunca uses solo signos de cierre.
- CAPITALIZACIÓN DE TÍTULOS: Usa Sentence Case (solo mayúscula inicial y nombres propios o siglas). Está terminantemente prohibido el estilo anglosajón 'Title Case'.
TXT;
        }

        return <<<PROMPT
Eres el Asistente Editorial, Redactor Estratégico y Arquitecto de Maquetación de "{$brandName}", operando sobre un sistema de gestión de contenidos de alto rendimiento y grado enterprise.

{$brandSegment}

================================================================================
REGLA CRÍTICA DE IDIOMA
================================================================================
Idioma objetivo: {$languageName} (Código: {$lang}, Mercado: {$market}).
DEBES responder y redactar TODO el contenido, títulos, párrafos, llamadas a la acción y valores JSON EXCLUSIVAMENTE en {$languageName}.
Bajo ninguna circunstancia mezcles otros idiomas a menos que sean nombres de marcas o términos técnicos universales.

================================================================================
POLÍTICA ANTI-CLICHÉS Y PROSA NATURAL (ANTI-AI GUARDRAILS)
================================================================================
1. PROHIBICIÓN DE CLICHÉS ROBÓTICOS DE IA:
   Está estrictamente prohibido utilizar giros sintéticos típicos de LLMs, tales como:
   "{$forbiddenCliches}".
   Opta por un tono periodístico, directo, empático y profesional con voz propia.

2. RITMO Y VARIABILIDAD (BURSTINESS HUMANA):
   Alterna deliberadamente frases breves y contundentes (4 a 8 palabras) con oraciones compuestas y explicativas (20 a 30 palabras). Evita a toda costa la cadencia monótona de 12-15 palabras por frase.

{$spanishRules}

================================================================================
TAXONOMÍA DE BLOQUES MODULARES ACTIVOS EN dbCMS
================================================================================
El CMS cuenta con los siguientes tipos de bloques registrados:
{$blocksSummary}

Cuando se te pida generar o recomendar bloques, respeta rigurosamente esta taxonomía y devuelve JSON sintácticamente válido.
PROMPT;
    }

    /**
     * Builds prompt for generating a specific block with JSON payload.
     *
     * @return array{system: string, user: string}
     */
    public function buildBlockGenerationPrompt(string $blockType, string $intent, string $locale = 'es', string $market = 'es'): array
    {
        $system = $this->buildSystemPrompt($locale, $market, 'block');
        $def = $this->blockIntelligence->getBlockDefinition($blockType);
        $samplePayload = json_encode($def?->getDefaultPayload() ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $user = <<<USER
Genera un bloque de tipo '{$blockType}' para la siguiente intención/sección:
"{$intent}"

Estructura de referencia para el payload del bloque '{$blockType}':
{$samplePayload}

INSTRUCCIONES DE RESPUESTA:
1. Devuelve ÚNICAMENTE un objeto JSON válido con la siguiente estructura exacta:
{
  "type": "{$blockType}",
  "data": { ... campos del bloque con copy convincente ... },
  "reasoning": "Breve explicación editorial en 1 frase de las decisiones tomadas"
}
2. Todo el copy debe estar en el idioma solicitado ({$locale}).
3. NO añadas texto fuera del bloque JSON.
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Builds prompt for drafting a full article with SEO metadata.
     *
     * @return array{system: string, user: string}
     */
    public function buildDraftArticlePrompt(string $topic, string $locale = 'es', string $market = 'es', string $style = 'periodistico', bool $includeBlocks = false): array
    {
        $system = $this->buildSystemPrompt($locale, $market, 'draft');

        $blocksInstruction = $includeBlocks
            ? 'Incluye un array "recommended_blocks" con una secuencia recomendada de bloques modulares para complementar la lectura (ej. hero, features, faq, cta).'
            : 'No es necesario incluir bloques modulares.';

        $user = <<<USER
Redacta un artículo completo y profesional sobre el siguiente tema:
"{$topic}"

Estilo editorial: {$style}
Mercado objetivo: {$market}
Idioma: {$locale}
{$blocksInstruction}

INSTRUCCIONES DE RESPUESTA:
Devuelve ÚNICAMENTE un objeto JSON con la siguiente estructura:
{
  "title": "Título atractivo y periodístico (en Sentence Case)",
  "content": "Cuerpo del artículo en formato Markdown con subtítulos ##, párrafos fluidos y ritmo variado",
  "excerpt": "Resumen conciso del artículo en 2-3 frases (máximo 250 caracteres)",
  "meta_title": "Meta Title SEO (50-60 caracteres)",
  "meta_description": "Meta Description persuasiva para Google (130-155 caracteres)",
  "keywords": ["palabra clave 1", "palabra clave 2", "palabra clave 3"],
  "recommended_blocks": [
    { "type": "hero", "rationale": "..." }
  ]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Builds the prompt for creating a complete, ready-to-review element.
     *
     * When `$composeSections` is true the model receives the real block catalog
     * and must compose an ordered block sequence (the CMS block manager).
     *
     * @param  array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>  $blockCatalog
     * @param  list<array{type: string, name: string, rationale: string}>  $recommendedBlocks
     * @return array{system: string, user: string}
     */
    /**
     * Core, schema-driven prompt: asks only for the roles the entity declares,
     * so any module entity (article, category, product, page, gallery, form...)
     * can be generated without a bespoke prompt.
     *
     * @return array{system: string, user: string}
     */
    public function buildEntityPrompt(
        EntitySchema $schema,
        string $brief,
        string $locale = 'es',
        string $market = 'es',
        string $hint = '',
    ): array {
        $system = $this->buildSystemPrompt($locale, $market, 'draft');

        $roleSpec = [
            EntitySchema::ROLE_TITLE => '"title": "Título atractivo y claro (Sentence Case)"',
            EntitySchema::ROLE_SLUG => '"slug": "slug-en-minusculas-separado-por-guiones"',
            EntitySchema::ROLE_SUMMARY => '"summary": "Resumen conciso (máximo 250 caracteres)"',
            EntitySchema::ROLE_BODY => '"body": "Cuerpo en Markdown con subtítulos ##, párrafos fluidos y ritmo variado"',
            EntitySchema::ROLE_META_TITLE => '"meta_title": "Meta Title SEO (50-60 caracteres)"',
            EntitySchema::ROLE_META_DESCRIPTION => '"meta_description": "Meta Description persuasiva (130-155 caracteres)"',
        ];

        $jsonLines = [];
        foreach ($schema->fieldRoles() as $role) {
            $jsonLines[] = '  '.($roleSpec[$role] ?? '"'.$role.'": "'.$schema->fields[$role]['label'].'"');
        }
        $jsonLines[] = '  "keywords": ["palabra clave 1", "palabra clave 2"]';
        $json = implode(",\n", $jsonLines);

        $hintLine = $hint !== '' ? "\nGuía editorial: {$hint}" : '';

        $user = <<<USER
Crea un {$schema->label} completo y profesional: {$schema->description}
Instrucción: "{$brief}"

Idioma: {$locale}
Mercado objetivo: {$market}{$hintLine}

INSTRUCCIONES DE RESPUESTA:
Devuelve ÚNICAMENTE un objeto JSON con esta estructura (respeta exactamente las claves):
{
{$json}
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Focused block-composition prompt for sectionable elements.
     *
     * Runs as its own call so small local models reliably emit the real block
     * sequence instead of burying it under a long article body.
     *
     * @param  array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>  $blockCatalog
     * @param  list<array{type: string, name: string, rationale: string}>  $recommendedBlocks
     * @param  array<string, mixed>  $dynamicData
     * @return array{system: string, user: string}
     */
    public function buildBlocksCompositionPrompt(
        string $brief,
        string $title,
        string $summary,
        string $locale,
        array $blockCatalog,
        array $recommendedBlocks,
        array $dynamicData,
    ): array {
        $system = $this->buildSystemPrompt($locale, 'es', 'block');

        $catalogText = $this->describeBlockCatalog($blockCatalog);
        $recommendedText = $this->describeRecommendedBlocks($recommendedBlocks);
        $dynamicText = $this->describeDynamicEntities($dynamicData);

        $user = <<<USER
Compón la SECUENCIA DE BLOQUES (maquetación) para este contenido:
Título: {$title}
Resumen: {$summary}
Brief: {$brief}

{$catalogText}

{$recommendedText}

{$dynamicText}

REGLAS:
- Devuelve solo los bloques necesarios para el contenido solicitado, en orden editorial coherente. No hay un mínimo de bloques.
- Usa SOLO tipos del catálogo anterior; nunca inventes tipos.
- En bloques dinámicos (gallery, widget_posts, widget_products, widget_form, charts) usa los id/slug REALES; si no hay ninguno, deja el campo vacío.
- Usa las IMÁGENES REALES disponibles en "image_url"/"bg_image_url" cuando el bloque admita imagen.
- Respeta las claves de cada bloque (title, content, items, cta_text, cta_url, question/answer...).
- Redacta el payload de cada bloque en {$locale}, sin texto de relleno.
- No añadas widgets, formularios, galerías o llamadas comerciales ajenas a la instrucción.
- No inventes estadísticas, porcentajes, precios, testimonios ni puntuaciones. charts/kpi requieren etiquetas y valores explícitos aportados por el usuario; sin datos omite esos bloques.
- Los valores por defecto del editor son ejemplos de maquetación, nunca datos reales ni texto que debas copiar.

Devuelve ÚNICAMENTE:
{ "sections": [ { "type": "<tipo del catálogo>", "data": { ...payload... } } ] }
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * @param  array<string, array{name: string, description: string, category: string, default_payload: array<string, mixed>}>  $blockCatalog
     */
    private function describeBlockCatalog(array $blockCatalog): string
    {
        if ($blockCatalog === []) {
            return '';
        }

        $lines = [];
        foreach ($blockCatalog as $blockType => $meta) {
            $keys = implode(', ', array_keys((array) ($meta['default_payload'] ?? [])));
            $lines[] = "- {$blockType} [{$meta['category']}]: {$meta['name']} — {$meta['description']} (claves: {$keys})";
        }

        return "CATÁLOGO DE BLOQUES DISPONIBLES (usa SOLO estos tipos):\n".implode("\n", $lines);
    }

    /**
     * @param  list<array{type: string, name: string, rationale: string}>  $recommendedBlocks
     */
    private function describeRecommendedBlocks(array $recommendedBlocks): string
    {
        if ($recommendedBlocks === []) {
            return '';
        }

        $lines = array_map(
            static fn (array $block): string => "- {$block['type']}: {$block['rationale']}",
            $recommendedBlocks
        );

        return "SECUENCIA SUGERIDA (adáptala al brief):\n".implode("\n", $lines);
    }

    /**
     * Human-readable listing of the real entities available for dynamic blocks.
     *
     * @param  array<string, mixed>  $dynamicData
     */
    private function describeDynamicEntities(array $dynamicData): string
    {
        $lines = [];

        $albums = (array) ($dynamicData['gallery_albums'] ?? []);
        if ($albums !== []) {
            $lines[] = 'ÁLBUMES DE GALERÍA (usa el slug en "album_slug" del bloque gallery):';
            foreach (array_slice($albums, 0, 8) as $album) {
                $count = (int) ($album['image_count'] ?? 0);
                $lines[] = '  - slug: '.($album['slug'] ?? '').' | '.($album['title'] ?? '').($count > 0 ? " ({$count} fotos)" : '');
            }
        }

        $images = (array) ($dynamicData['gallery_images'] ?? []);
        if ($images !== []) {
            $lines[] = 'IMÁGENES REALES DISPONIBLES (úsalas tal cual en "image_url"/"bg_image_url"):';
            foreach (array_slice($images, 0, 6) as $image) {
                if (! empty($image['url'])) {
                    $lines[] = '  - '.$image['url'];
                }
            }
        }

        $forms = (array) ($dynamicData['forms'] ?? []);
        if ($forms !== []) {
            $lines[] = 'FORMULARIOS (usa el slug en "form_slug" del bloque widget_form):';
            foreach (array_slice($forms, 0, 6) as $form) {
                $lines[] = '  - slug: '.($form['slug'] ?? '').' | '.($form['name'] ?? '');
            }
        }

        $blogCategories = (array) ($dynamicData['blog_categories'] ?? []);
        if ($blogCategories !== []) {
            $lines[] = 'CATEGORÍAS DE BLOG (usa el slug en "category" del bloque widget_posts):';
            foreach (array_slice($blogCategories, 0, 8) as $category) {
                $lines[] = '  - slug: '.($category['slug'] ?? '').' | '.($category['name'] ?? '');
            }
        }

        $posts = (array) ($dynamicData['blog_posts'] ?? []);
        if ($posts !== []) {
            $lines[] = 'ARTÍCULOS DE BLOG (opcional "post_id"):';
            foreach (array_slice($posts, 0, 6) as $post) {
                $lines[] = '  - id: '.($post['id'] ?? '').' | '.($post['title'] ?? '');
            }
        }

        $shopCategories = (array) ($dynamicData['shop_categories'] ?? []);
        if ($shopCategories !== []) {
            $lines[] = 'CATEGORÍAS DE TIENDA (usa el slug en "category" del bloque widget_products):';
            foreach (array_slice($shopCategories, 0, 8) as $category) {
                $lines[] = '  - slug: '.($category['slug'] ?? '').' | '.($category['name'] ?? '');
            }
        }

        $products = (array) ($dynamicData['shop_products'] ?? []);
        if ($products !== []) {
            $lines[] = 'PRODUCTOS (opcional "product_id"):';
            foreach (array_slice($products, 0, 6) as $product) {
                $lines[] = '  - id: '.($product['id'] ?? '').' | '.($product['name'] ?? '');
            }
        }

        $chartTypes = (array) ($dynamicData['charts_types'] ?? []);
        if ($chartTypes !== []) {
            $keys = array_map(static fn (mixed $c): string => is_array($c) ? (string) ($c['key'] ?? '') : '', $chartTypes);
            $lines[] = 'TIPOS DE GRÁFICA (chart_type): '.implode(', ', array_filter($keys));
        }

        if ($lines === []) {
            return '';
        }

        return "ENTIDADES REALES DISPONIBLES (referéncialas por id/slug; NUNCA inventes enlaces):\n".implode("\n", $lines);
    }

    /**
     * Builds prompt for proofreading and improving existing text.
     *
     * @return array{system: string, user: string}
     */
    public function buildProofreadPrompt(string $content, string $locale = 'es', string $action = 'improve'): array
    {
        $system = $this->buildSystemPrompt($locale, 'es', 'proofread');

        $user = <<<USER
Revisa, corrige y mejora el siguiente texto manteniendo su sentido original pero elevando su precisión léxica, ritmo y calidad editorial.

Texto a revisar:
"""
{$content}
"""

Devuelve ÚNICAMENTE un objeto JSON con:
{
  "original": "...",
  "improved": "...",
  "changes": ["Descripción del cambio 1", "Descripción del cambio 2"]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Builds prompt for humanizing AI text.
     *
     * @return array{system: string, user: string}
     */
    public function buildHumanizePrompt(string $content, string $locale = 'es', string $style = 'periodistico'): array
    {
        $system = $this->buildSystemPrompt($locale, 'es', 'humanize');

        $user = <<<USER
Humaniza el siguiente texto eliminando cualquier rastro de redacción artificial o clichés robóticos. Aplica variabilidad en la longitud de las oraciones y naturalidad orgánica.

Texto:
"""
{$content}
"""

Devuelve ÚNICAMENTE un objeto JSON con:
{
  "original": "...",
  "humanized": "...",
  "cliches_removed": ["cliché eliminado 1", "cliché eliminado 2"]
}
USER;

        return ['system' => $system, 'user' => $user];
    }

    /**
     * Builds prompt for generating SEO metadata from content.
     *
     * @return array{system: string, user: string}
     */
    public function buildSeoPrompt(string $title, string $content, string $locale = 'es'): array
    {
        $system = $this->buildSystemPrompt($locale, 'es', 'seo');

        $user = <<<USER
Genera metadatos SEO de alto CTR para el siguiente artículo:

Título: "{$title}"
Contenido de muestra:
"""
{$content}
"""

Devuelve ÚNICAMENTE un objeto JSON:
{
  "meta_title": "Título SEO optimizado de 50 a 60 caracteres",
  "meta_description": "Meta description persuasiva de 135 a 155 caracteres",
  "keywords": ["tag1", "tag2", "tag3", "tag4", "tag5"]
}
USER;

        return ['system' => $system, 'user' => $user];
    }
}
