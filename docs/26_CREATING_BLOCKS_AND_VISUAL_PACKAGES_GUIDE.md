# 26 · Crear bloques y paquetes visuales

Esta guía construye una tarjeta informativa completa, `info_card`, sin entidades
ni tablas propias. Para crear una función de negocio y sus bloques, sigue la
[guía de módulos](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md). Los contratos de
[registro y disponibilidad](CORE_EXTENSION_REGISTRATION.md) son la referencia
canónica; este documento muestra cómo aplicarlos.

## 1. Elegir el propietario

| Necesidad | Qué crear | Quién registra el bloque |
| :--- | :--- | :--- |
| Presentar contenido configurable sin dominio propio | Bloque dentro de un paquete visual | `BlockServiceProvider`, leyendo `block-package.json` |
| Agrupar y distribuir varios componentes visuales | Paquete visual con varias clases de bloque | El mismo proveedor; cada clase tiene un tipo único |
| Gestionar eventos, productos u otras entidades con reglas y permisos | Módulo funcional, con cero o más bloques | El proveedor del módulo registra sus contribuciones |
| Mostrar datos de un módulo dentro de una página | Bloque del módulo, dependiente de este | El proveedor del módulo; `getRequiredModule()` declara la dependencia |

Un **bloque** es una definición insertable en secciones. Un **paquete visual**
agrupa definiciones y controla su disponibilidad. Un **módulo** posee una función
de negocio, sus datos y sus contribuciones: puede incluir varios bloques, pero
no necesita ninguno. No crees un módulo para agrupar únicamente tarjetas o
comparadores. Charts, KPI/ROI y BeforeAfter son ejemplos de paquetes visuales.

Bloques y widgets usan `BlockRegistryInterface`, la misma validación y la misma
decisión de disponibilidad. La categoría del selector no crea otro subsistema.
Pages pertenece al núcleo; una página puede utilizar bloques de cualquier
propietario disponible.

## 2. Preparar el ejemplo sin alterar una instalación

Trabaja en una **copia desechable del proyecto**, con filesystem y base de datos
propios. No ejecutes migraciones de prueba sobre una instalación existente.
Arranca Sail desde la raíz de esa copia; PHP, Composer y Artisan se ejecutan
siempre dentro del contenedor:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan help route:list
```

Si todavía no tienes `vendor`, utiliza el
[runbook de arranque sin PHP en el host](06_ZERO_HOST_PHP_SAIL_RUNBOOK.md).
La copia debe estar instalada para guardar páginas y gestionar paquetes.

No hay un generador de bloques equivalente a `make:cms-module`. Prepara un
árbol externo al catálogo del CMS:

```bash
tutorial_dir="$(mktemp -d)"
mkdir -p "$tutorial_dir/InfoCard/Blocks"
mkdir -p "$tutorial_dir/InfoCard/Resources/views/blocks"
```

Guarda los tres archivos siguientes dentro de `$tutorial_dir`:

```text
InfoCard/
├── block-package.json
├── Blocks/InfoCardBlock.php
└── Resources/views/blocks/card.blade.php
```

`InfoCard` es el directorio y segmento del namespace; `info_card` es el
identificador del paquete y, por elección del ejemplo, también el tipo del
bloque. Son identidades independientes: un paquete puede declarar varios tipos.

## 3. Archivos completos

### `InfoCard/block-package.json`

```json
{
  "identifier": "info_card",
  "name": "Tarjeta informativa",
  "version": "1.0.0",
  "blocks": [
    "BlockPackages\\InfoCard\\Blocks\\InfoCardBlock"
  ],
  "requires": {
    "core": "^3.0"
  }
}
```

`blocks` enumera clases, no rutas. Cada clase debe estar dentro del namespace
`BlockPackages\InfoCard\` y en el archivo que corresponde a su nombre. El
manifiesto usa una versión propia del paquete; no cambia la versión del CMS.

### `InfoCard/Blocks/InfoCardBlock.php`

```php
<?php

