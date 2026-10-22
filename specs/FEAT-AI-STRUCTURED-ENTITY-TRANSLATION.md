# Feature Specification: Traducción IA de entidades conservando su estructura

> **Spec ID**: `FEAT-AI-STRUCTURED-ENTITY-TRANSLATION`  
> **Status**: IMPLEMENTED (contraste estático; checklist de aceptación, no ejecución actual)
> **Author**: `SpecArchitect` Agent  

---

## 1. Domain Objective & Context

Permitir traducir el contenido de una página, artículo o producto a un idioma activo desde su editor, usando el proveedor de texto disponible para el actor. El resultado es una propuesta revisable: no guarda, publica ni modifica la entidad por sí mismo. El usuario aplica la propuesta al idioma destino del formulario y utiliza después el guardado editorial existente.

La operación se comparte entre backoffice, cola y una herramienta MCP concreta mediante el catálogo y ejecutor de capacidades. No exige habilitar el planificador de instrucciones libres. El propietario de cada módulo declara sus campos y bloques; el asistente no conoce modelos específicos ni duplica sus servicios de persistencia.

### Anclas verificadas del repositorio

- `EntitySchemaRegistryInterface` descubre esquemas registrados por módulos. `EntitySchema.fields` asocia roles semánticos con inputs localizados `translations[{locale}][...]`; `permission` declara la autorización de la entidad.
- Página: `title`, `meta_title`, `meta_description`; artículo: además `summary` y `body` cuyo input es `content`; producto: título cuyo input es `name`, resumen y cuerpo cuyo input es `description`.
- `slug` y `schema_type` aparecen también en los esquemas, pero no son contenido traducible automáticamente. Se conservan los valores existentes del destino. Para un destino nuevo, el editor puede preparar un slug mediante su generador existente y presentarlo para revisión, sin aceptar rutas propuestas por el modelo.
- Los tres tipos son `sectionable`. El editor serializa `sections_json` por idioma. Una sección puede contener `columns: [{id, blocks: [...]}]`; se conservan orden, IDs y cardinalidades en todos los niveles.
- `BlockRegistryInterface` descubre bloques activos. Su interfaz base no cambia: el contrato opcional core `TranslatableBlockInterface::getTranslationPaths()` declara rutas textuales desde el bloque propietario. El consumidor consulta sólo `getActiveBlocks()`, sin dependencia de los módulos respecto a clases IA y sin inferir que toda cadena es texto editorial.
- `locale-tabs`, `market-picker` y el editor de bloques publican `window.cmsContext`, `cms-locale-switched` y `cms-markets-changed`. Los controles de idioma y mercado permanecen en el editor de entidad.
- La operación reutiliza `POST /<ADMIN_PATH>/ai-assistant/generate/async` y `GET /<ADMIN_PATH>/ai-assistant/generate/tasks/{task}`. La ruta síncrona permanece compatible.

### Alcance inicial y extensibilidad

Los editores de páginas, artículos y productos ofrecen la traducción completa. Otros módulos pueden incorporarla registrando un esquema con inputs localizados, permiso de edición y rutas textuales de sus bloques. Un esquema de contactos, pedidos u otra entidad sin traducciones localizadas no obtiene esta acción por el mero hecho de tener un rol `title` o `body`.

## 2. Algebraic Data Types & DTO Contracts

Los límites de dominio se expresan mediante DTOs inmutables `readonly` y un contrato de servicio en el módulo IA. Los nombres concretos pueden adaptarse a convenciones existentes, manteniendo el contrato de datos descrito aquí.

```php
namespace Modules\AiAssistant\DTOs;

final readonly class EntityTranslationRequest
{
    /**
     * @param list<int> $marketIds
     * @param array<string, string> $fields Roles definidos por el esquema.
     * @param list<array<string, mixed>> $sections Snapshot del idioma origen.
     */
    public function __construct(
        public string $entityType,
        public string $sourceLocale,
        public string $targetLocale,
        public array $marketIds,
        public array $fields,
        public array $sections,
        public ?int $entityId = null,
    ) {}
}
```

### Petición aditiva de generación

- `action: "translate_entity"`.
- `entity_type`: tipo registrado, con inputs localizados.
- `entity_id`: opcional; creación sin ID usa el contenido actual del formulario. Un ID requiere acceso real al recurso.
- `source_locale`, `target_locale`: idiomas activos distintos.
- `editor_market_ids`: lista explícita de mercados activos del editor; se utiliza la unión de sus idiomas permitidos, como las pestañas existentes.
- `fields`: mapa de roles textuales admitidos por el esquema. No incluye valores técnicos o privados.
- `sections`: lista de secciones del origen, incluyendo estructura y valores protegidos para reconstruir la propuesta.
- `text_provider` / `text_model`: opciones existentes, validadas contra disponibilidad. No se cambia proveedor silenciosamente.

