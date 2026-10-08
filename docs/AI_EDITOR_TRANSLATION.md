# Asistente IA en los editores y traducción de contenido

Guía del CMS local, actualizada el 11 de octubre de 2026. La IA prepara propuestas; el editor y sus servicios conservan la decisión de guardar y publicar. La disponibilidad depende de los módulos, permisos, conexiones y modelos configurados.

## Preparar la conexión

En la configuración del asistente, configura y verifica un proveedor y modelo de **texto** para redactar, corregir, generar SEO y traducir. Para imágenes o vídeo hace falta además una conexión que admita esa modalidad y un modelo compatible. Una prueba del catálogo o de texto no acredita generación de imágenes.

El usuario necesita permiso de uso de IA y los permisos del elemento que va a editar. Generar medios requiere también gestionar la biblioteca. La clave del proveedor se guarda mediante la configuración cifrada del CMS; no se introduce en el prompt, la documentación o una traducción.

En el asistente independiente puedes indicar idioma y mercado en la instrucción. Dentro de un editor, la IA hereda la pestaña de idioma activa y los mercados seleccionados. Si la instrucción contradice ese contexto, solicita aclaración antes de generar. No cambies idioma o mercados mientras se genera una propuesta: si el contexto cambia, hay que volver a generar o revisar.

## Crear o mejorar texto

1. Abre la página, artículo, producto u otra entidad compatible y selecciona su idioma y mercados.
2. Sitúa el cursor en el campo de texto que quieras completar y abre su botón de IA. Los campos técnicos, IDs, precios y URLs no son destinos de redacción.
3. Pide el texto o utiliza la corrección/mejora sobre el contenido existente.
4. Revisa la propuesta. Reemplaza o añade texto al campo seleccionado.
5. Guarda con la acción normal del formulario.

También puedes usar la acción de completar el formulario de creación cuando el módulo la ofrece. Los títulos, resúmenes, cuerpo y metadatos se asignan según el esquema declarado por la entidad; no se toma cualquier campo del formulario como texto editorial.

## Crear texto dentro de bloques y componer secciones

Para completar un título o cuerpo de un bloque, abre IA desde ese campo y aplica el texto al mismo destino. Esto conserva la configuración del bloque.

Para crear bloques completos, utiliza **Generar secciones** en el editor de bloques, elige el tipo cuando corresponda y describe lo necesario. Revisa la propuesta y decide si añadirla a las secciones o sustituir la composición actual. Las secciones heredan idioma y mercados del editor. Guarda después por el formulario habitual.

La propuesta no acredita la veracidad de cifras, testimonios o afirmaciones. Revisa fuentes y datos de negocio; el asistente no debe completar resultados con cifras de demostración. Las puntuaciones internas de contenido o SEO son heurísticas, no garantías de posicionamiento.

## Generar una imagen, portada o vídeo

En una portada, pulsa **Generar imagen con IA**. Para otra imagen, abre su selector de medios y pulsa el mismo botón. Puede ser la portada de un artículo/producto, una imagen de un bloque, una imagen de fondo o un recurso de la biblioteca. Describe la imagen y, cuando esté disponible, selecciona su proporción.

Revisa la vista previa y pulsa **Usar en este campo** cuando hayas abierto la generación desde el selector, o aplica como portada desde el editor. Una portada se asigna al campo de portada; una imagen de bloque se asigna al bloque desde el que abriste el asistente. El copiloto también puede ofrecer insertar un nuevo bloque de imagen o vídeo, o usar el resultado como portada si el formulario dispone de ella. Guarda después la entidad con su acción normal.

Generar medios puede crear un archivo en la biblioteca; **aplicarlo a una entidad y publicar esa entidad son acciones distintas**. No se reemplazan automáticamente todas las imágenes del contenido. Si el campo o bloque cambió o desapareció durante la generación, no apliques el resultado a otro destino.

