# Referencia de campos del editor de bloques

Esta guía describe los campos que ve el editor para cada bloque del núcleo en
dbCMS 3.1.0. Los formularios se generan desde el esquema del bloque y el editor
común. Los campos de medios se marcan expresamente; el resto son textos, números,
booleanos, listas o repetidores.

## Cómo leer las tablas

- **Control**: el input que renderiza el editor.
- **Por defecto**: valor que recibe un bloque insertado en modo vacío. En modo
  demostración se usa el payload de ejemplo del bloque.
- **Uso**: dónde se consume el valor al renderizar. Un campo marcado como
  “opcional” puede quedar vacío sin romper el bloque.
- **Medio**: `Imagen` o `Vídeo` indica que abre el selector multimedia y filtra
  el tipo. `—` indica que no es un medio.
- Los campos con **visible si** solo aparecen cuando se cumple la condición.
- Los repetidores permiten añadir, editar y eliminar filas; sus hijos admiten los
  mismos tipos de control que el nivel raíz.

## Comportamiento común

- Los medios aceptan una ruta local (`/storage/...`) o una URL `http`/`https`.
  Las URLs ejecutables se rechazan en validación y se vacían al renderizar.
- Los campos con `show_when` se ocultan cuando no aplican, pero conservan su
  valor guardado.
- Los textos largos (`content`, `description`, `caption`, `quote`, `subtitle`,
  `summary`, `body`, `answer`, `guidance`, `assumptions`, `session_definition`,
  `disclaimer`) usan `textarea`.
- Los campos `image_url`, `after_image_url`, `avatar`, `logo_url`, `poster_url`,
  `video_url`, `mobile_video_url`, `bg_image_url`, `bg_video_url` y
  `bg_video_poster` se detectan como medios cuando aparecen en un payload.
- El editor no renombra claves ni convierte payloads históricos.

## Bloques Basic

### Carrusel (`carousel`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | vacío | Título opcional sobre las diapositivas | — |
| `height` | Número entero | 520 | Altura del carrusel, limitada entre 240 y 900 px | — |
| `autoplay` | Booleano | activo | Avanza automáticamente | — |
| `interval` | Número entero | 6000 | Milisegundos entre diapositivas; solo visible si `autoplay` está activo | — |
| `show_arrows` | Booleano | activo | Flechas de navegación | — |
| `show_dots` | Booleano | activo | Indicadores de posición | — |
| `slides` | Repetidor | 2 diapositivas demo | Cada fila es una diapositiva | — |
| `slides.*.image_url` | Medio | URL demo | Imagen de la diapositiva; se usa como fondo y texto alternativo de apoyo | Imagen |
| `slides.*.title` | Texto | texto demo | Titular de la diapositiva | — |
| `slides.*.subtitle` | Texto largo | texto demo | Texto de apoyo | — |
| `slides.*.cta_text` | Texto | texto demo | Texto del botón | — |
| `slides.*.cta_url` | URL | `/` | Destino interno o externo del botón | — |

### Incrustador de código / HTML (`code_embed`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | vacío | Etiqueta pequeña y título accesible del iframe | — |
| `content` | Texto largo | HTML demo | Código HTML que se renderiza dentro de un iframe con `sandbox="allow-scripts"` | — |

### Llamada a la acción (`cta`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Titular del banner | — |
| `content` | Texto largo | texto demo | Descripción del banner | — |
| `cta_text` | Texto | `Comenzar Ahora` | Texto del botón | — |
| `cta_url` | URL | `/contacto` | Destino del botón | — |

### Preguntas frecuentes (`faq`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título del acordeón | — |
| `items` | Repetidor | 2 preguntas demo | Cada fila es una pregunta | — |
| `items.*.question` | Texto | texto demo | Pregunta visible | — |
| `items.*.answer` | Texto largo | texto demo | Respuesta desplegable | — |

### Cuadrícula de características (`features`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la cuadrícula | — |
| `grid_columns` | Select | `3` | Columnas en pantallas grandes | — |
| `items` | Repetidor | 3 tarjetas demo | Cada fila es una tarjeta | — |
| `items.*.icon` | Texto | emoji demo | Icono o emoji de la tarjeta | — |
| `items.*.title` | Texto | texto demo | Título de la tarjeta | — |
| `items.*.description` | Texto largo | texto demo | Descripción de la tarjeta | — |

### Hero (`hero`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Titular principal | — |
| `content` | Texto largo | texto demo | Párrafo de apoyo | — |
| `cta_text` | Texto | texto demo | Texto del botón principal | — |
| `cta_url` | URL | `/contacto` | Destino del botón principal | — |
| `secondary_cta_text` | Texto | texto demo | Texto del botón secundario | — |
| `secondary_cta_url` | URL | `/blog` | Destino del botón secundario | — |
| `image_url` | Medio | vacío | Imagen principal; si hay vídeo se usa como póster | Imagen |
| `video_url` | Medio | vacío | Vídeo de fondo o principal | Vídeo |
| `badge` | Texto | texto demo | Insignia sobre el titular | — |