El servidor calcula el hash del snapshot aceptado; un hash enviado por el modelo o por el cliente no concede autoridad. La API limita bytes totales, profundidad, número de bloques y número de unidades de traducción antes de llamar al proveedor.

### Resultado de propuesta

```json
{
  "success": true,
  "action": "translate_entity",
  "entity_type": "post",
  "source_locale": "es",
  "target_locale": "en",
  "source_fingerprint": "sha256 del snapshot validado",
  "fields": {"title": "Translated title", "summary": "Translated summary"},
  "sections": [],
  "protected_block_types": [],
  "resolved_context": {"locale": "en", "market_ids": [1]},
  "provider": "gemini",
  "model": "modelo configurado"
}
```

`sections` es una copia reconstruida por el CMS, con el mismo árbol que el origen. El modelo devuelve exclusivamente un mapa de identificadores internos de unidades a texto traducido. No devuelve el árbol de entidad, claves de negocio, permisos ni instrucciones de aplicación.

La respuesta usa los errores comunes: `needs_input`, `denied`, `unavailable`, `conflict` o `failed`, con mensaje legible. El frontend no interpreta un estado de error o aclaración como generación completada. El polling conserva propiedad de tarea y termina sin aplicar contenido en esos estados.

### Declaración de texto de bloques

Cada definición de bloque puede implementar el contrato core opcional `TranslatableBlockInterface` para exponer rutas textuales dot-path, con `*` para elementos existentes. El consumidor preserva segmentos de HTML/Markdown y sólo cambia texto permitido. Las definiciones de los paquetes y módulos son la única fuente de rutas; no existe un registro paralelo de tipos en IA. No se extiende obligatoriamente `BlockTypeInterface` ni se exige a los módulos una dependencia de IA. Rutas ausentes, desconocidas o de tipo no textual se conservan sin enviarse como unidades de traducción. Ejemplos constatados:

| Bloque | Rutas textuales | Valores que permanecen |
| --- | --- | --- |
| Hero | `title`, `content`, `cta_text`, `secondary_cta_text`, `badge` | URLs, imágenes, vídeo |
| Texto enriquecido | `title`, `content` | `format`, enlaces, código y estructura |
| FAQ | `title`, `items.*.question`, `items.*.answer` | Orden y cantidad de preguntas |
| Características | `title`, `items.*.title`, `items.*.description`; ruta `items.*.desc` cuando existe | Iconos y configuración de rejilla |
| CTA | `title`, `content`, `cta_text` | `cta_url` |
| Antes/después | `title`, `before_label`, `after_label` | Ambas imágenes |
| Vídeo | `title`, `caption` | URL, póster y reproducción |
| Carrusel | `title`, `slides.*.title`, `slides.*.subtitle`, `slides.*.cta_text` | Imágenes, enlaces, temporización y controles |
| Testimonios | `title`, `items.*.quote`, `items.*.role` | Autor, avatar e identidad |
| Precios | `title`, `items.*.name`, `items.*.period`, `items.*.features.*`, `items.*.cta_text` | Precio, URL y estructura de planes |
| KPI | `title`, `items.*.label` | `items.*.value` |
| ROI | `title`, `description` | Importes, moneda y valores de cálculo |
| Búsqueda | `title`, `placeholder` | Tamaño y opciones técnicas |
| Código incrustado | `title` | Todo `content`, incluidos HTML, scripts e iframes |
| Blog, Shop y Gallery widgets | `title`, registrado por el módulo | Categoría, post/product ID, album slug, límites y consultas |
| Forms widget | `title`, `button_text`, registrado por Forms | `form_slug` y campos de formulario resueltos dinámicamente |
| Charts | `title`, `items.*.label`, `donut_label`, `donut_subtext`, `metric_label`, `metric_description`, registrado por Charts | Valores, unidades, porcentajes, tipo, colores y series numéricas |

No se traduce recursivamente cualquier clave llamada `content`: el tipo de bloque y su declaración gobiernan cada ruta. Para HTML se separan segmentos textuales y literales protegidos, y se reconstruye la cadena original cambiando únicamente el texto permitido; etiquetas, atributos, clases, estilos, enlaces y atributos de eventos no se aceptan del modelo. Se conservan íntegros los fragmentos `script`, `style`, `pre` y `code`. Para Markdown se preservan delimitadores estructurales, destinos de enlaces y segmentos de código; una salida que altera esa estructura se rechaza.

