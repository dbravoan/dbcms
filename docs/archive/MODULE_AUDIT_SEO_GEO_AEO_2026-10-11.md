# Auditoría funcional SEO, GEO y AEO — 11 de octubre de 2026

> Historical evidence. This records the behavior and checks of its stated date; current contracts live in [Core and extensions](../../specs/CORE_AND_EXTENSIONS.md) and [Testing](../TESTING.md).

Estado: auditoría de código, capturas públicas y correcciones verificadas en contenedores aislados. Captura del runtime `http://localhost:8080`, lectura de código y contraste con fuentes primarias. Las publicaciones de fixtures y su aprobación las realiza el coordinador mediante la interfaz. Este informe no atribuye indexación ni citas reales a una prueba local.

## Criterios verificables

| Superficie | Criterio | Evidencia necesaria |
|---|---|---|
| Contenido público | HTTP 200, título/contenido coherentes, canonical único y navegable | HTML público y seguimiento de URLs |
| Privacidad editorial | Borradores y snapshots pendientes ausentes de HTML público, sitemap, RSS, búsqueda y corpus | Marcadores distintos para versión live, draft y privada |
| Internacionalización | Idioma activo y asignado al mercado; alternates recíprocos de contenido realmente disponible | HTML de cada alternativa y respuestas HTTP |
| Sitemap | XML válido, sólo URLs públicas/canónicas, prefijos localizados y mercados efectivos | Parse XML y GET de cada URL del fixture |
| JSON-LD | JSON válido, tipo/propiedades adecuados al contenido visible; serialización segura en HTML | Parse de JSON y DOM; validación externa posterior |
| RSS | XML válido, posts públicos y URLs que resuelven en su contexto | Parse RSS y GET de enlaces |
| llms.txt | Markdown con H1 y enlaces coherentes; sin datos privados; corpus fiel a lo publicado | Documento público y marcadores de fixtures |
| Invalidación | Edición pendiente conserva live; aprobación actualiza HTML y superficies de descubrimiento | Capturas antes/pendiente/después |
| Resultados reales | Indexación, tráfico y citas observadas en servicios de buscadores | Search Console y Bing Webmaster Tools del dominio publicado |

No se aplica una puntuación total, un porcentaje de citabilidad o una promesa de aparecer en respuestas de IA. Cantidades de palabras, longitud de títulos y presencia de encabezados son observaciones editoriales, no garantías de ranking ni de factualidad.

## Observaciones iniciales del runtime

La captura estructurada está en `storage/app/runtime-verification/module-audit-20261011/seo.json`. No contiene cookies, tokens ni cabeceras privadas.

- `/robots.txt` responde 200 y `Disallow: /`; el entorno local no productivo está bloqueado deliberadamente. Blog devuelve `noindex, nofollow`. Esto es correcto para pruebas y exige revisar la configuración del despliegue público.
- `/sitemap.xml` responde 200, parsea correctamente y anuncia cuatro segmentos. Los cuatro responden 200 y están vacíos antes de crear los fixtures. `/es/es/sitemap.xml` anuncia home/blog/tienda/galeria.
- `/llms.txt`, `/llms-full.txt` y sus variantes `/es/es/` responden 200. Sus encabezados y enlaces se registraron; el corpus inicial está vacío.
- `/feed.xml` es el RSS real y responde 200. `/rss.xml` y `/es/es/blog/rss.xml` no son rutas del contrato y responden 404.
- `/es/ca` responde 404 en la configuración inicial porque el mercado sólo tiene ES disponible. No se interpreta como fallo de traducción.
- Home muestra la página operativa de instalación sin canonical/JSON-LD; todavía no existe una landing editorial de home. Blog tiene canonical propio; búsqueda tiene canonical sin prefijo para el mercado/idioma por defecto, según la normalización de `LocalizationService`.

## Hallazgos

### SEO-01 — Alto, corregido: ruptura del script JSON-LD mediante contenido editorial

`resources/views/themes/components/seo-head.blade.php:86` y `:88` insertaban JSON crudo con `JSON_UNESCAPED_SLASHES`. Un título `</script><script>…</script>` cerraba el nodo JSON-LD y añadía un script ejecutable. La prueba sobre ambos fragmentos reales de Blade reprodujo RED: dos nodos script donde debía existir uno.

Se añadieron los flags `JSON_HEX_TAG`, `JSON_HEX_AMP`, `JSON_HEX_APOS` y `JSON_HEX_QUOT`. `tests/Regression/SeoJsonLdEscapingTest.php` comprueba DOM, ausencia de elementos inyectados y roundtrip íntegro de Unicode/metadatos: GREEN, 1 prueba y 9 assertions. La ejecución fue en Sail, sin aplicación/DB.

### SEO-02 — Alto, corregido: sitemap segmentado diverge del contrato público

