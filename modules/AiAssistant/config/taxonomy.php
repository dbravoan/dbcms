<?php

declare(strict_types=1);

/**
 * AI assistant taxonomy configuration.
 *
 * The AI assistant does not hardcode any module: it discovers the entities
 * registered in the core EntitySchemaRegistry and exposes them. This file only
 * lets an operator restrict which of those entities are offered and add extra
 * editorial guidance per type.
 */
return [
    /*
     * null  => expose every entity registered by the active modules.
     * list  => restrict the assistant to these entity types.
     */
    'supported' => null,

    /*
     * Optional editorial guidance per entity type, appended to the prompt.
     */
    'hints' => [
        'post' => 'Enfoque periodístico y educativo, con subtítulos y ritmo variado.',
        'page' => 'Estructura la página en secciones claras orientadas a conversión.',
        'product' => 'Describe beneficios y casos de uso, no solo especificaciones técnicas.',
        'blog_category' => 'Descripción breve y útil para el lector, sin relleno.',
        'shop_category' => 'Descripción breve orientada a la intención de compra.',
        'gallery' => 'Título evocador y descripción que invite a explorar las imágenes.',
        'form' => 'Textos claros y directos, orientados a maximizar la conversión.',
    ],

    /*
     * Starter examples per entity type. The first one is used as the textarea
     * placeholder and all of them render as clickable chips under the brief.
     *
     * @var array<string, list<string>>
     */
    'examples' => [
        'post' => [
            'Guía práctica sobre caché en Laravel con ejemplos y errores comunes',
            'Opinión: arquitectura modular en un CMS multi-mercado',
        ],
        'page' => [
            'Landing de servicios de consultoría con planes y formulario de contacto',
            'Página "Sobre nosotros" con historia, equipo y valores',
        ],
        'product' => [
            'Zapatillas de trail para terreno técnico: usos, materiales y cuidados',
            'Suscripción mensual de café de especialidad con notas de cata',
        ],
        'blog_category' => [
            'Categoría "Tutoriales" para artículos prácticos paso a paso',
        ],
        'shop_category' => [
            'Categoría "Running" para calzado y accesorios de carrera',
        ],
        'gallery' => [
            'Álbum del evento de lanzamiento con las mejores fotos',
        ],
        'form' => [
            'Formulario de solicitud de presupuesto con nombre, email y mensaje',
        ],
    ],
];
