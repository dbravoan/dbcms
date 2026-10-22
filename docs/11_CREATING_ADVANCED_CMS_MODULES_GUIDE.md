# Crear y extender módulos de dbCMS

Leer [Core and extensions](../specs/CORE_AND_EXTENSIONS.md) y las
[convenciones](DEVELOPER_CONVENTIONS_AND_STANDARDS.md). Un módulo es una extensión
opcional del mismo proceso Laravel y base de datos; no es un sandbox de PHP.
Pages pertenece al núcleo; Charts, KPI/ROI y BeforeAfter son paquetes visuales.
Un módulo puede aportar cero, uno o varios bloques. Para un componente sin dominio
propio, seguir [crear bloques y paquetes visuales](26_CREATING_BLOCKS_AND_VISUAL_PACKAGES_GUIDE.md).

## Empezar con el generador actual

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan make:cms-module Events --pageable --crud
```

`make:cms-module` usa `stubs/cms-module/`. Crea manifiesto, proveedor en la raíz,
modelos y traducciones, contrato/servicio de persistencia, migraciones, rutas y
vistas. `--crud` añade edición; `--pageable` añade presentación pública.
`--with-auth` crea usuarios propios; `--dedicated-panel` incluye autenticación y
portal separado. No instalar módulos ni activar sus datos durante discovery.

El generador es un punto de partida, no una integración editorial completa.
Sus operaciones de persistencia deben integrarse con el esquema y los handlers
comunes si la entidad va a participar en publicación, capacidades, IA o MCP.
No promete generar DTOs ni todas las contribuciones del catálogo.

```text
modules/Events/
├── module.json
├── EventsModuleServiceProvider.php
├── Models/
├── Contracts/Services/
├── Services/
├── Database/Migrations/
├── Http/Controllers/Admin/
├── Routes/admin.php
└── Resources/views/
```

## Manifiesto, registro e instalación

El manifiesto generado declara la versión de core efectiva. Este es un ejemplo
mínimo para dbCMS 3.0:

```json
{
  "identifier": "events",
  "name": "Events",
  "version": "1.0.0",
  "provider": "Modules\\Events\\EventsModuleServiceProvider",
  "is_pageable": true,
  "requires": {"core": "^3.0"},
  "permissions": ["manage:events"],
  "settings": {}
}
```

El proveedor extiende `BaseModuleServiceProvider` y permanece en la raíz del
módulo: la base encuentra `Routes/admin.php` respecto a ese archivo. `parent::boot()`
registra esas rutas con autenticación administrativa y `module.enabled`.
`Routes/admin.php` añade el prefijo/nombre del módulo y sus permisos, sin duplicar
el grupo administrativo. Las rutas públicas se declaran en
`registerPageableRoutes()` y reciben la protección de disponibilidad de la base.
Las vistas y migraciones se registran desde el proveedor generado; registrar
una ruta de migración no equivale a ejecutar una instalación.

`register()` enlaza contratos necesarios. `boot()` aporta definiciones a registros
propietarios: entidades, bloques, capacidades, búsqueda y contenido público.
Las dependencias entre módulos se declaran en `requires`. No añadir imports de
implementaciones opcionales al núcleo ni escribir registros durante el arranque.
Instalar explícitamente desde el gestor o seleccionar `CMS_MODULES` antes de la
instalación. [Registro de extensiones](CORE_EXTENSION_REGISTRATION.md) describe
el grafo, los estados, las contribuciones administrativas y la retirada de acceso.

`getAdminNavigation()` devuelve elementos con ruta y permiso. `getAdminDashboard()`
aporta datos/vistas del módulo; conservar permisos y filtros por actor en las
consultas. Los catálogos genéricos no deben conocer el identificador concreto.

## Entidades, idiomas y publicación

La fila base contiene datos compartidos. Traducciones por entidad/locale tienen
unicidad, y la relación de mercados no duplica la entidad. Los nombres SQL usan
snake_case; los identificadores de módulo pueden tener guiones. Tomar los idiomas
y mercados activos del CMS, no de una lista fija en la vista.

Registrar `EntitySchema` y el handler propietario para usar las mutaciones
compartidas. Preservar autorización, revisión, traducciones, mercados y secciones
en la misma transacción. Las operaciones remotas salen después del commit.
Usar DTOs e interfaces cuando exista una frontera útil; el generador no obliga a
crear equivalentes para cada helper.

Declarar `PublicContentDefinition` únicamente para entidades públicas. Búsqueda,
sitemaps y corpus deben respetar fecha, publicación, mercados, traducción y
módulo disponible. La información administrativa y los datos de pacientes,
clientes o credenciales no son contenido GEO por defecto. Ver
[descubrimiento público](GEO_PUBLIC_CONTRIBUTORS.md) y
[búsqueda](22_GENERAL_SEARCH.md).

## Configuración y conexiones externas

`module.json.settings` define campos. Las claves con `{locale}` o `{market}` se
expanden mediante `ModuleSettingsService::getSchema()`; contraseñas y tokens
usan campos secretos. El módulo registra normalización y presentación específicas
en `ModuleSettingsExtensionRegistry`, sin condicionales por módulo en core.
`availability_setting` identifica un ajuste que interviene en disponibilidad;
no basta con llamar `is_enabled` a un booleano.

Una conexión a ERP, CRM o base externa requiere su propio contrato de acceso,
permisos, validación y política de reintento. El scaffold no genera esa conexión
ni garantiza una transacción distribuida. Mantener credenciales en configuración
protegida y separar el commit local de los efectos remotos. Consultar
[configuración e instalación](INSTALLATION_AND_MODULE_SETTINGS_BOUNDARIES.md)
y [correo y paquetes](MODULE_NOTIFICATION_AND_PACKAGE_SECURITY.md).

## Vistas, bloques y pruebas

Usar `<x-admin.*>` y tokens semánticos para ambos temas administrativos. Para
frontoffice seguir el [resolutor de temas](17_FRONTOFFICE_THEME_GUIDE.md), incluyendo
composiciones derivadas sin temas no seleccionados. No ejecutar consultas en Blade.
Un bloque del módulo usa la definición/registro común y requiere su módulo; las
instancias no disponibles se preservan sin ejecutarse.

Declarar la composición de tests y fixtures mínimos. Verificar instalación,
ausencia física, desactivación, actor sin permiso y las reglas específicas de
mercado/idioma/publicación. Las pruebas del generador deben ejecutar el artefacto
producido en una copia desechable, no limitarse a comprobar que hay archivos.
Seguir [TESTING](TESTING.md): rápido, integración aislada y release cuando se
modifiquen instalación, paquetes, despliegue o concurrencia.


## Tutorial: Events funciona primero sin bloques

Este recorrido parte de una instalación local de desarrollo terminada, con al menos
un idioma y un mercado activos y un administrador. Usar una copia desechable para
ensayarlo; no ejecutar migraciones de prueba sobre la instalación de trabajo.
Los comandos son para Sail; dentro del contenedor CI usar PHP directamente.

### 1. Generar e inspeccionar

Ejecutar el comando del inicio una sola vez. No sobrescribe `modules/Events` si ya
existe. `Events` es tanto el nombre de clase generado como el nombre de directorio;
no renombrarlo a `Event` a mitad del tutorial.

```bash
./vendor/bin/sail artisan help make:cms-module
./vendor/bin/sail artisan route:list --name=admin.events
```

Antes de instalar, la segunda consulta puede no listar rutas: generar archivos no
selecciona ni instala el módulo. El resultado del generador incluye:

| Archivo o directorio en `modules/Events/` | Responsabilidad real |
| --- | --- |
| `module.json` | Identidad, proveedor, versión de core, permiso `manage:events`; ajustes vacíos. |
| `EventsModuleServiceProvider.php` | Binding del servicio, vistas, migraciones, rutas y navegación. |
| `Models/Events.php`, `Models/EventsTranslation.php` | Fila base y traducciones; relaciones de mercado. |
| `Database/Migrations/*_create_events_tables.php` | Tablas `events`, `events_translations`, `market_events`; FK `events_id`. |
| `Contracts/Services/EventsServiceInterface.php`, `Services/EventsDomainService.php` | Crear, actualizar, borrar y paginar; escrituras relacionadas en transacción. |
| `Http/Controllers/Admin/`, `Routes/admin.php` | Listado, alta, edición, guardado y borrado con `manage:events`. |
| `Resources/views/admin/` | Listado y formularios compartidos de administración. |
| `Http/Controllers/EventsFrontController.php`, `Resources/views/index.blade.php` | Punto de partida público que se sustituye más abajo. |
| `DTOs/` | Directorio vacío; no se generan DTOs automáticamente. |

`--pageable` crea rutas y un listado público filtrado por actividad, mercado e
idioma; no registra un esquema editorial ni revisión de publicación. `--crud` genera formularios y sus acciones. Las otras dos
opciones son independientes de los bloques: `--with-auth` añade usuarios propios y
`--dedicated-panel` implica esa autenticación y añade un portal. Este ejemplo no
las utiliza ni necesita usuarios propios.

La migración generada no contiene fechas de evento, `is_published`, programación
ni revisión editorial. El CRUD guarda `is_active`, `order`, `translations` y
`market_ids`. En este ejemplo mínimo, **activo significa autorizado para aparecer
en el catálogo público**. No equivale a aprobar una revisión en ContentAudit.
Un registro sin mercados asignados es global, siguiendo la política del núcleo.

### 2. Personalizar la lectura pública para compartirla con un bloque

El módulo recién generado ya dispone de un CRUD y listado público operativo:
`paginatePublic()` filtra actividad, mercado e idioma; `paginate()` pertenece a
administración. Comprueba primero el módulo sin añadir bloques. No necesita
reparaciones manuales de rutas, componentes ni autenticación.

Para el ejemplo reutilizamos una presentación específica desde la portada y el
bloque de eventos. La modificación siguiente es una personalización del tutorial,
no un requisito para que funcione el resultado del generador.

Crear `modules/Events/Services/EventsPublicReader.php` con este contenido completo.
Este lector interno no necesita una interfaz adicional: lo utilizan la portada y,
más adelante, el bloque. Selecciona exclusivamente el idioma solicitado y su
traducción genérica o específica de ese mercado; nunca otro idioma como fallback.

```php
<?php

declare(strict_types=1);

namespace Modules\Events\Services;

use App\Core\Modules\ModuleManager;
use App\Models\Market;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Events\Models\Events;

final class EventsPublicReader
{
    public function __construct(private readonly ModuleManager $modules) {}

    /** @return list<array{title: string, content: string}> */
    public function items(Market $market, string $locale, int $limit = 6): array
    {
        if (! $this->modules->isModuleEnabled('events') || ! $market->is_active
            || ! $market->languages()->where('code', $locale)->where('is_active', true)->exists()) {
            return [];
        }

        $translations = static function (Builder|HasMany $query) use ($market, $locale): void {
            $query->where('locale', $locale)
                ->where(static fn (Builder $q) => $q->whereNull('market_id')->orWhere('market_id', $market->id));
        };

        $events = Events::query()->where('is_active', true)
            ->where(static fn (Builder $q) => $q
                ->whereHas('markets', static fn (Builder $markets) => $markets->whereKey($market->id))
                ->orDoesntHave('markets'))
            ->whereHas('translations', $translations)
            ->with(['translations' => $translations])
            ->orderBy('order')->orderBy('id')
            ->limit(min(50, max(1, $limit)))->get();

        $items = [];
        foreach ($events as $event) {
            $translation = $event->translations->firstWhere('market_id', $market->id)
                ?? $event->translations->firstWhere('market_id', null);
            if ($translation !== null) {
                $items[] = ['title' => $translation->title, 'content' => (string) $translation->content];
            }
        }

        return $items;
    }
}
```

Sustituir por completo `modules/Events/Http/Controllers/EventsFrontController.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Events\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use Illuminate\Contracts\View\View;
use Modules\Events\Services\EventsPublicReader;