## 3. Formal Invariants (Hoare Logic: P {Q} R)

### Preconditions (P)

1. Actor autenticado y vigente, módulo IA y módulo propietario activos.
2. Permiso `use:ai` y permiso de modificación declarado por el esquema. Un cliente OAuth requiere además los scopes del transporte; una sesión web no recibe scopes ficticios.
3. Si hay recurso persistido, autorización sobre ese recurso antes de lectura sensible o llamada remota.
4. Idiomas origen/destino distintos, activos y compatibles con los mercados seleccionados. Ningún mercado o idioma se inventa a partir del texto del modelo.
5. Campos son roles textuales localizados conocidos; estructuras y límites válidos. No existe fuente implícita mediante fallback a otro idioma.
6. Proveedor y modelo de texto configurados, compatibles y disponibles; credenciales nunca entran al prompt, respuesta o reporte.

### Processing (Q)

1. Validar contexto y autorización por el ejecutor común antes de inferencia.
2. Derivar unidades exclusivamente mediante el esquema y las declaraciones de bloques; tratar su contenido como datos no confiables, no instrucciones.
3. Dar al modelo instrucciones fijas y una lista acotada de unidades. No otorgar herramientas de persistencia ni autoridad editorial.
4. Exigir exactamente los identificadores de unidad esperados, sin claves extra, pérdidas, duplicaciones, nuevos bloques ni cambios de tipo.
5. Reconstruir sobre una copia del snapshot del CMS. Comparar la estructura y todos los valores protegidos con el origen; rechazar toda alteración.
6. Guardar sólo el estado/resultados de la tarea. Ninguna transacción local abarca llamadas HTTP.
7. Mostrar la propuesta y el destino. Aplicar requiere decisión del humano; si el destino ya contiene texto o bloques, confirmar expresamente su sustitución en el formulario.
8. Antes de aplicar, comparar origen, destino y mercados actuales con los snapshots de inicio. Ante cambio concurrente, detener y pedir nueva generación/revisión.

### Postconditions (R)

- Origen idéntico; otros idiomas y mercados idénticos; publicación y recursos multimedia idénticos.
- Estructura completa conservada: IDs, tipos, orden, cantidad de secciones, columnas, bloques e ítems, configuración y referencias.
- No hay escrituras de entidad por generación, polling ni aprobación de preview. La propuesta vive sólo en el formulario hasta el guardado normal.
- El usuario puede revisar y corregir el idioma destino. El guardado invoca sus servicios y permisos actuales, incluida revisión de contenido publicado, invalidación de cachés y generación de índices.
- El modelo no puede fabricar aprobación con `confirm: true`, seleccionar un recurso distinto ni escribir en aplicaciones externas.

## 4. REST API & Passport OAuth2 Contract

Se extienden las operaciones existentes de generación sin cambiar acciones ni formatos antiguos. Los controladores delegan en servicios y el ejecutor; jobs y MCP invocan el mismo handler.

- Web: `POST /<ADMIN_PATH>/ai-assistant/generate/async`, con autenticación de sesión, CSRF y permiso `use:ai`; valida adicionalmente permiso y recurso de entidad. Devuelve referencia de tarea existente y estado pendiente.
- Web: `GET /<ADMIN_PATH>/ai-assistant/generate/tasks/{task}`; sólo propietario autorizado. La respuesta completada incluye la propuesta descrita en §2.
- Síncrona: `POST /<ADMIN_PATH>/ai-assistant/generate`, misma decisión de autorización y mismo efecto sin persistencia.
- API: `POST /api/ai-assistant/translate-entity`, respuesta síncrona con la propuesta dentro de `data`; exige OAuth y los scopes de escritura correspondientes además de permisos.
- MCP: `cms_ai_translate_entity`, entrada equivalente; actor, scopes y ejecución común. No obtiene vía alternativa de guardado. Puede devolver preview sin requerir modelo de planificación.
- OAuth: se conserva el mapa de scopes tipados vigente del transporte común; no se añaden cadenas arbitrarias de scope a una sesión web.
- Fallos: `401` no autenticado; `403` autorización del transporte; `422` validación de la petición. Los errores de dominio, disponibilidad y proveedor siguen los resultados JSON del ejecutor y estados de tarea existentes. El conflicto de snapshot se detecta en el formulario antes de aplicar; esta API de propuesta no incorpora un guardado con control de versión.

### Compatibilidad y permisos