### Planes y precios (`pricing`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la comparativa | — |
| `items` | Repetidor | 2 planes demo | Cada fila es una tarjeta de precio | — |
| `items.*.name` | Texto | texto demo | Nombre del plan | — |
| `items.*.price` | Texto | texto demo | Precio visible | — |
| `items.*.period` | Texto | texto demo | Descripción o periodo del plan | — |
| `items.*.features` | Lista | lista demo | Características, una por línea | — |
| `items.*.cta_text` | Texto | texto demo | Texto del botón | — |
| `items.*.cta_url` | URL | `/contacto` | Destino del botón | — |

### Texto enriquecido (`text`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la sección | — |
| `content` | Editor propio | markdown demo | Contenido Markdown o HTML saneado | — |
| `format` | Select | `markdown` | Formato del contenido; el editor propio ofrece las mismas opciones | — |

### Testimonios (`testimonials`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la sección | — |
| `items` | Repetidor | 2 testimonios demo | Cada fila es una cita | — |
| `items.*.author` | Texto | texto demo | Nombre de la persona | — |
| `items.*.role` | Texto | texto demo | Cargo o empresa | — |
| `items.*.quote` | Texto largo | texto demo | Cita mostrada en la tarjeta | — |
| `items.*.avatar` | Medio | URL demo | Avatar de la persona | Imagen |

`rating` existe en el render como valor opcional de payload, pero no se expone
como input en 3.1.0; si está ausente se muestran cinco estrellas.

### Vídeo (`video`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `video_url` | Medio | vacío | Archivo o URL directa del vídeo | Vídeo |
| `poster_url` | Medio | vacío | Imagen mostrada antes de reproducir | Imagen |
| `title` | Texto | texto demo | Título sobre el reproductor | — |
| `caption` | Texto largo | texto demo | Leyenda bajo el vídeo | — |
| `autoplay` | Booleano | desactivado | Reproducción automática | — |
| `loop` | Booleano | desactivado | Repetir al terminar | — |
| `controls` | Booleano | activo | Controles nativos | — |
| `muted` | Booleano | desactivado | Silenciar; se fuerza con autoplay | — |

## BeforeAfter

### Comparador antes / después (`before_after`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | vacío | Título opcional del comparador | — |
| `image_url` | Medio | vacío | Imagen del lado «antes» | Imagen |
| `after_image_url` | Medio | vacío | Imagen del lado «después» | Imagen |
| `before_label` | Texto | vacío; render usa `Antes` | Etiqueta del lado izquierdo | — |
| `after_label` | Texto | vacío; render usa `Después` | Etiqueta del lado derecho | — |

La versión derivada de Termosalud añade `description`, `caption`, `source_url`,
`original_url`, `after_original_url`, `original_label`, `anchor`, `media_width`
y `media_height`; se documentan en la guía del proyecto derivado.

## Charts

### Gráficas (`charts`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la visualización | — |
| `chart_type` | Select | `bar` | Barras, línea, anillo o métrica | — |
| `items` | Repetidor | 3 valores demo | Filas de barras o puntos; visible para barras y líneas | — |
| `items.*.label` | Texto | texto demo | Etiqueta de la fila | — |
| `items.*.value` | Número | valor demo | Valor numérico | — |
| `items.*.color` | Texto | color demo | Color CSS de la barra o punto | — |
| `items.*.unit` | Texto | `%` | Sufijo visible | — |
| `donut_color` | Texto | `var(--cms-accent)` | Color del anillo; visible para anillo | — |
| `donut_percentage` | Número | 88 | Porcentaje del anillo; visible para anillo | — |
| `donut_label` | Texto | texto demo | Texto central; visible para anillo | — |
| `donut_subtext` | Texto | texto demo | Texto inferior; visible para anillo | — |
| `metric_value` | Texto | texto demo | Valor grande; visible para métrica | — |
| `metric_label` | Texto | texto demo | Etiqueta; visible para métrica | — |
| `metric_description` | Texto largo | texto demo | Descripción; visible para métrica | — |

### KPI (`kpi`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la cuadrícula | — |
| `items` | Repetidor | 4 indicadores demo | Cada fila es un contador | — |
| `items.*.value` | Texto | texto demo | Valor visible, puede incluir símbolos | — |
| `items.*.label` | Texto | texto demo | Etiqueta del indicador | — |
| `items.*.description` | Texto largo | vacío | Texto opcional bajo el indicador | — |

### ROI (`roi`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la calculadora | — |
| `description` | Texto largo | texto demo | Explicación inicial | — |
| `initial_investment` | Número | 12000 | Inversión inicial | — |
| `annual_savings` | Número | 38000 | Ahorro anual | — |
| `project_years` | Entero | 3 | Años del proyecto | — |
| `current_solution_cost` | Número | 50000 | Coste actual anual | — |
| `optimized_solution_cost` | Número | 12000 | Coste optimizado anual | — |
| `payback_months` | Entero | 4 | Meses de amortización | — |
| `currency_symbol` | Texto | `€` | Símbolo de moneda de los importes | — |