declare(strict_types=1);

namespace BlockPackages\InfoCard\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;

final class InfoCardBlock extends AbstractBlockType
{
    public function getType(): string
    {
        return 'info_card';
    }

    public function getName(): string
    {
        return 'Tarjeta informativa';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-circle-info';
    }

    public function getCategory(): string
    {
        return 'content';
    }

    public function getDescription(): string
    {
        return 'Título, texto y una lista breve de datos informativos.';
    }

    public function getDefaultPayload(): array
    {
        return [
            'title' => 'Información de contacto',
            'body' => 'Puedes visitarnos de lunes a viernes.',
            'tone' => 'neutral',
            'show_title' => true,
            'facts' => [
                ['label' => 'Horario', 'value' => 'De 09:00 a 18:00'],
            ],
        ];
    }

    public function getEditorSchema(): array
    {
        return [
            'title' => [
                'type' => 'text',
                'label' => 'Título',
                'required' => true,
                'rules' => ['string', 'max:120'],
            ],
            'body' => [
                'type' => 'textarea',
                'label' => 'Texto',
                'rules' => ['nullable', 'string', 'max:2000'],
            ],
            'tone' => [
                'type' => 'select',
                'label' => 'Tono visual',
                'default' => 'neutral',
                'options' => ['neutral' => 'Neutro', 'accent' => 'Destacado'],
            ],
            'show_title' => [
                'type' => 'boolean',
                'label' => 'Mostrar título',
                'default' => true,
            ],
            'facts' => [
                'type' => 'repeater',
                'label' => 'Datos',
                'description' => 'Hasta seis pares de etiqueta y valor.',
                'rules' => ['nullable', 'array', 'max:6'],
                'fields' => [
                    'label' => [
                        'type' => 'text',
                        'label' => 'Etiqueta',
                        'required' => true,
                        'rules' => ['string', 'max:80'],
                    ],
                    'value' => [
                        'type' => 'text',
                        'label' => 'Valor',
                        'required' => true,
                        'rules' => ['string', 'max:200'],
                    ],
                ],
            ],
        ];
    }

    public function getTranslationPaths(): array
    {
        return ['title', 'body', 'facts.*.label', 'facts.*.value'];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $view = app(FrontViewResolver::class)->resolve(
            'info_card.blocks.card',
            'info_card::blocks.card',
        );

        return view($view, [
            'title' => (string) ($payload['title'] ?? ''),
            'body' => (string) ($payload['body'] ?? ''),
            'tone' => ($payload['tone'] ?? 'neutral') === 'accent' ? 'accent' : 'neutral',
            'showTitle' => (bool) ($payload['show_title'] ?? true),
            'facts' => (array) ($payload['facts'] ?? []),
        ])->render();
    }
}
```

`AbstractBlockType` ya implementa el contrato visual y el de metadatos
traducibles. El ejemplo hereda `getRequiredModule() = null`,
`isFullWidth() = false`, `getEditorView() = null` y `getEditorData() = []`.
No añade otro proveedor, autoloader, registro ni fila de instalación durante el
arranque.

### `InfoCard/Resources/views/blocks/card.blade.php`

```blade
<article data-block="info_card" data-tone="{{ $tone }}">
    @if ($showTitle && $title !== '')
        <h2>{{ $title }}</h2>
    @endif

    @if ($body !== '')
        <p>{{ $body }}</p>
    @endif

    @if ($facts !== [])
        <dl>
            @foreach ($facts as $fact)
                <dt>{{ (string) ($fact['label'] ?? '') }}</dt>
                <dd>{{ (string) ($fact['value'] ?? '') }}</dd>
            @endforeach
        </dl>
    @endif
