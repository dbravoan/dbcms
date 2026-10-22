# Integrar el asistente IA en un editor

Para redactar, componer secciones, generar medios y traducir, utiliza la
[guía de uso](AI_EDITOR_TRANSLATION.md). Esta referencia describe los destinos
de aplicación y el contrato de integración; generar no guarda ni publica.

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