final class EventsFrontController extends Controller
{
    public function index(EventsPublicReader $reader, FrontViewResolver $views): View
    {
        $market = app('current.market');
        abort_unless($market instanceof Market, 404);

        return view($views->resolve('events.index', 'events::index'), [
            'items' => $reader->items($market, app()->getLocale(), 12),
            'layout' => $views->layout(),
        ]);
    }
}
```

Sustituir por completo `modules/Events/Resources/views/index.blade.php`:

```blade
@extends($layout)

@section('title', 'Events')

@section('content')
<main class="max-w-5xl mx-auto px-4 py-12" data-module="events">
    <h1 class="text-3xl font-bold mb-6">Events</h1>
    <ul class="space-y-4">
        @foreach ($items as $item)
            <li>
                <h2 class="text-xl font-semibold">{{ $item['title'] }}</h2>
                <p class="whitespace-pre-line">{{ $item['content'] }}</p>
            </li>
        @endforeach
    </ul>
</main>
@endsection
```

No hay consultas en Blade ni salida HTML sin escapar. El ejemplo es un listado
acotado de 12 elementos, no un calendario ni una página de detalle por slug.
Para sustituirlo desde el tema activo crear `themes/{id}/events/index.blade.php`
con las mismas variables; ver [temas públicos](17_FRONTOFFICE_THEME_GUIDE.md).

### Rutas y formulario generados

El proveedor utiliza `FrontRoutePatterns::locale()`, reserva el prefijo
administrativo configurado y protege sus rutas con la disponibilidad del módulo.
El formulario usa `admin.switch` con un input oculto `is_active=0`, de modo que
desmarcarlo persiste el estado. Mantén esas garantías al personalizar el módulo.

### 3. Instalar y utilizar el módulo sin bloques

Conservar el manifiesto y el proveedor generados. La lectura y presentación
anteriores son las personalizaciones de este ejemplo. El manifiesto de arriba sirve
como referencia mínima; el generado declara la versión efectiva de core. No es
necesario editar listas del núcleo, ejecutar Composer ni registrar otro proveedor
manualmente. El autoload de `Modules\` ya pertenece a la aplicación.

En el administrador, abrir **Módulos**, localizar **Events** e instalarlo. La
operación explícita ejecuta sus migraciones y lo deja instalado y activo. No
confundirla con activar un módulo todavía no instalado. Tras la siguiente petición:

```bash
./vendor/bin/sail artisan route:list --name=admin.events
./vendor/bin/sail artisan route:list --path=events
```

Con `ADMIN_PATH=admin`, las rutas administrativas son `/admin/events`,
`/admin/events/create`, `/admin/events/{item}/edit`, POST `/admin/events`,
PUT y DELETE `/admin/events/{item}`. Sus nombres son `admin.events.index`,
`create`, `edit`, `store`, `update`, `destroy`. La base del proveedor incorpora
el prefijo administrativo, autenticación y `module.enabled:events`; el archivo
`Routes/admin.php` incorpora `manage:events`. No duplicar estos grupos.

El administrador puede gestionar Events por su rol. El manifiesto no crea filas
en `permissions`, no concede permisos a otros roles y no añade una pantalla para
editar sus permisos. El panel de usuarios permite asignar roles existentes.

Para ensayar un rol limitado, crear opcionalmente
`modules/Events/Database/Seeders/EventsPermissionsSeeder.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Events\Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class EventsPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $role = Role::firstOrCreate(['slug' => 'events-editor'], ['name' => 'Editor de eventos']);
            foreach (['access:admin' => 'Acceder al panel', 'manage:events' => 'Gestionar eventos'] as $slug => $name) {
                $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $name]);
                $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
        });
    }
}
```

Ejecutarlo explícitamente en la copia desechable después de instalar Events:

```bash
./vendor/bin/sail artisan db:seed --class='Modules\Events\Database\Seeders\EventsPermissionsSeeder'
```

Asignar **Editor de eventos** a un usuario de prueba desde la pantalla de usuarios.
El rol concede `access:admin` y `manage:events`, sin convertirlo en administrador
ni conceder gestión de módulos o paquetes. La instalación del módulo no ejecuta
este seeder automáticamente. Un actor sin `manage:events` no debe poder entrar
ni enviar operaciones CRUD.

Crear un evento desde el formulario: título y contenido en los idiomas activos,
mercados de destino, orden y estado activo. Guardar, editar y volver a abrirlo.
El servicio generado crea las traducciones genéricas (`market_id=null`) y sincroniza
los mercados; no elimina una traducción simplemente por omitirla del payload.
La unicidad declarada incluye entidad, idioma y mercado; el servicio utiliza
`updateOrCreate` para las traducciones genéricas. No asumir que el índice SQL con
un mercado nullable impide por sí solo toda duplicación concurrente.

Las rutas públicas generadas son `/events`, `/{locale}/events` y
`/{market}/{locale}/events`; usan literalmente `events`, sin slugs de módulo
traducidos. Probar una combinación existente, por ejemplo `/es/es/events` si
mercado e idioma tienen código `es`. Los valores inexistentes o los idiomas no
activos para ese mercado no deben exponer contenido. Crear también un evento
inactivo, otro asignado a un mercado distinto y otro sin traducción al idioma de
la petición: ninguno debe aparecer. **Events ya funciona sin ningún bloque.**

## Tutorial: un módulo que aporta bloques

### 4. Definir el bloque de listado

Crear los directorios `modules/Events/Blocks` y
`modules/Events/Resources/views/blocks`. El módulo conserva la propiedad de sus
entidades y servicios. El bloque solo configura y presenta su lectura pública;
no necesita `block-package.json` ni un paquete visual paralelo.

Crear `modules/Events/Blocks/EventsListBlock.php`:

```php
<?php

