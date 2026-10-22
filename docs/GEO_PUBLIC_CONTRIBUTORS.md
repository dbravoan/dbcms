# Contrato público de descubrimiento y corpus GEO

El índice y el corpus siguen siendo documentos públicos dinámicos. Una clave o un proveedor IA no es necesario para generarlos. Su disponibilidad no demuestra que un buscador los haya rastreado ni que un modelo haya citado el sitio.

## Extensiones de módulos

Un módulo puede implementar `App\Core\Content\Contracts\PublicSemanticContributorInterface` y registrar su instancia en `PublicSemanticRegistry` durante `boot()` de su proveedor. Declara una clave única, su módulo propietario y dos operaciones de lectura: `index(Market, locale)` y `corpus(Market, locale)`.

La instancia del registro se comparte en el CMS, sin dependencia del asistente IA ni MCP. Antes de invocar un contribuidor se comprueba que su módulo está activo, que el mercado está activo y que tiene habilitado el idioma solicitado. Su implementación debe seleccionar exclusivamente recursos públicos disponibles en ese contexto. Para entidades declaradas en el CMS debe reutilizar `PublicContentDiscoveryInterface::query()` y `url()`; para recursos propios, su servicio de dominio debe ofrecer la misma restricción pública.

El resultado debe contener texto editorial público y enlaces a fuentes canónicas. Nunca incluir credenciales, datos de clientes, resultados privados del backoffice, borradores ni instrucciones para el consumidor del documento. Un módulo que solo administra datos privados no debe registrar un contribuidor. El registro rechaza claves duplicadas.

El corpus existente de páginas, artículos, productos y galerías permanece compatible. El contrato permite añadir recursos nuevos sin editar el generador central.

## Bloques y widgets

`PublicWidgetSemanticContributorInterface` declara las claves del widget, su módulo y `text(payload, market, locale)`. Solo se invoca para secciones activas publicadas y con su contenido resuelto en el idioma actual. Un bloque dinámico comparte la selección pública y sus filtros entre HTML y texto semántico; no basta con copiar identificadores guardados en el bloque.

Los bloques canónicos de Blog, tienda y galería implementan ese contrato en sus propias clases; sus proveedores registran esas mismas instancias. Respetan selección por entidad, categoría por ID, álbum, destacado y límite. Los productos no añaden precios ocultos al corpus. El extractor de bloques incluye el título público de formularios, nunca envíos. Los bloques estáticos extraen texto, preguntas, respuestas, etiquetas y descripciones accesibles de su contenido publicado. Otras extensiones pueden contribuir cuando exista una representación pública apropiada; no se exporta por defecto su información administrativa.

## Fechas y publicaciones programadas

`PublicContentModifiedService` calcula la fecha conocida más reciente de la entidad, traducciones, secciones publicadas, traducciones de esas secciones, mercados y elementos `items` relacionados cuando
la entidad define esa relación. Las secciones en borrador no se consideran. Los servicios de mutación actualizan la entidad al cambiar relaciones de mercado; la aprobación conserva la versión pública hasta que se acepta el cambio.

HTML, sitemaps y corpus incluyen una huella de las publicaciones cuyo instante ya ha llegado. La clave cambia en la primera petición en la frontera temporal aunque no se haya escrito una fila ni ejecutado el planificador. La duración de la caché se limita a la siguiente publicación programada. Esto evita depender únicamente de la expiración general. El estado temporal se reutiliza dentro de cada segundo y versión de contenido, reduciendo consultas repetidas durante una petición.

`PublicDocumentCache` comparte la versión de visibilidad y el TTL de
`PageCacheService`. Un fallo al leer o escribir caché permite generar y devolver
el documento; un fallo del generador se propaga y no repite su ejecución. Las
rutas públicas las atienden acciones invocables en `Http/Controllers/Seo`.
El índice global es `/sitemap.xml`; no existe el alias `/sitemaps.xml`.

## Límites del sitemap

Cada documento hijo contiene como máximo 50.000 URLs y 50 MiB sin comprimir. El índice publica hijos numerados cuando un segmento supera el límite. Las rutas anteriores de segmento siguen devolviendo la primera parte; los rastreadores deben seguir el índice para obtener todas.

Los sitemaps regionales también pasan a un índice cuando necesitan varias partes, publicadas como `sitemap-part-N.xml` dentro de su contexto regional. Una entrada individual que exceda el máximo produce un fallo explícito en lugar de publicar XML fuera del contrato. Los parámetros internos `seo.sitemap.max_urls` y `max_bytes` permiten límites inferiores; nunca pueden elevar los máximos del protocolo.

Las comprobaciones locales verifican estructura, límites y disponibilidad pública. El dominio de producción, los accesos reales de rastreadores, los datos de buscadores y las citas externas deben verificarse con la configuración de ese sitio.