`app/Services/Seo/SitemapIndexService.php:160` utiliza sólo el primer mercado de una página. Posts (`:185`) y productos (`:206`) fuerzan el mercado por defecto e ignoran sus asociaciones. Productos usan `shop` literal aunque ES usa `tienda`. Galerías (`:227`) usan slug raíz y `gallery` literal aunque ES usa `galeria`, y generan sólo el idioma de la petición. Las consultas no filtran módulos habilitados ni idiomas activos/asignados. Antes del cambio podía omitir alternativas válidas y anunciar URLs 404 o de módulos desactivados. Los segmentos ahora recorren mercados/idiomas disponibles, consultan el contrato común y generan URLs mediante `LocalizationService`, sin slugs raíz ni prefijos fijos. El índice deja de inventar `lastmod` con la hora de cada petición. Los filtros bajo el umbral de calidad no se anuncian como segmento vacío.

### SEO-03 — Medio, corregido: contenido global omitido y fallback de idioma en sitemap/GEO regional

`SitemapService.php:120` y `LlmsTxtGeneratorService.php:139` exigen una asociación explícita al mercado. El contrato de `app/Core/Traits/MarketScoped.php:40` permite contenido sin asociaciones como global. Las superficies regionales no aplican ese mismo contrato. Además usan la primera traducción cuando falta el idioma solicitado (`SitemapService.php:124`, `LlmsTxtGeneratorService.php:337`), lo que puede anunciar una URL inexistente o contenido de otro idioma. Los nuevos fixtures verifican contenido global, asociaciones explícitas y traducciones parciales. `PublicContentDiscoveryInterface` centraliza visibilidad por módulo, publicación, mercado e idioma para sitemap, RSS e índices/corpus semánticos. Las alternativas de sitemap respetan idiomas activos/asignados.

### SEO-04 — Medio, corregido y reproducido: RSS no respeta mercado ni idioma estricto

`app/Http/Controllers/Seo/RssController.php:42` toma todos los posts publicados, sin mercado; `:61` sustituye una traducción ausente por la primera y `:67` construye la URL con el mercado/idioma actual. Puede generar enlaces que el resolver público rechace. La caché incorpora locale, pero no mercado (`:87`). La prueba funcional reprodujo un post de otro mercado dentro del RSS. Ahora consulta el mismo contrato público, exige traducción exacta, usa URLs normalizadas y separa caché por mercado/idioma.

### GEO-01 — Medio, corregido para entidades editoriales: corpus global incompleto y galerías ausentes

`LlmsTxtGeneratorService.php:319` sólo incluye cuerpos de páginas/posts/productos cuando se solicita un mercado. El `/llms-full.txt` global es el índice más una cabecera vacía. Las galerías aparecen en el índice pero no tienen generación de cuerpo; Forms no tiene adaptador semántico. Se añadió corpus global por contextos disponibles y corpus de galerías con descripción/textos publicados de sus elementos. El precio de producto procede del mercado. Los formularios embebidos aportan el título del bloque; no se exportan respuestas ni se promete una representación exhaustiva de widgets. No existe una tubería RAG/embeddings verificada.

### GEO-02 — Medio, corregido sin DB: límite de caracteres estimados no se cumple

`ContextChunkingService.php:24` sólo corta entre párrafos. Un único párrafo de 10.000 caracteres genera un chunk de 2.500 tokens estimados aunque se solicita un máximo de 512. Ahora corta párrafos largos y limita también el solapamiento, conservando Unicode. `token_count` sigue siendo caracteres/4: no es un tokenizer ni garantiza límites de un modelo. Prueba pura RED→GREEN de texto largo sin pérdida y chunks con solapamiento. La documentación debe distinguir el helper disponible de RAG implementado.

### AEO-01 — Medio, corregido sin inventar resultados: métricas de citabilidad sin medición

`ContentQualityAuditorService.php:28` empieza las puntuaciones en 100 y aplica penalizaciones por longitudes, palabras y expresiones regex. `:108` presenta ese resultado como `llm_citability_index` porcentual. No mide citas, factualidad, consultas, ni rendimiento en buscadores. Las recomendaciones de densidad de keyword y ventaja universal de artículos largos tampoco están sustentadas por las fuentes primarias consultadas. Se conservan los campos heredados para compatibilidad, añadiendo `score_kind=editorial_heuristic`, una etiqueta explícita y `citations_measured=false`; la interfaz elimina porcentajes de eficacia y promesas de optimización/citas. Las recomendaciones ya no afirman que más palabras o una densidad universal mejoren ranking. Una prueba pura verifica esos límites semánticos.