declare(strict_types=1);

namespace Modules\Events\Blocks;

use App\Core\Blocks\AbstractBlockType;
use App\Models\Market;
use App\Services\Cms\FrontViewResolver;
use Modules\Events\Services\EventsPublicReader;

final class EventsListBlock extends AbstractBlockType
{
    public function __construct(private readonly EventsPublicReader $reader) {}

    public function getType(): string
    {
        return 'events_list';
    }

    public function getName(): string
    {
        return 'Listado de eventos';
    }

    public function getIcon(): string
    {
        return 'fa-solid fa-calendar-days';
    }

    public function getCategory(): string
    {
        return 'dynamic';
    }

    public function getDescription(): string
    {
        return 'Eventos disponibles para el mercado e idioma actuales.';
    }

    public function getRequiredModule(): ?string
    {
        return 'events';
    }

    public function isFullWidth(): bool
    {
        return true;
    }

    public function getTranslationPaths(): array
    {
        return ['title'];
    }

    public function getDefaultPayload(): array
    {
        return ['title' => 'Eventos', 'limit' => 6];
    }

    public function getEditorSchema(): array
    {
        return [
            'title' => ['type' => 'text', 'label' => 'Título', 'rules' => ['nullable', 'string', 'max:160']],
            'limit' => ['type' => 'integer', 'label' => 'Máximo de eventos', 'default' => 6,
                'rules' => ['required', 'integer', 'between:1,50']],
        ];
    }