Si no existe proveedor compatible o la cuenta carece de cuota, el CMS debe mostrar la indisponibilidad/error. Tener una clave de texto no significa disponer de imagen o vídeo. En la ronda de auditoría actual no hay un proveedor de imagen configurado; se distinguen las comprobaciones locales del destino y contratos simulados de una generación remota real.

## Traducir una página, artículo o producto conservando su estructura

La traducción usa el contenido actual del formulario, de modo que puedes revisar el origen antes de guardarlo. Debe existir texto en el idioma origen. Elige un idioma destino activo y permitido por los mercados del elemento; origen y destino tienen que ser distintos.

1. Abre **Traducir con IA** desde los controles de idioma del editor.
2. El origen se preselecciona desde la pestaña activa. En el diálogo puedes elegir origen y destino explícitamente entre los idiomas admitidos por los mercados del editor.
3. Pulsa **Generar traducción**. La operación funciona como tarea: muestra progreso y después la traducción o un error legible.
4. Revisa los títulos, resúmenes, contenido, metadatos y bloques traducidos. Los bloques, columnas e ítems mantienen su estructura.
5. Pulsa **Aplicar traducción**. Si ya hay contenido en el destino, marca la casilla **Reemplazar textos existentes del idioma de destino** para confirmar la sustitución. Cancelar conserva ese destino.
6. Revisa el idioma destino y guarda con la acción normal del editor. Para contenido publicado se mantiene su circuito de revisión existente.

La generación no guarda ni publica la traducción. Tampoco modifica el idioma origen, otros idiomas ni los mercados. Si editas origen, destino o mercados mientras llega la respuesta, el editor debe impedir aplicar una propuesta desactualizada.

### Qué se traduce y qué se conserva

| Se traduce | Se conserva |
| --- | --- |
| Título/nombre, resumen, cuerpo y metadatos SEO declarados por la entidad | IDs, publicación, categorías, autor, pertenencia a mercados |
| Texto declarado por cada bloque: encabezados, párrafos, botones, preguntas/respuestas y etiquetas | Orden y cantidad de secciones, columnas, bloques e ítems; tipos y configuración |
| Texto visible permitido en contenido enriquecido | Etiquetas y atributos HTML, enlaces, estilos, clases y código |
| Texto de bloques personalizados cuando su módulo registra rutas traducibles | Campos desconocidos o sin declaración; sus textos pueden quedar sin traducir y se avisa |

La traducción no cambia precios, stock, SKU, nombres de archivo, URLs de imágenes/vídeo, referencias ni código incrustado. No regenera imágenes. El slug existente y el tipo Schema.org no los decide el modelo. La propuesta conserva el slug actual del destino; si está vacío, complétalo o utiliza el generador del editor antes de guardar. Revisa también su unicidad y adecuación al idioma.

Los bloques de Blog, Shop y Gallery traducen sus títulos estáticos y conservan las referencias al contenido consultado. El bloque de Forms traduce su título y botón; los campos internos proceden del formulario asociado, sin reescribirlo. Charts traduce títulos y etiquetas, conservando series, valores, porcentajes, colores y unidades. Si un bloque personalizado no declara qué texto puede traducirse, se conserva íntegro y la propuesta muestra un aviso para revisarlo manualmente.

Las traducciones propuestas no aparecen en sitemap, RSS o superficies GEO. Se incorporan cuando el guardado y la publicación normales las hacen elegibles.

## Recuperar errores y tareas pendientes

- **Hace falta información**: completa o corrige la instrucción/contexto y genera de nuevo; no se aplica contenido incompleto.
- **Permiso o conexión insuficiente**: revisa permisos y configuración con el administrador; no hay una ruta alternativa que los omita.
- **Cuota agotada / HTTP 429**: conserva el contenido del formulario y espera a que la cuenta disponga de cuota. No se cambia de modelo o proveedor automáticamente.
- **Resultado remoto incierto**: consulta el estado de la tarea antes de repetir; evita duplicar una generación cuyo resultado no se ha confirmado.
- **Propuesta desactualizada**: revisa los cambios recientes y vuelve a preparar la traducción; no sobrescribas el destino modificado.

