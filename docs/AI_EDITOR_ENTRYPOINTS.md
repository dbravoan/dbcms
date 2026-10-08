# Asistente IA en los editores

Fecha de revisión: 11 de octubre de 2026.

El asistente comparte los servicios y la autorización del CMS. Cada editor aporta la traducción activa y los mercados seleccionados; las instrucciones no cambian esos datos silenciosamente. Los resultados se revisan antes de aplicarlos al formulario. Aplicar texto, metadatos o imágenes no guarda ni publica el elemento: se utiliza después el botón de guardar del editor.

## Texto y bloques

En un campo de texto o un bloque, enfoca el campo y pulsa el icono del asistente o `Alt+A`. Un campo vacío permite redactar; un campo con contenido permite corregir y mejorar. «Reemplazar» modifica ese campo y «Añadir» conserva su texto inicial. Escribir las instrucciones dentro del asistente no cambia el campo de destino.

Desde «Generar secciones» se puede generar un bloque concreto o una secuencia. Reemplazar afecta únicamente a los bloques de la traducción activa; añadir conserva los existentes. Un cambio de idioma o mercados mientras se genera impide aplicar un resultado que corresponda al contexto anterior.

Los campos de contraseñas, cantidades, direcciones de correo y URL multimedia no aceptan redacción de prosa. La generación multimedia utiliza su entrada específica.

## Imágenes y portadas

Los artículos y productos ofrecen «Generar imagen con IA» junto a la portada. Tras generar, se revisa la vista previa y se aplica como imagen destacada. El estado del formulario y la vista previa se actualizan juntos.

Cualquier campo conectado al gestor multimedia puede utilizar la misma función: abre el selector de medios y pulsa «Generar imagen con IA». «Usar en este campo» devuelve la imagen al destino que abrió el selector. Esto incluye imágenes de bloque, comparativas antes/después, fondos, diapositivas, avatares y campos multimedia genéricos. No sustituye automáticamente la portada ni crea un bloque adicional.

La generación exige permisos de uso de IA y gestión de medios, un proveedor compatible con imágenes y su conexión configurada. Una conexión de texto no garantiza generación de imágenes. Si falta la modalidad o la configuración, el servidor devuelve una indisponibilidad; no produce una imagen simulada ni declara éxito. No se cambia de proveedor para eludir ese resultado.

## SEO

«Generar SEO» toma el título, los campos editoriales y los textos de los bloques de la traducción activa, incluyendo columnas y otros grupos anidados. Conserva separados los idiomas. Las sugerencias se aplican a los campos de metadatos y se guardan con el formulario.

## Superficies revisadas

| Superficie | Texto contextual | Creación del formulario | Bloques | Imágenes |
| --- | --- | --- | --- | --- |
| Página | Sí | Sí | Generación y texto de bloque | Gestor de medios de los bloques |
| Artículo | Sí | Sí | Generación y texto de bloque | Portada y gestor de medios de los bloques |
| Producto | Sí | Sí | Generación y texto de bloque | Imagen principal y gestor de medios de los bloques |
| Galería | Sí | Sí | No declarados por la entidad | Campos conectados al gestor de medios |
| Formulario | Sí | Sí | No declarados por la entidad | Campos conectados al gestor de medios |
| Categorías de blog y tienda | Sí | Según el registro del módulo | No declarados por la entidad | Según sus campos |
| Campo multimedia de cualquier módulo | No se redacta prosa en su URL | No corresponde | Según el editor | Selección y aplicación en el destino exacto |

Esta matriz describe los contratos registrados y sus entradas de interfaz. Las pruebas unitarias de interfaz comprueban el destino de las escrituras y la aplicación de resultados; no equivalen a haber generado una imagen real con todos los proveedores. Las llamadas reales a modelos y las comprobaciones visuales se enumeran por separado en el informe funcional.

## Integración de nuevos módulos

El módulo puede abrir el asistente con `window.CmsAssistant.open({ target: campo, mode: 'text' })`. Para una URL multimedia concreta, utiliza `mode: 'image'` o `mode: 'video'` y el campo en `target`; la aplicación dispara los eventos `input` y `change` del campo.

Los selectores multimedia utilizan `window.CmsAssistant.open({ mode: 'image', mediaTarget: destino })`. La aplicación devuelve el evento común `file-selected`, con `detail.target` y `detail.file.url`. El mismo consumidor que procesa la selección manual procesa el resultado de IA.

El evento `cms-assistant-ready` permite mostrar las acciones cuando el módulo se inicializa después del formulario. Los botones permanecen ocultos cuando el asistente no está disponible. La autorización final y la disponibilidad siempre se comprueban en el servidor.

Para acciones de entidad, el módulo declara sus campos, vistas de creación/edición y si utiliza bloques mediante su esquema del registro de contenido. El asistente no implementa otra versión de sus reglas de guardado.

## Regresiones verificadas

`tests/Frontend/ai-media-entrypoints.test.mjs` comprueba diez casos: apertura por modalidad, retorno al selector exacto, rechazo tras cambio de contexto, aplicación a una URL explícita, estado de portada, integración del gestor universal, inicialización tardía, exclusión de campos sensibles, conservación del destino al escribir instrucciones y lectura SEO de bloques multilingües anidados. Las reproducciones iniciales fallaron antes de aplicar las correcciones.

Verificación en contenedor: esos diez casos y los trece existentes de contexto del editor pasan. No se han introducido claves en esta documentación ni se han habilitado proveedores multimedia automáticamente.