    public function render(array $payload, ?Market $market = null, ?string $locale = null): string
    {
        $market ??= app()->bound('current.market') ? app('current.market') : null;
        if (! $market instanceof Market) {
            return '';
        }
        $views = app(FrontViewResolver::class);

        return view($views->resolve('events.blocks.list', 'events::blocks.list'), [
            'title' => (string) ($payload['title'] ?? ''),
            'items' => $this->reader->items($market, $locale ?? app()->getLocale(), (int) ($payload['limit'] ?? 6)),
        ])->render();
    }
}
```

El tipo `events_list` es único en toda la instalación. `getRequiredModule()` usa
el identificador del manifiesto, no el nombre de clase. `isFullWidth()=true` hace
que el editor lo inserte como sección; para un bloque sencillo insertable dentro
de una columna usar `false`, como la tarjeta de la [guía de bloques](26_CREATING_BLOCKS_AND_VISUAL_PACKAGES_GUIDE.md).

Solo se traduce el título escrito en el bloque. El límite no es texto, y los
nombres de eventos se leen de sus propias traducciones. Si se añaden IDs o slugs
como selectores, no incluirlos en `getTranslationPaths()`. Los selectores dinámicos
pueden usar `getEditorData()` y `options_source`, como
`modules/Blog/Blocks/BlogPostsBlock.php`; deben seguir validándose en servidor.

Crear `modules/Events/Resources/views/blocks/list.blade.php`:

```blade
<div data-block="events_list">
    @if ($title !== '')
        <h2 class="text-2xl font-bold mb-4">{{ $title }}</h2>
    @endif
    <ul class="space-y-4">
        @foreach ($items as $item)
            <li>
                <h3 class="text-lg font-semibold">{{ $item['title'] }}</h3>
                <p class="whitespace-pre-line">{{ $item['content'] }}</p>
            </li>
        @endforeach
    </ul>