## Módulos opcionales

### Últimos artículos del blog (`blog_posts`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la lista | — |
| `selection` | Select | `latest` | Recientes o destacados; visible si no hay artículo concreto | — |
| `category` | Select | todas | Filtra por categoría; visible si no hay artículo concreto | — |
| `post_id` | Select | automático | Artículo concreto; si se elige, ignora selección, categoría y límite | — |
| `limit` | Entero | 3 | Número máximo de artículos; visible si no hay artículo concreto | — |
| `grid_columns` | Select | `3` | Columnas en pantallas grandes | — |

### Formulario personalizado (`custom_form`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título opcional sobre el formulario | — |
| `form_slug` | Select | vacío | Formulario que se inserta | — |
| `button_text` | Texto | `Enviar Mensaje` | Texto del botón de envío | — |

### Álbum de galería (`gallery_album`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título opcional | — |
| `album_slug` | Select | todas | Álbum mostrado; vacío incluye todos | — |
| `grid_columns` | Select | `3` | Columnas en pantallas grandes | — |
| `limit` | Entero | 6 | Número máximo de imágenes | — |

### Buscador (`search_box`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | vacío | Título opcional sobre la caja | — |
| `size` | Select | `lg` | Tamaño visual, normal o grande | — |
| `placeholder` | Texto | vacío | Texto dentro del campo vacío | — |

### Productos de la tienda (`shop_products`)

| Campo | Control | Por defecto | Uso | Medio |
|---|---|---|---|---|
| `title` | Texto | texto demo | Título de la lista | — |
| `subtitle` | Texto largo | vacío | Texto introductorio | — |
| `selection` | Select | `all` | Catálogo o destacados; visible si no hay producto concreto | — |
| `category` | Select | todas | Filtra por categoría; visible si no hay producto concreto | — |
| `product_id` | Select | automático | Producto concreto; si se elige, ignora selección, categoría y límite | — |
| `limit` | Entero | 4 | Número máximo de productos; visible si no hay producto concreto | — |
| `grid_columns` | Select | `4` | Columnas en pantallas grandes | — |

## Panel de apariencia

El panel se comparte entre sección, columna y bloque. Los campos de fondo y
bordes pertenecen al contenedor; en una columna se guardan en `col.style`.

| Pestaña | Campo | Control | Uso |
|---|---|---|---|
| Contenedor | `container_style` | Select | Estilo visual del contenedor |
| Contenedor | `content_width` | Select | Ancho del contenido |
| Fondo | `bg_type` | Select | Ninguno, color, imagen o vídeo |
| Fondo | `bg_color` | Texto | Color CSS o `rgba()` |
| Fondo | `bg_image_url` | Medio imagen | Imagen de fondo |
| Fondo | `bg_image_overlay` | Texto | Capa de color sobre la imagen |
| Fondo | `bg_behavior` | Select | Normal, parallax o fijo; solo sección/columna |
| Fondo | `bg_video_url` | Medio vídeo | Vídeo de fondo |
| Fondo | `bg_video_poster` | Medio imagen | Póster del vídeo de fondo |
| Fondo | `bg_video_overlay` | Texto | Capa de color sobre el vídeo |
| Divisores | `divider_top` / `divider_bottom` | Select + color + altura + interruptor | Separadores decorativos |
| Bordes | `border_top` / `border_bottom` | Interruptor + color + ancho | Bordes superior e inferior |
| Animación | `animation` | Select | Animación de entrada |
| Animación | `scroll_magic.scrub` | Select | Comportamiento de scroll |
| Animación | `animation_delay` | Texto | Retardo de la animación |

`border_top.style` y `animation_duration` existen en el payload y en el render,
pero el panel no los expone en 3.1.0; conservan sus valores por defecto.

## Relación de medios y selector

| Bloque | Campos con selector |
|---|---|
| Hero | `image_url` (imagen), `video_url` (vídeo) |
| Carrusel | `slides.*.image_url` (imagen) |
| Vídeo | `video_url` (vídeo), `poster_url` (imagen) |
| Testimonios | `items.*.avatar` (imagen) |
| BeforeAfter | `image_url`, `after_image_url` (imagen) |
| Apariencia | `bg_image_url`, `bg_video_poster` (imagen), `bg_video_url` (vídeo) |

Los campos `cta_url`, `secondary_cta_url`, `source_url`, `youtube_url` y `url`
siguen siendo URLs normales, no medios.

## Medios en bloques personalizados

Los bloques de un proyecto derivado que declaren `type: media` reciben el mismo
selector. Si un campo no se detecta automáticamente, el bloque puede declararlo
explícitamente:

```php
'image_url' => [
    'type' => 'media',
    'media_kind' => 'image',
    'label' => 'Imagen',
    'alt_key' => 'image_alt',
],
```

Los tipos `media` se validan con la misma política de URLs seguras que los campos
`url`, pero restringidos a `http`, `https` o rutas locales.
