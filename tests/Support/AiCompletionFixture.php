<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\AiAssistant\Contracts\AiConfigBridgeInterface;

/** Deterministic provider responses for domain transformations; production always calls the adapter. */
final class AiCompletionFixture
{
    public static function configure(): void
    {
        $fixture = new self;
        Http::preventStrayRequests();
        Http::fake([
            'generativelanguage.googleapis.com/*:generateContent' => static fn (Request $request) => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => $fixture->responseContent((string) data_get($request->data(), 'contents.0.parts.0.text', ''))]]]]],
                'usageMetadata' => ['promptTokenCount' => 12, 'candidatesTokenCount' => 24],
            ]),
        ]);
        $bridge = app(AiConfigBridgeInterface::class);
        $bridge->saveProviderConfig('gemini', ['api_key' => 'fixture-key', 'model' => 'gemini-fixture']);
        $bridge->setDefaultProvider('gemini');
    }

    /**
     * Synthesizes realistic JSON response for mock/test execution based on requested action.
     */
    private function responseContent(string $userPrompt): string
    {
        if (str_contains($userPrompt, 'Genera un bloque de tipo')) {
            preg_match("/tipo '([^']+)'/", $userPrompt, $matches);
            $blockType = $matches[1] ?? 'hero';

            $mockData = match ($blockType) {
                'hero' => [
                    'title' => 'Impulsa tu arquitectura digital con dbCMS',
                    'content' => 'CMS desacoplado de alto rendimiento con inteligencia artificial integrada y soporte multilingüe.',
                    'cta_text' => 'Comenzar ahora',
                    'cta_url' => '/contacto',
                    'align' => 'center',
                ],
                'features' => [
                    'title' => 'Características diseñadas para escalar',
                    'items' => [
                        ['title' => 'Rendimiento Extremo', 'description' => 'Arquitectura optimizada en Laravel LTS con caché inteligente.', 'icon' => 'fa-solid fa-bolt'],
                        ['title' => 'Seguridad OAuth2', 'description' => 'Protección criptográfica mediante Laravel Passport para agentes y APIs.', 'icon' => 'fa-solid fa-shield-halved'],
                        ['title' => 'Model Context Protocol', 'description' => 'Integración nativa bidireccional con OpenCode, Cursor y cualquier cliente MCP.', 'icon' => 'fa-solid fa-network-wired'],
                    ],
                ],
                'faq' => [
                    'title' => 'Preguntas Frecuentes',
                    'items' => [
                        ['question' => '¿Cómo se conecta un agente externo?', 'answer' => 'A través de tokens seguros de Laravel Passport con los scopes correspondientes.'],
                        ['question' => '¿Es compatible con múltiples idiomas?', 'answer' => 'Sí, soporta mercados y locales dinámicos de forma nativa.'],
                    ],
                ],
                'cta' => [
                    'title' => '¿Listo para transformar tu flujo editorial?',
                    'content' => 'Únete a las empresas que gestionan contenidos a velocidad de código.',
                    'cta_text' => 'Solicitar Demostración',
                    'cta_url' => '/demo',
                ],
                'pricing' => [
                    'title' => 'Planes y Tarifas Flexibles',
                    'items' => [
                        ['name' => 'Starter', 'price' => '29€', 'period' => '/mes', 'features' => ['Hasta 5 sitios', 'API MCP básica'], 'cta_text' => 'Elegir Plan', 'cta_url' => '/planes', 'is_featured' => false],
                        ['name' => 'Pro Enterprise', 'price' => '99€', 'period' => '/mes', 'features' => ['Sitios ilimitados', 'Agentes autónomos', 'Soporte 24/7'], 'cta_text' => 'Comenzar Pro', 'cta_url' => '/planes', 'is_featured' => true],
                    ],
                ],
                'kpi' => [
                    'items' => [
                        ['value' => '99.9%', 'label' => 'Disponibilidad Garantizada', 'icon' => 'fa-solid fa-server'],
                        ['value' => '< 50ms', 'label' => 'Latencia Promedio', 'icon' => 'fa-solid fa-gauge-high'],
                        ['value' => '10k+', 'label' => 'Artículos Publicados', 'icon' => 'fa-solid fa-newspaper'],
                    ],
                ],
                default => [
                    'title' => 'Sección Generada con Asistente IA',
                    'description' => 'Contenido maquetado automáticamente con dbCMS.',
                ],
            };

            return $this->toJson([
                'type' => $blockType,
                'data' => $mockData,
                'reasoning' => 'Bloque generado con estructura equilibrada y copy persuasivo en tono periodístico.',
            ]);
        }

        if (str_contains($userPrompt, 'Redacta un artículo completo')) {
            return $this->toJson([
                'title' => 'Evolución de las arquitecturas modulares en sistemas de contenidos',
                'content' => "## La transformación del desarrollo web\n\nEl desarrollo de software corporativo exige flexibilidad. Cuando los equipos dependen de estructuras rígidas, la velocidad de entrega se desploma ante cualquier cambio regulatorio o técnico.\n\nPor esta razón, la transición hacia arquitecturas modulares desacopladas permite aislar responsabilidades sin perder la cohesión del sistema.\n\n## Ventajas estratégicas del desacoplamiento\n\nAl organizar las funcionalidades como módulos independientes con interfaces estrictas, el mantenimiento se simplifica notablemente.",
                'excerpt' => 'Análisis de cómo las arquitecturas modulares y el protocolo MCP transforman los sistemas de contenidos corporativos.',
                'meta_title' => 'Arquitecturas Modulares en CMS: Guía y Ventajas',
                'meta_description' => 'Descubre cómo las arquitecturas desacopladas optimizan la escalabilidad y reducen la deuda técnica en el desarrollo web moderno.',
                'keywords' => ['arquitectura modular', 'cms', 'laravel', 'mcp', 'desacoplamiento'],
                'recommended_blocks' => [
                    ['type' => 'hero', 'rationale' => 'Introducción visual del concepto.'],
                    ['type' => 'features', 'rationale' => 'Resumen de los pilares arquitectónicos.'],
                    ['type' => 'cta', 'rationale' => 'Invitación a explorar el repositorio.'],
                ],
            ]);
        }

        if (str_contains($userPrompt, 'Revisa, corrige y mejora')) {
            return $this->toJson([
                'original' => $userPrompt,
                'improved' => 'El desarrollo de software corporativo exige flexibilidad. Cuando los equipos dependen de estructuras rígidas, la velocidad de entrega se resiente ante cambios técnicos.',
                'changes' => [
                    'Sustituida frase pasiva por activa para mayor contundencia',
                    'Corregida concordancia y ritmo de la oración',
                ],
            ]);
        }

        if (str_contains($userPrompt, 'Humaniza el siguiente texto')) {
            return $this->toJson([
                'original' => $userPrompt,
                'humanized' => 'Hoy en día en el sector tecnológico, resulta determinante comprender la arquitectura del sistema. Con certeza, la modularidad ofrece múltiples opciones.',
                'cliches_removed' => [
                    'en el vertiginoso mundo de',
                    'juega un papel crucial',
                    'sin lugar a dudas',
                ],
            ]);
        }

        if (str_contains($userPrompt, 'Genera metadatos SEO')) {
            return $this->toJson([
                'meta_title' => 'Guía de Optimización SEO y Arquitectura Web',
                'meta_description' => 'Aprende las mejores prácticas de posicionamiento orgánico, indexación semántica y optimización para motores de búsqueda de IA.',
                'keywords' => ['seo', 'geo', 'optimizacion', 'indexacion', 'arquitectura'],
            ]);
        }

        if (str_contains($userPrompt, 'completo y profesional')) {
            return $this->toJson([
                'title' => 'Guía práctica de arquitectura modular para equipos de producto',
                'slug' => 'guia-practica-arquitectura-modular',
                'summary' => 'Una guía para diseñar sistemas de contenido modulares, mantenibles y listos para escalar entre mercados e idiomas.',
                'body' => "## Por qué modularizar\n\nLos equipos que trabajan con estructuras rígidas pierden velocidad ante cualquier cambio. Separar responsabilidades permite iterar sin romper el conjunto.\n\n## Cómo aplicarlo\n\nDefine interfaces estables entre módulos, aísla la lógica de dominio y deja que la presentación dependa de contratos claros.\n\n## Resultado\n\nUn sistema más predecible, más fácil de probar y más rápido de evolucionar.",
                'meta_title' => 'Arquitectura Modular: Guía Práctica para Producto',
                'meta_description' => 'Aprende a diseñar sistemas de contenido modulares y escalables entre mercados e idiomas con buenas prácticas de arquitectura.',
                'keywords' => ['arquitectura modular', 'cms', 'escalabilidad', 'multi-mercado'],
            ]);
        }

        if (str_contains($userPrompt, 'SECUENCIA DE BLOQUES')) {
            return $this->toJson([
                'title' => 'Guía práctica de arquitectura modular para equipos de producto',
                'summary' => 'Una guía para diseñar sistemas de contenido modulares, mantenibles y listos para escalar entre mercados e idiomas.',
                'content' => "## Por qué modularizar\n\nLos equipos que trabajan con estructuras rígidas pierden velocidad ante cualquier cambio. Separar responsabilidades permite iterar sin romper el conjunto.\n\n## Cómo aplicarlo\n\nDefine interfaces estables entre módulos, aísla la lógica de dominio y deja que la presentación dependa de contratos claros.\n\n## Resultado\n\nUn sistema más predecible, más fácil de probar y más rápido de evolucionar.",
                'meta_title' => 'Arquitectura Modular: Guía Práctica para Producto',
                'meta_description' => 'Aprende a diseñar sistemas de contenido modulares y escalables entre mercados e idiomas con buenas prácticas de arquitectura.',
                'keywords' => ['arquitectura modular', 'cms', 'escalabilidad', 'multi-mercado'],
                'sections' => [
                    [
                        'type' => 'hero',
                        'data' => [
                            'title' => 'Arquitectura modular para equipos de producto',
                            'content' => 'Diseña sistemas de contenido mantenibles y listos para escalar entre mercados e idiomas.',
                            'cta_text' => 'Solicitar información',
                            'cta_url' => '/contacto',
                            'badge' => 'Guía práctica',
                        ],
                    ],
                    [
                        'type' => 'features',
                        'data' => [
                            'title' => 'Pilares de una arquitectura modular',
                            'grid_columns' => '3',
                            'items' => [
                                ['icon' => '⚡', 'title' => 'Responsabilidades claras', 'description' => 'Cada módulo expone un contrato estable y aislado.'],
                                ['icon' => '🛡️', 'title' => 'Lógica de dominio', 'description' => 'La presentación depende de interfaces, no de implementaciones.'],
                                ['icon' => '🤖', 'title' => 'Escalado multi-mercado', 'description' => 'Contenido localizado sin duplicar estructura.'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'gallery',
                        'data' => [
                            'title' => 'Nuestros proyectos',
                            'album_slug' => 'album-inventado',
                            'grid_columns' => '3',
                            'limit' => 6,
                        ],
                    ],
                    [
                        'type' => 'faq',
                        'data' => [
                            'title' => 'Preguntas frecuentes',
                            'items' => [
                                ['question' => '¿Cuándo conviene modularizar?', 'answer' => 'Cuando el equipo crece y los cambios empiezan a romper partes no relacionadas.'],
                                ['question' => '¿Afecta al rendimiento?', 'answer' => 'No: separar responsabilidades facilita además el caché por capas.'],
                            ],
                        ],
                    ],
                    [
                        'type' => 'widget_form',
                        'data' => [
                            'title' => 'Solicita información',
                            'form_slug' => 'form-inventado',
                            'button_text' => 'Enviar',
                        ],
                    ],
                    [
                        'type' => 'cta',
                        'data' => [
                            'title' => '¿Listo para ordenar tu arquitectura?',
                            'content' => 'Hablemos de tu caso y definamos una ruta de modularización realista.',
                            'cta_text' => 'Contactar',
                            'cta_url' => '/contacto',
                        ],
                    ],
                ],
            ]);
        }

        return $this->toJson([
            'status' => 'success',
            'message' => 'Operación completada con éxito por el Asistente IA.',
        ]);
    }

    protected function toJson(mixed $data): string
    {
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }
}