## Verificación y alcance

El diálogo usa los componentes compartidos del backoffice: selects, botones,
mensajes y vista previa adoptan automáticamente termoCMS o dbCMS. En móvil, los
idiomas se apilan y el contenido de la propuesta tiene desplazamiento propio.
La [revisión de temas](CMS_MODULE_FUNCTIONAL_AUDIT_2026-10-11.md#revisión-visual-de-termocms-y-dbcms)
describe las comprobaciones de presentación, separadas de las pruebas del proveedor.

El [informe funcional del 11 de octubre](CMS_MODULE_FUNCTIONAL_AUDIT_2026-10-11.md) diferencia inspección de UI/código, regresiones con proveedor simulado y pruebas con proveedor real. Gemini `gemini-2.5-flash` generó texto, bloques, corrección, humanización y metadatos en la ronda anterior, hasta recibir `HTTP 429 / RESOURCE_EXHAUSTED`. En esta nueva ronda se intentó una traducción real desde el editor de artículo: terminó con una excepción del proveedor y estado incierto, sin propuesta ni escritura del artículo. Su HTTP concreto no quedó registrado; no se atribuye automáticamente a la cuota anterior. Imagen y vídeo remotos quedan pendientes de conexiones compatibles.

Los contratos de traducción pasan con respuestas HTTP simuladas en páginas, artículos y productos, incluyendo permisos, scopes, tareas, estructura y ausencia de escritura durante la generación. Otras pruebas aplican la propuesta y guardan mediante el servicio propietario: al recargar conservan el idioma origen, IDs de secciones, columnas, enlaces, mercados y estado borrador. Se corrigió el servicio compartido que antes borraba y recreaba las secciones al guardar. Una referencia de sección ajena no modifica el otro elemento; las secciones omitidas se eliminan sin recrear las conservadas. La interfaz de los tres editores se ha comprobado en el navegador. Esto no acredita una traducción remota completada.

## API, MCP y extensión

La API ofrece `POST /api/ai-assistant/translate-entity` con `action: translate_entity`, `entity_type`, `entity_id` opcional, `source_locale`, `target_locale`, `editor_market_ids`, `fields` por roles y `sections` del origen. Usa OAuth con permiso de IA, permiso de edición de la entidad y scopes de escritura correspondientes. Una sesión web usa sus permisos habituales, sin scopes ficticios. MCP dispone de `cms_ai_translate_entity` en el mismo ejecutor; ambas entradas producen una propuesta, no guardan la entidad.

Los roles textuales permitidos son `title`, `summary`, `body`, `meta_title` y `meta_description` cuando el esquema de la entidad los declara como localizados. El resultado contiene `fields`, `sections`, `source_locale`, `target_locale`, `source_fingerprint`, `resolved_context` y `protected_block_types`. Las respuestas del modelo sólo aportan segmentos de texto identificados; el CMS reconstruye el documento original. La petición está limitada a 200 KB, 1.000 segmentos, profundidad 24 y 2.000 nodos de arrays.

Un bloque propietario puede implementar el contrato core opcional `TranslatableBlockInterface::getTranslationPaths()` para declarar rutas textuales, como `items.*.label`. Su módulo sigue independiente del módulo IA. Sólo se utilizan los bloques activos. Una nueva entidad declara sus campos en `EntitySchema`; para habilitar el diálogo en su editor debe aportar el mismo estado de traducciones, control de mercados y contrato de aplicación al formulario descrito en la especificación.

Entradas y extensión de módulos: [matriz de integración de los editores](AI_EDITOR_ENTRYPOINTS.md). Contrato y pruebas esperadas: [especificación de traducción estructurada](../specs/FEAT-AI-STRUCTURED-ENTITY-TRANSLATION.md). El cierre del informe registra 1.245 pruebas y 12.169 aserciones del conjunto completo, 40 pruebas de interfaz y los gates de análisis, formato, build y vistas correctos sobre el código final.