La revisión final retiró también las traducciones residuales del drawer que prometían alcanzar100% o satisfacer directrices de citabilidad de ChatGPT/Gemini/Perplexity. ES/EN y las entradas catalanas ahora describen criterios editoriales y ausencia de resultados externos medidos, incluso cuando no quedan sugerencias. Regresión pura:2 pruebas/44 assertions; el test de ruta autenticada conserva los enlaces de edición y verifica el aviso explícito. El texto promocional estático del footer todavía menciona máxima citabilidad; queda pendiente de revisión editorial, separado del auditor corregido.

### DOC-01 — Medio, corregido: llms.txt descrito como RFC y cobertura sobreafirmada

`docs/05_GEO_AND_SEO_ENGINEERING.md:8` llama a llms.txt “RFC standard”. La fuente original se presenta como propuesta; su versión actual es v2, modificada el 10-08-2026. Los modelos tampoco implementan todos una representación conectada mediante el generador citado. `docs/05_GEO_AND_SEO_ENGINEERING.md` ahora distingue propuesta, cobertura por entidad, estimación de tokens y ausencia de una tubería RAG/citas verificadas.

### SEO-05 — Alto, corregido: rutas y disponibilidad internacional incoherentes

Con un mercado y dos idiomas, el generador emitía `/es/{slug}` y `/en/{slug}` pero la ruta de dos prefijos consumía el slug como locale y devolvía 404. La captura inicial de Page2 reprodujo esa incoherencia. Se restringió el segmento locale a códigos configurados o la sintaxis de idioma/región; el middleware continúa validando existencia, actividad y asociación. El patrón admite idiomas estándar añadidos después de arrancar la aplicación, evitando una regresión en procesos persistentes/tests.

Gallery mostraba álbumes de mercados no asignados y usaba el prefijo del idioma actual en todas sus alternativas. El front ahora exige mercado y traducción exacta; `buildHreflangCluster` recibe opcionalmente la entidad, filtra mercados asignados e idiomas activos y localiza cada prefijo. Page también sustituía un slug de otro idioma por contenido del locale actual: prueba RED 200 donde debía ser 404, corregida retirando ese fallback. Home sólo puede elegir una página con traducción del idioma solicitado.

El texto del formulario Gallery ahora describe sus páginas públicas localizadas además de la incrustación mediante bloque. El toast de filtros dice «preparado para indexación», sin afirmar que Google ha rastreado/indexado nada.

### SEO-06 — Alto, corregido y verificado por HTTP: resultados de otro mercado

El índice heredado de Post/Product almacena `market_code` vacío. SearchEngine lo interpretaba como disponibilidad global aunque la entidad tuviera mercados asignados. `/uk/en/search/qa-20261011?no_cache=1` anunciaba Post3 con URL ES; el Post correspondiente en UK respondía404. La prueba reprodujo un total/faceta/hit UK donde debía haber cero.

Ahora los queries FULLTEXT, fallback y sugerencias se restringen por el contrato público live antes de contar, obtener facetas o paginar. Se respetan scopes propios del módulo, incluyendo disponibilidad del pivot de Product. Los resultados globales generan URL canónica para el mercado solicitado. El arreglo funciona con índices existentes, sin modificar/reindexar la DB runtime. HTTP tras el arreglo: ES2 resultados, EN1 y UK0. Los fixtures de pruebas que insertaban documentos sin entidad ahora usan entidades publicadas reales; no se añadieron bypasses.

### SEO-07 — Medio, corregido: marcado genérico en artículos y galerías

Los HTML iniciales exponían sólo WebPage/BreadcrumbList. Los builders de cada módulo ahora entregan datos publicados a SeoMetaService/JsonLdSchemaService: Article (o BlogPosting/NewsArticle configurado), headline, fechas disponibles y nombre público del autor; ImageGallery y sus ImageObject existentes con URL/caption. No se inventan rating, ofertas ni publisher. La regresión de ruta real fue RED por entidad ausente, después GREEN; captures HTTP con `no_cache=1` verifican grafos ES/EN. Ese flag evita atribuir a una invalidación editorial el cambio de plantilla/código.

### UX-01 — Medio, observado y fuera del arreglo de SEO

El tema db-cyber, sin menús configurados, anuncia fallback `sobre-nosotros`, `servicios`, `contacto`, `privacidad` y `terminos`; las cinco URLs del fixture responden404. Debe configurarse navegación/contenido o condicionar enlaces a destinos existentes. El footer no productivo muestra Adminer DB a visitantes anónimos (localhost:8088); se verificó presencia del enlace, no acceso a credenciales/datos ni exposición remota. La plantilla lo oculta en producción. No se cambiaron navegación ni infraestructura en este bloque.