</div>
```

Un tema sustituye esta vista con `themes/{id}/events/blocks/list.blade.php`.
La resolución comprueba ese tema y después `events::blocks.list`; no pasa
implícitamente por las vistas de otro tema.

### 5. Registrar el bloque desde su propietario

En `modules/Events/EventsModuleServiceProvider.php`, añadir los imports:

```php
use App\Core\Blocks\Contracts\BlockRegistryInterface;
use Modules\Events\Blocks\EventsListBlock;
```

Sustituir únicamente `boot()` por este método completo. Conservar `register()`,
`registerPageableRoutes()`, propiedades y navegación generados:

```php
public function boot(): void
{
    parent::boot();
    $this->loadViewsFrom(__DIR__.'/Resources/views', 'events');
    $this->loadMigrationsFrom(__DIR__.'/Database/Migrations');
    $this->app->make(BlockRegistryInterface::class)
        ->register($this->app->make(EventsListBlock::class));
}
```

Después de guardar, una nueva petición debe mostrar **Listado de eventos** en el
catálogo del editor. No se ejecuta una instalación nueva para registrar esta clase
ni se crea una segunda tabla de bloques. Si el proyecto utiliza cachés de rutas o
procesos persistentes, seguir su procedimiento local de recarga, no reinstalarlo.

Para aportar varios bloques, crear otra definición con otro `getType()` y registrar
ambas en el mismo proveedor. Todas las que dependan de estos datos deben declarar
`getRequiredModule() = 'events'`. Desactivar Events afecta a todas ellas; no se les
inventa un interruptor independiente de paquete.

### 6. Insertar, guardar y comprobar el ciclo de vida

1. Crear una página del núcleo con traducción al idioma de prueba. En su editor de
   secciones insertar **Listado de eventos**, configurar título y límite y guardar.
   Para verla anónimamente, publicar la página mediante su flujo normal.
2. Abrirla en un mercado/idioma activos. Verificar el listado y que cambiar el
   título de la sección no cambia los títulos de las entidades Events.
3. Cambiar a otro idioma: configurar su sección traducida y sus traducciones de
   eventos. Una sección sin traducción no aparece en ese idioma.
4. Desactivar Events desde **Módulos**. Su portada deja de ser accesible, el bloque
   desaparece del selector y el front no lo ejecuta. La instancia guardada aparece
   como no disponible en el editor. Guardar otro campo de la página la conserva;
   no se permite modificar ni duplicar esa instancia mientras esté desactivada.
5. Reactivar Events. La instancia recupera configuración y renderizado sin volver
   a insertarla. Desinstalar tampoco borra sus tablas ni sus datos; no se utiliza
   esa operación como mecanismo de limpieza de pruebas.

La página pertenece al núcleo: puede contener bloques de Events y de otros
propietarios sin convertirse en una entidad del módulo. Ver
[edición de secciones](15_FRONT_SECTION_EDITING.md) para almacenamiento y estados.

## Integraciones que el ejemplo no genera

| Necesidad | Integración explícita y referencia canónica |
| --- | --- |
| Revisión, aprobación, publicación programada o mutación compartida | Diseñar la política de publicación de Events, registrar `EntitySchema` y su `EntityMutationHandlerInterface`, y llevar los escritores HTTP y agentes al servicio compartido. Ver [registro y revisión editorial](CORE_EXTENSION_REGISTRATION.md). El CRUD directo de este ejemplo no ofrece esa garantía. |
| Secciones dentro de cada evento | Implementar `Sectionable` y sus relaciones y utilizar `SectionSyncService` dentro de la mutación común; consultar `app/Content/Pages` y Blog. Aportar un bloque no hace a la entidad sectionable. |
| Sitemap, menús y URLs de detalle | Crear primero rutas de detalle y registrar `PublicContentDefinition` con una política compatible. Ver [descubrimiento público](GEO_PUBLIC_CONTRIBUTORS.md). Este ejemplo solo ofrece colección, sin enlaces individuales. |
| Texto dinámico del bloque en corpus/SEO | Implementar `PublicWidgetSemanticContributorInterface` y registrarlo en `PublicSemanticRegistry`; Blog comparte la selección pública entre `render()` y `text()`. Registrar un bloque visual no registra esa contribución. |
| Búsqueda | Aportar una fuente e indexación que respeten la misma disponibilidad. Ver [búsqueda general](22_GENERAL_SEARCH.md). |
| Operaciones por IA o MCP | Declarar esquema, handler y capacidades autorizadas y verificar paridad con HTTP. Ver [paridad MCP](23_MCP_BACKOFFICE_PARITY.md). El generador no convierte el CRUD automáticamente en herramientas MCP. |

Si Events se integra después con publicación editorial o fechas, sustituir la
política `is_active` del lector por la política pública compartida. Portada,
bloques, búsqueda y semántica deben utilizar la misma decisión, sin reintroducir
el paginador administrativo como acceso público.

## Diagnóstico y verificación del recorrido

| Síntoma | Comprobación |
| --- | --- |
| Hay archivos pero no navegación | Instalación y disponibilidad reales, proveedor declarado y permiso del actor. Discovery no instala. |
| Error por tipo duplicado | `getType()` debe ser único incluso entre paquetes y módulos. |
| Bloque registrado pero ausente del selector | Estado del módulo y coincidencia exacta de `getRequiredModule()` con `events`. |
| No aparecen datos | Estado activo, mercados asignados, idioma activo para el mercado y traducción disponible; no ampliar la consulta para «arreglarlo». |
| Error al guardar límite | El servidor aplica el esquema; el rango es 1–50, no solo una restricción visual del formulario. |
| Vista no encontrada o personalización ignorada | Namespace `events`, ruta de vista, tema realmente activo y nombre exacto del override. |
| La clase no carga | Namespace `Modules\Events\…`, directorio y mayúsculas coherentes; no copiar clases `BlockPackages\` dentro del módulo. |

Validar primero el módulo sin bloque y después el bloque en una página. Ensayar
alta/edición/borrado y rechazo sin permiso en ambos temas administrativos;
comprobar límites inválidos, idiomas, mercados, estado inactivo, desactivación,
conservación y reactivación. Probar también la vista del módulo y su override.
Los ensayos que instalan extensiones o escriben secciones requieren filesystem y
base de datos desechables; `RefreshDatabase` no aísla archivos.

Las pruebas existentes de `tests/Feature/Blocks`,
`tests/Feature/Ui/BlockEditorCompositionTest.php` y
`tests/Unit/Services/ModuleBlockTranslationDescriptorTest.php` protegen contratos
relacionados, pero ejecutarlas no demuestra por sí solo que el ejemplo copiado
funcione. Registrar aparte la ejecución real del artefacto generado, los comandos,
resultados y límites, siguiendo [TESTING](TESTING.md).