</article>
```

El resultado es HTML semántico sin consultas ni dependencias de JavaScript o
CSS propias. `data-tone` permite que el tema distinga ambos tonos; este ejemplo
no incluye un estilo visual diferente para ellos. Las expresiones `{{ }}` escapan
el contenido: escribir `<script>` en el texto lo muestra como texto, sin
convertirlo en una etiqueta ejecutable. No sustituyas este escape por `{!! !!}`
para admitir HTML arbitrario.

## 4. Cómo funciona el esquema

`BlockRegistry::validate()` convierte el esquema en reglas de Laravel al guardar;
no basta con que un formulario se vea correcto. `required: true` añade la regla
`required`. `rules` **sustituye** las reglas inferidas para ese campo, por lo que
hay que incluir `array`, `string`, límites o valores permitidos cuando proceda.
Los campos de tipo `url` añaden siempre la política compartida de protocolos
seguros, también cuando declaran reglas propias.
En un `select` sin `rules`, las claves de un mapa de opciones —o los valores de
una lista— son los valores aceptados. Un `options_source` alimenta las opciones
del editor desde `getEditorData()`; no convierte esa lista dinámica en validación
de pertenencia del servidor. Declara las reglas necesarias en la definición.

| Tipo del esquema | Control genérico | Validación inferida si no se indican `rules` |
| :--- | :--- | :--- |
| `text`, `string` | Texto | Cadena opcional |
| `url` | Texto con teclado URL; admite rutas relativas | Cadena opcional y protocolos seguros; `url` de Laravel, si se añade en `rules`, exige URL absoluta |
| `email` | Email | Cadena opcional; declarar `email` en `rules` para validar el formato también en el servidor |
| `textarea` | Texto multilínea | Cadena opcional |
| `number`, `integer` | Input numérico | Numérico o entero opcional |
| `boolean` | Casilla | Booleano opcional |
| `select` | Selector de `options` | Valor opcional perteneciente a las opciones declaradas |
| `json`, `array` | Editor de objeto o lista JSON | Array opcional; no un string JSON almacenado sin decodificar |
| `list` | Un elemento de texto por línea | Array opcional; añade reglas de elementos si son necesarias |
| `repeater` | Añadir/eliminar elementos con `fields` | Array opcional, cada elemento array y reglas de sus campos |

El repetidor genérico ofrece campos hijos de texto, número, textarea y lista;
no es un editor recursivo de repetidores anidados ni ofrece todos los controles
de nivel superior dentro de cada fila. En nuestro ejemplo los hijos son texto.
La cota `max:6` se comprueba en el servidor; no presupone que el botón de añadir
se oculte al llegar al sexto elemento.

Sin esquema explícito, `AbstractBlockType` lo infiere de `getDefaultPayload()`.
Las claves `url` y las terminadas en `_url` se infieren como campos `url`.
Una lista vacía no permite inferir la estructura de sus elementos; usa un esquema
explícito como aquí para los repetidores y las restricciones de negocio.

**Vacío y demostración son opciones distintas de inserción.** En modo vacío,
`resources/js/block-manager.js` inicializa `default` de cada campo del esquema;
sin él usa cadenas vacías, `false`, arrays vacíos u opciones iniciales según el
tipo. `getDefaultPayload()` aporta el contenido de demostración solamente cuando
se elige ese modo. Aquí, vacío conserva `tone=neutral` y `show_title=true`, pero
exige introducir un título; demostración añade el horario de ejemplo.

El catálogo conserva `raw_category`, pero agrupa `dynamic`, `widget` e
`interactive` bajo el selector de widgets y las demás categorías bajo contenido.
El icono admite clases Font Awesome o texto/emoji.

Para un control especializado, `getEditorView()` puede devolver una vista Blade
y `getEditorData()` sus datos. Mantén el esquema para la validación; una vista
personalizada no la sustituye. El editor rico del paquete Basic es una referencia
real de esa extensión.

## 5. Descubrimiento, instalación y ZIP

Elige **una** de las dos vías siguientes. Para probar ambas utiliza dos copias
desechables independientes: no mantengas `InfoCard` incluido y subido a la vez.

### Vía A: paquete incluido con el proyecto

Desde la raíz de la copia, conservando la variable del apartado 2:

```bash
cp -R "$tutorial_dir/InfoCard" block-packages/InfoCard
./vendor/bin/sail artisan route:list --name=admin.block-packages
```

En el siguiente arranque de la aplicación, `BlockServiceProvider` descubre
`block-packages/InfoCard/block-package.json`, registra el autoload del namespace,
carga `Resources/views` como `info_card::` y registra la clase con su propietario
`info_card`. No hace falta editar `composer.json` ni ejecutar Composer para este
namespace.

Los paquetes incluidos están disponibles por defecto si cumplen sus requisitos y
no existe una desactivación persistida. Descubrirlos no crea registros en
`block_packages`. El administrador puede ocultarlos y reactivarlos, pero no
eliminarlos desde la pantalla de paquetes.

### Vía B: distribución mediante ZIP

No copies el paquete a `block-packages` en la instalación receptora. Genera el
ZIP desde el árbol externo; este comando usa únicamente la biblioteca estándar
de Python en el host, sin ejecutar PHP:

```bash
python3 - "$tutorial_dir" <<'PY'
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
import sys