Page3, publicada con widgets y sin bloque de encabezado, responde200 en ES/EN y tiene canonical por idioma, pero el HTML no contiene H1. Su único control `range` carece de `id`, `aria-label` y `aria-labelledby`. Queda como seguimiento P2 de composición semántica/accesibilidad; no demuestra una penalización de ranking ni una puntuación global del sitio. La ausencia de H1 corresponde a este fixture de widgets, no a una prohibición técnica de añadir un encabezado.

## Verificación local y límites

- Proyecto Docker aislado `dbcms-seo-audit`, imagen CI existente y DB `testing` en una red independiente; no se ejecutaron suites sobre la DB runtime ni se modificaron sus registros/configuración.
- Ronda SEO/GEO/Gallery: **40 pruebas, 258 assertions**. Ronda filtros/localización/descubrimiento y regresiones puras: **28 pruebas, 1652 assertions**. Estas rondas comparten algunas pruebas; no son un total acumulable.
- Última ronda Search/MCP/discovery: **26 pruebas, 174 assertions**; ronda de schemas/rutas Gallery: **9 pruebas, 76 assertions**. Se verifican entidades reales publicadas y el aislamiento de totales, facetas y sugerencias, sin mocks de disponibilidad. Full CI final lo coordina el agente raíz tras congelar el código.
- PHPStan Level 8 focal: **0 errores**; Pint focal y `git diff --check` correctos. Regresiones JSON-LD y chunks incluyeron un RED real anterior al arreglo.
- Capturas Page2 ES/EN tras aprobación: HTTP200, canonical correspondiente y alternates recíprocos; página privada `verificacion-funcional-cms-2026-10-10` HTTP404. Root aprobó edición pública con Hero/Form/Gallery y cambiaron títulos/contenido. Los hashes HTML incluyen elementos variables y las correcciones de generadores fueron concurrentes: por sí solos no demuestran una invalidación controlada.
- Se alternaron URLs ES/EN y cabeceras opuestas Accept-Language/X-Market de visitantes anónimos: HTML y canonical conservan el contexto explícito; UK sigue404. No equivale a una prueba de sesión autenticada/cache privada.
- El filtro editorial con menos de tres resultados responde `noindex, nofollow` y queda fuera del sitemap/llms por el gate común. El entorno local también bloquea indexación por configuración, por lo que esa respuesta local no equivale a validación productiva del crawler.
- Los 404 iniciales de Post ES y Gallery EN correspondían a pérdida de traducciones en campos ocultos de formularios; el coordinador corrigió esos adapters y restauró/aprobó ambos fixtures por UI. Las capturas finales verifican200 en ES/EN, Article/ImageGallery en JSON-LD y404 para Gallery en UK. No se atribuyen a rutas ni a ausencia de soporte de traducción.
- Sitemap final: XML válido, Page2/Page3/Post/Gallery con ambos idiomas y Product ES; la página privada no aparece. Corpus regional y global contienen los cuerpos públicos; el RSS incluye Post ES con URL pública. El corpus UK no contiene el Post ni la Gallery restringidos a ES; la categoría global sí puede aparecer según sus asociaciones.

Pendiente en un dominio público: HTTPS y canonical del host definitivo; Rich Results Test/Schema validator; Search Console URL Inspection y sitemaps; Bing Webmaster Tools e informe AI Performance; política robots/crawlers/snippets. No se enviaron páginas locales ni contenido privado a validadores externos y no se consultó ningún LLM externo. `llms.txt` es una propuesta para consumidores compatibles; Google declara que no lo usa para mejorar visibilidad ni exige ese archivo. No existe una puntuación universal verificable de SEO/GEO/AEO.

## Fuentes primarias consultadas

- [Google: datos estructurados](https://developers.google.com/search/docs/appearance/structured-data/intro-structured-data): el marcado debe describir información visible; schema.org y elegibilidad para resultados enriquecidos tienen requisitos distintos.
- [Google: versiones localizadas](https://developers.google.com/search/docs/specialty/international/localized-versions): alternates completos/recíprocos y URLs válidas.
- [Google: sitemaps](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap): formato, URLs canónicas y límites de publicación.
- [Google: funciones generativas](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide): no exige archivos llms.txt ni marcado especial para aparecer en sus funciones de IA.
- [Schema.org: ImageGallery](https://schema.org/ImageGallery): semántica del tipo, independiente de resultados enriquecidos soportados por Google.
- [llms.txt: propuesta v2](https://llmstxt.org/): H1 requerido, enlaces y texto Markdown; recomienda variantes Markdown y relaciones de descubrimiento, sin convertirse en control de acceso.
- [Bing: directrices](https://www.bing.com/webmasters/help/bing-webmaster-guidelines-30fba23a) y [AI Performance](https://blogs.bing.com/webmaster/2026/2/Introducing-AI-Performance-in-Bing-Webmaster-Tools-Public-Preview/): visibilidad y citas deben observarse en datos del buscador; sus métricas no equivalen a una puntuación de autoridad.