El contrato canónico usa `text_provider` y `text_model`; el DTO también admite `provider` y `model` como alternativas. No se deduce de ello compatibilidad con otros payloads históricos. Los roles personalizados no reciben nuevos permisos automáticamente. La herramienta es visible sólo con módulo y actor autorizados; la visibilidad del botón no sustituye autorización del backend. La aplicación de preview tampoco envía un guardado automático escondido.

## 5. Generative & Traditional SEO Specification

Se traducen títulos, resúmenes y metadatos declarados junto al contenido; se conserva Schema.org y el tipo editorial. No se prometen calidad perfecta, puntuaciones garantizadas ni indexación inmediata. Slugs y rutas merecen revisión explícita; no se reescriben enlaces de contenido al traducir.

Una propuesta no aparece en sitemap, RSS, `/llms.txt`, `/llms-full.txt` ni en caché pública. Tras guardar y publicar por el circuito existente, las traducciones elegibles se incorporan mediante los generadores y mecanismos de invalidación actuales. Alt/captions se traducen sólo si la declaración del propietario los marca textuales; no se regeneran imágenes por traducir.

## 6. Test Invariant Checklist (TDD)

- [ ] Contrato: petición y propuesta válidas para página, artículo y producto; roles del esquema se resuelven a los inputs reales del editor.
- [ ] Autorización: sin `use:ai`, sin permiso de entidad, recurso ajeno o actor revocado se rechaza antes de HTTP; web, MCP y job comparten decisión.
- [ ] Disponibilidad: módulo desactivado, modelo/proveedor ausente o modalidad incompatible no generan respuestas simuladas.
- [ ] Contexto: idioma inactivo, igual al origen, mercado inválido y destino no admitido por mercados se rechazan; no usa fallback de fuente.
- [ ] Estructura: hero + FAQ + columnas anidadas mantienen todas las IDs, orden, cardinalidades y tipos.
- [ ] Protección: URLs, archivos, colores, clases, estilos, iconos, precio, stock, SKU, referencias y código permanecen idénticos; valores técnicos desconocidos tampoco se traducen.
- [ ] Respuesta del proveedor: claves extra, falta de unidad, valor no textual, estructura inventada e instrucciones inyectadas se rechazan sin persistir.
- [ ] HTML: sólo cambia texto permitido; tags/attrs/enlaces/código se conservan, una respuesta con markup no produce atributos o HTML ejecutable nuevos.
- [ ] Markdown: enlaces, imágenes, código y estructura conservados; alteración inválida termina en fallo recuperable.
- [ ] Snapshot: editar origen, destino o mercados durante HTTP/polling invalida la aplicación; no se reemplaza contenido reciente.
- [ ] Preview: generación síncrona, asíncrona y MCP no modifican traducciones, bloques, publicaciones ni historial de revisiones.
- [ ] Destino con contenido: requiere confirmar sustitución en el formulario; cancelar conserva exactamente ese destino.
- [ ] Guardado: el usuario revisa y guarda mediante servicios actuales; contenido publicado mantiene circuito de revisión; origen sigue intacto.
- [ ] UI: páginas/artículos/productos, create/edit, temas `dbcms`/`termocms`; tarea completada/failed/needs_input; target explícito y mensajes legibles.
- [ ] SEO: metadatos traducidos, tipo Schema.org y URLs conservados; propuesta no invalida/indexa hasta guardado autorizado.
- [ ] Arquitectura: controladores delgados, DTOs readonly, contratos, registros de módulos y ausencia de queries en nuevas vistas.
- [ ] Gates: Pest con MySQL aislado en contenedor (nunca RefreshDatabase sobre desarrollo), PHPStan sin baseline, Pint, build y verificación funcional registrada.

## 7. Verificación de puntos de entrada IA y documentación

La entrega incluye una matriz trazable de cada punto de entrada visible: texto y corrección en campos, generación por bloque, secciones, SEO, imagen en bloque, biblioteca y portada, vídeo cuando exista proveedor compatible, y traducción estructurada. Para cada punto se registra módulo/editor, destino concreto, herencia de contexto, disponibilidad, autorización y prueba realizada.

Los controles de imagen deben conservar el destino de la selección original (portada, imagen de bloque, fondo o biblioteca), mostrar error de proveedor ausente y no anunciar éxito sin artefacto válido. Generar imagen no significa aplicarla ni publicarla. Las pruebas contractuales de proveedores se distinguen de las llamadas reales y no se afirma compatibilidad universal sin comprobar cada adaptador.

El runbook describe: configurar modelos por modalidad; traducción desde el idioma origen al destino; revisar preview; aplicar; revisar formulario; guardar; y circuito editorial si está publicado. El informe distingue verificado, no configurado, limitado por cuota y pendiente, sin incluir claves ni datos privados.