workspace = Path(sys.argv[1])
package = workspace / "InfoCard"
archive = workspace / "InfoCard-1.0.0.zip"
files = [
    "block-package.json",
    "Blocks/InfoCardBlock.php",
    "Resources/views/blocks/card.blade.php",
]
with ZipFile(archive, "w", compression=ZIP_DEFLATED) as output:
    for name in files:
        output.write(package / name, arcname=f"InfoCard/{name}")
print(archive)
PY
```

El ZIP debe contener **una raíz `InfoCard/`**, con el manifiesto dentro, no los
archivos sueltos ni una doble carpeta `InfoCard/InfoCard/`. No incluyas `.env`,
`vendor`, archivos ocultos, enlaces simbólicos ni otra raíz.

1. Accede con una cuenta administrativa con permiso `manage:blocks` a
   `/{ADMIN_PATH}/block-packages` (`/admin/block-packages` con la configuración
   predeterminada). Su nombre de ruta es `admin.block-packages.index`.
2. Selecciona el ZIP en **Añadir un paquete de bloques** y pulsa **Instalar paquete**.
3. Comprueba el mensaje **Paquete instalado y desactivado.** y el estado **Oculto**.
4. Pulsa **Activar**. La tarjeta debe aparecer en el selector de bloques de una
   página editable por tu cuenta; `manage:blocks` no concede por sí solo permiso
   para editar páginas.

La subida usa el campo `package_zip`, admite ZIP de hasta 51 200 KiB según la
validación HTTP actual y aplica también los límites del archivo de extensiones.
La instalación valida el archivo, extrae a staging y comprueba las clases en un
proceso PHP separado contra el catálogo actual. Solo después publica el
directorio y persiste el paquete desactivado. Necesita PHP CLI y creación de
subprocesos disponibles en el servidor; véanse los requisitos y límites en
[registro de extensiones](CORE_EXTENSION_REGISTRATION.md).

Los paquetes subidos viven en `storage/app/extensions/blocks/InfoCard`, con
configuración de raíz sustituible. En despliegues con contenedor debe persistir
ese almacenamiento. No se ejecuta Composer ni npm durante la subida y no se
sobrescribe otro paquete instalado. Un ZIP contiene código PHP de un proveedor
de confianza; la comprobación no lo convierte en código aislado o no confiable.

`requires.core` comprueba la versión del CMS. Otras claves de `requires` nombran
módulos que deben estar activos y tener una versión compatible; no son nombres
de paquetes visuales ni dependencias Composer. Si el bloque pertenece
funcionalmente a un módulo propio, sigue el recorrido de módulos con bloques en
la [guía de módulos](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md).

## 6. Insertar, guardar y traducir

Abre el editor de una página, selecciona un idioma y añade **Tarjeta informativa**.
Prueba primero la inserción vacía: el título será obligatorio y el repetidor
estará vacío. Después prueba la demostración y confirma sus textos iniciales.
Guarda y vuelve a abrir; las filas, el tono y la casilla deben conservarse.

El bloque hereda `isFullWidth() = false`: puede insertarse como sección o dentro
de una columna. Un bloque con rejilla interior debe declarar
`isFullWidth() = true`; el editor lo coloca a nivel de sección. El ancho completo
no significa un sistema de bloques diferente. Para el almacenamiento, las claves
`columns` y `grid_columns` y los enlaces de edición desde el front, consulta
[edición de secciones](15_FRONT_SECTION_EDITING.md).

Esta es una sección simple de ejemplo para las pruebas de renderizado. No es
una petición HTTP completa ni debe escribirse directamente en la base de datos:

```json
{
  "type": "info_card",
  "title": "Información de contacto",
  "body": "Puedes visitarnos de lunes a viernes.",
  "tone": "neutral",
  "show_title": true,
  "facts": [
    {"label": "Horario", "value": "De 09:00 a 18:00"}
  ]
}
```

El mismo objeto puede ocupar `columns[0].blocks[0]` dentro de una sección con
columnas. La definición no cambia. Usa el editor o el contrato de mutación
compartido para guardarlo, con sus permisos y validación.

La versión de contenido por idioma reside en las traducciones de la sección.
`SectionRendererService` pasa mercado e idioma a `render()` y selecciona el
contenido de ese idioma; no muestra una sección sin traducción para el idioma
solicitado. Esta tarjeta no consulta datos de mercado: los parámetros están
previstos para bloques que sí lo necesiten.

`getTranslationPaths()` declara los textos que el traductor estructurado de
AiAssistant puede traducir cuando ese módulo está disponible. El comodín
`facts.*` recorre elementos existentes; no inventa filas. `tone` y `show_title`
se conservan. No traduzcas slugs de álbumes, IDs de formularios, identificadores
de entidades, URLs de recursos ni valores numéricos como si fueran texto
editorial. Declarar las rutas no ejecuta una traducción ni sustituye la edición
manual por idioma. Consulta [idiomas](14_LOCALIZATION_AND_I18N.md) y
[traducción asistida](AI_EDITOR_TRANSLATION.md).

## 7. Personalizar desde un tema

`FrontViewResolver::resolve('info_card.blocks.card', 'info_card::blocks.card')`
busca primero:

```text
themes/{tema-activo}/info_card/blocks/card.blade.php
```

Si no existe utiliza la vista del paquete. No busca ese bloque en otro tema.
Copia `card.blade.php` a esa ruta dentro de un tema instalado de la copia
desechable, cambia su elemento inicial a
`<article data-block="info_card" data-theme-override="true" data-tone="{{ $tone }}">`
y activa el tema. El HTML público debe contener `data-theme-override="true"`;
con un tema sin esa vista debe seguir apareciendo `data-block="info_card"`
mediante el fallback del paquete.

Para añadir CSS o JavaScript, integra los recursos en el build y layout del
proyecto o del tema según la [guía de temas públicos](17_FRONTOFFICE_THEME_GUIDE.md).
El contrato del bloque no ofrece un método `getAssets()` ni el instalador ejecuta
un build. Si introduces utilidades Tailwind, comprueba que el build escanea la
ruta que las contiene: subir un ZIP a `storage` no garantiza que sus clases CSS
se hayan compilado. Esta tarjeta evita esa dependencia para poder probarla sin
recompilar recursos.

## 8. Ocultar, reactivar y desinstalar

Con una página publicada que contenga la tarjeta:

1. Pulsa **Ocultar** en paquetes de bloques.
2. Comprueba que la tarjeta desaparece del selector y del HTML público. Su método
   `render()` no se ejecuta a través del registro cuando está desactivada.
3. Abre la página: sus instancias deben mostrarse como **Bloque no disponible**.
   Edita otro bloque y guarda. La instancia oculta debe permanecer intacta;
   no intentes modificarla ni insertar otra del mismo tipo mientras esté oculta.
4. Pulsa **Activar**: vuelve a aparecer la misma tarjeta, con sus datos guardados.

Ocultar cambia la disponibilidad del propietario, no elimina las secciones.
El guardado rechaza inserciones o modificaciones de bloques no disponibles y
preserva instancias anteriores sin cambios. La disponibilidad también se usa
para excluir sus contribuciones de búsqueda y de los selectores de IA.

Un paquete subido solo ofrece **Desinstalar paquete** cuando está oculto; esa
acción elimina sus archivos y su estado, no migra ni reemplaza las instancias
almacenadas. Estas quedan no disponibles. Para este recorrido basta con
ocultar/reactivar: no hace falta desinstalar. Un paquete incluido no ofrece
la desinstalación desde administración.

## 9. Problemas habituales

| Síntoma | Qué comprobar |
| :--- | :--- |
| `Duplicate block definition` | Tipo único en todo el registro; cambia `getType()` si colisiona con cualquier propietario |
| Namespace o clase no válidos | Coincidencia exacta `InfoCard/Blocks/InfoCardBlock.php` ↔ `BlockPackages\InfoCard\Blocks\InfoCardBlock`; no mover el manifiesto a la raíz del ZIP |
| El ZIP no se instala | Una sola raíz con nombre válido, sin enlaces ni archivos ocultos; no debe existir el identificador ni directorio en la instalación receptora |
| El paquete no puede arrancar en la validación | Error PHP, clase abstracta, contrato no implementado, dependencia de constructor no resoluble o colisión con el catálogo; comprobar también PHP CLI/subprocesos y timeout |
| Paquete instalado pero tarjeta ausente | ZIP empieza oculto; activar, revisar versiones y módulos requeridos, y comprobar el tipo en el selector correcto |
| El formulario acepta un valor pero guardar falla | Reglas del servidor, campos obligatorios y límites del repetidor; `rules` no se deriva de los atributos HTML |
| Un campo no aparece | Tipo admitido por el editor genérico y limitaciones de campos hijos; un esquema arbitrario no crea controles nuevos |
| Vista no encontrada o sustitución ignorada | Namespace `info_card::`, nombre `blocks.card`, tema realmente activo y ruta `info_card/blocks/card.blade.php` |
| CSS de un ZIP no se ve | Recursos no incluidos en el build; resolver la integración con el tema, sin esperar npm desde la subida |
| Cambiar idioma muestra contenido inesperado | Traducción de sección guardada para ese idioma; las listas y columnas localizadas sustituyen la clave completa, no se fusionan recursivamente |

## 10. Comprobar el recorrido

En el artefacto desechable, comprueba ambos modos de distribución por separado y
las dos marcas del administrador. Registra resultados observados, sin presentar
la mera lectura de los archivos como ejecución de pruebas:

- Instalación incluida y subida ZIP, estado inicial y activación.
- Inserción vacía y de demostración, sección simple y bloque en columna.
- Guardado y reapertura, rechazo de título vacío, tono desconocido y séptima fila.
- Renderizado público de una página publicada; texto escapado y traducción propia
  en cada idioma utilizado, sin sección pública para un idioma sin traducción.
- Vista del paquete y sustitución desde el tema activo.
- Ocultar, guardar otro bloque conservando la instancia y reactivar con los
  mismos datos; rechazo de una inserción mientras el paquete está oculto.

La [guía de pruebas](TESTING.md) define los contenedores y las vías de ejecución.
Las pruebas existentes de paquetes y preservación de bloques cubren el contrato
del CMS, pero no certifican automáticamente un paquete que acabas de escribir:
ejecuta también el ejemplo real en su composición desechable. La guía de
[módulos](11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md) continúa con un dominio
`Events` y el registro de bloques que consultan sus servicios.
