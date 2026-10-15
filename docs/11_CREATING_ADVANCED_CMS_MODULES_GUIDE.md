# Document 11: Guía Maestra para la Creación de Módulos Avanzados en dbCMS / termoCMS

> **Versión**: 1.0 (LTS Architecture)  
> **Ámbito**: Módulos Desacoplados, Multi-CRUD, Arquitectura Limpia, Conexiones Externas y Reutilización de UI Components  
> **Runtimes**: Docker Sail Containerizado (`./vendor/bin/sail`)

---

Los ejemplos de Clinics son un diseño orientativo, no clases existentes del runtime. Para extender el CMS actual, seguir primero la [guía de mantenimiento](ARCHITECTURE_MAINTENANCE_GUIDE.md), usar sus registros propietarios y verificar la ausencia del módulo.

## 1. Introducción y Filosofía Arquitectónica

En **dbCMS / termoCMS**, un módulo no es un simple plugin o una colección desordenada de controladores. Cada módulo es un **Bounded Context (Contexto Delimitado)** autónomo de Domain-Driven Design (DDD), empaquetado bajo el directorio raíz `/modules/{ModuleName}/` con su propio ciclo de vida, migraciones, modelos, servicios de dominio, controladores invocables, vistas y configuración.

### Principios Fundamentales:
1. **Desacoplamiento Estricto**: Ningún módulo debe depender directamente de las clases internas de otro módulo, excepto mediante contratos (`Contracts`), eventos del sistema (`Events`) o el gestor de módulos (`ModuleManager`).
2. **Clean Architecture (Thin Controllers, Fat Services)**: Los controladores son de acción única (`__invoke`) con un máximo de 10 líneas de código, limitándose a deserializar peticiones en DTOs inmutables, llamar al Servicio de Dominio y responder con una vista o redirección.
3. **Persistencia Dual (Local y Remota)**: Soporte nativo para esquemas relacionales locales multi-tabla, multi-idioma (mercados del CMS) y conexiones dinámicas o estáticas a bases de datos externas (ERPs, CRMs, bases legadas).
4. **Reutilización del Sistema de UI (`<x-admin.*>`)**: Todo el backoffice utiliza el kit de componentes Blade corporativos reutilizables, que se adaptan automáticamente a la marca activa en el archivo `.env` (**dbCMS** ciberpunk o **termoCMS** clínico/corporativo) sin alterar la lógica de plantillas.
5. **Zero-Host Containerization**: Todas las operaciones de creación de código, migraciones y tests se ejecutan dentro del contenedor Sail (`./vendor/bin/sail`).

---

## 2. Estructura Canónica de un Módulo Complejo

A continuación se detalla el árbol de directorios estándar para un módulo que gestione múltiples entidades (por ejemplo, un módulo médico/clínico `Clinics` que contenga sedes clínicas, especialistas y citas):

```
modules/Clinics/
├── module.json                           # Manifiesto del módulo, metadatos y esquema de configuración
├── Database/
│   ├── Migrations/                       # Migraciones locales del módulo
│   │   ├── 2026_09_18_000001_create_clinics_tables.php
│   │   └── 2026_09_18_000002_create_clinic_translations_tables.php
│   └── Seeders/
│       └── ClinicsDatabaseSeeder.php
├── Models/                               # Modelos Eloquent de esquema puro (delgados)
│   ├── Clinic.php
│   ├── ClinicTranslation.php
│   ├── Specialist.php
│   └── ExternalPatient.php               # Modelo conectado a BD externa
├── DTOs/                                 # Clases inmutables de transferencia de datos
│   ├── ClinicData.php
│   ├── SpecialistData.php
│   └── AppointmentBookingData.php
├── Contracts/                            # Interfaces de servicios de dominio
│   ├── ClinicServiceInterface.php
│   └── ExternalCrmSyncInterface.php
├── Services/                             # Fat Services con lógica de negocio y transacciones
│   ├── ClinicService.php
│   └── ExternalCrmDatabaseService.php    # Manejo dinámico de conexiones externas
├── Http/
│   ├── Requests/Admin/                   # Validación de formularios y mapeo a DTOs
│   │   ├── StoreClinicRequest.php
│   │   └── UpdateClinicRequest.php
│   └── Controllers/Admin/                # Controladores Invocables de Acción Única
│       ├── Clinics/
│       │   ├── IndexController.php
│       │   ├── CreateController.php
│       │   ├── StoreController.php
│       │   ├── EditController.php
│       │   ├── UpdateController.php
│       │   └── DestroyController.php
│       ├── Specialists/
│       │   ├── IndexController.php
│       │   └── ...
│       └── ExternalSync/
│           └── SyncPatientsController.php
├── Providers/
│   ├── ClinicsServiceProvider.php        # Service Provider que extiende BaseModuleServiceProvider
│   └── RouteServiceProvider.php
├── Resources/
│   └── views/
│       └── admin/                        # Vistas Blade utilizando componentes <x-admin.*>
│           ├── clinics/
│           │   ├── index.blade.php
│           │   ├── create.blade.php
│           │   └── edit.blade.php
│           └── specialists/
│               └── index.blade.php
├── Routes/
│   ├── admin.php                         # Rutas del backoffice (prefijo /admin/clinics)
│   └── web.php                           # Rutas públicas del frontoffice (si es pageable)
└── Tests/
    ├── Unit/
    └── Feature/
```

---

## 3. Manifiesto `module.json` y Registro del ServiceProvider

### 3.1. Definición de `module.json`

El archivo `module.json` define la identidad del módulo y sus parámetros de configuración en tiempo de ejecución. Permite a los administradores parametrizar credenciales (como conexiones externas a bases de datos) directamente desde la pantalla de *Ajustes de Módulos* del CMS.

```json
{
    "identifier": "clinics",
    "name": "Gestión de Clínicas y Centros Médicos",
    "version": "1.0.0",
    "description": "Módulo avanzado para sedes clínicas, doctores y sincronización externa de pacientes.",
    "provider": "Modules\\Clinics\\Providers\\ClinicsServiceProvider",
    "is_pageable": true,
    "settings": {
        "is_enabled": {
            "type": "boolean",
            "label": "Módulo Habilitado",
            "default": true
        },
        "external_db_host": {
            "type": "text",
            "label": "Host de BD Externa (ERP/CRM)",
            "placeholder": "192.168.1.100",
            "required": false
        },
        "external_db_database": {
            "type": "text",
            "label": "Nombre de Base de Datos Externa",
            "placeholder": "crm_clinica",
            "required": false
        },
        "external_db_username": {
            "type": "text",
            "label": "Usuario BD Externa",
            "required": false
        },
        "external_db_password": {
            "type": "password",
            "label": "Contraseña BD Externa",
            "secret": true,
            "required": false
        }
    }
}
```

#### Ajustes por idioma y por mercado (`{locale}` / `{market}`)

Una clave de `settings` que contenga el placeholder `{locale}` se expande, **al leer**,
en un campo por cada idioma activo de la base de datos; `{market}` hace lo mismo con
los mercados activos. La expansión vive en `ModuleSettingsService::getSchema()`, así
que la vista, las reglas de validación del PUT, `setMany()` y la API MCP heredan el
mismo contrato sin código extra.

```json
"slug_default": {
    "type": "text",
    "label": "Slug base del módulo",
    "default": "blog"
},
"slug_{locale}": {
    "type": "text",
    "label": "Slug en {language} ({locale})",
    "description": "Ruta personalizada para el idioma {language}.",
    "default": "blog",
    "defaults": { "es": "noticias", "en": "news", "ca": "noticies", "fr": "nouvelles" }
}
```

- `{language}` se sustituye por el nombre nativo del idioma; `{locale}` por su código en mayúsculas.
- `defaults` (opcional) da una sugerencia natural por idioma; si no hay entrada se usa `default`.
- El resultado son claves concretas (`slug_es`, `slug_fr`, …) que el resto de la
  aplicación ya sabe leer con `LocalizationService::getModulePrefix()`.
- **Nunca hardcodees `es`/`en`/`ca`** en campos, etiquetas o listas: los idiomas y los
  mercados siempre se leen de la base de datos (`LocalizationService`), y el backoffice
  reacciona al instante a cualquier cambio.

### 3.2. Implementación de `ClinicsServiceProvider`

El ServiceProvider debe extender `App\Core\Modules\BaseModuleServiceProvider`. Implementa la carga de migraciones, rutas, vistas y la inyección en la barra de navegación del backoffice:

```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\Providers;

use App\Core\Modules\BaseModuleServiceProvider;
use Modules\Clinics\Contracts\ClinicServiceInterface;
use Modules\Clinics\Services\ClinicService;

class ClinicsServiceProvider extends BaseModuleServiceProvider
{
    protected string $identifier = 'clinics';
    protected string $name = 'Clínicas';
    protected string $version = '1.0.0';
    protected bool $isPageable = true;

    public function register(): void
    {
        // Enlazar interfaces de dominio a sus implementaciones concretas
        $this->app->bind(ClinicServiceInterface::class, ClinicService::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Cargar migraciones del módulo
        $this->loadMigrationsFrom(__DIR__ . '/../Database/Migrations');

        // Cargar vistas del módulo bajo el namespace clinics::
        $this->loadViewsFrom(__DIR__ . '/../Resources/views', 'clinics');

        // Registrar rutas de administración
        $this->loadRoutesFrom(__DIR__ . '/../Routes/admin.php');

        // Si el módulo publica páginas en el frontoffice
        if ($this->isPageable()) {
            $this->loadRoutesFrom(__DIR__ . '/../Routes/web.php');
        }
    }

    /**
     * Define los elementos de navegación para el sidebar del CMS.
     * El CMS inyectará automáticamente estos enlaces en "Extensiones y Módulos".
     */
    public function getAdminNavigation(): array
    {
        return [
            [
                'title' => 'Clínicas y Sedes',
                'route' => 'admin.clinics.index',
                'icon' => 'fa-solid fa-hospital',
                'createRoute' => 'admin.clinics.create',
                'createTooltip' => 'Nueva Clínica',
                'children' => [
                    [
                        'title' => 'Especialistas Médicos',
                        'route' => 'admin.clinics.specialists.index',
                        'icon' => 'fa-solid fa-user-doctor',
                    ],
                    [
                        'title' => 'Sincronización Externa',
                        'route' => 'admin.clinics.external-sync.index',
                        'icon' => 'fa-solid fa-database',
                    ],
                ],
            ],
        ];
    }
}
```

---

## 4. Persistencia: Esquema Local Multi-Tabla y Traducciones Multi-Mercado

### 4.1. Migración Relacional con Soporte Multi-Mercado

El CMS utiliza una arquitectura multilingüe por mercados (`markets` y `locales`). Para que las entidades del módulo soporten múltiples idiomas y regiones sin redundancia, se divide la entidad en su tabla base de metadatos y una tabla hija de traducciones:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla Principal de Sedes Clínicas
        Schema::create('clinics', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique(); // Identificador interno (ej. CLI-MAD-01)
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('latitude')->nullable();
            $table->string('longitude')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->integer('order')->default(0);
            $table->timestamps();
        });

        // 2. Tabla de Traducciones por Mercado / Idioma
        Schema::create('clinic_translations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->foreignId('market_id')->nullable()->constrained('markets')->nullOnDelete();
            $table->string('locale', 10)->index();
            $table->string('name');
            $table->string('slug')->index();
            $table->string('address')->nullable();
            $table->text('description')->nullable();
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->timestamps();

            $table->unique(['clinic_id', 'locale', 'market_id']);
        });

        // 3. Tabla Secundaria (Especialistas)
        Schema::create('clinic_specialists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->string('name');
            $table->string('medical_license_number')->nullable();
            $table->string('specialty');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinic_specialists');
        Schema::dropIfExists('clinic_translations');
        Schema::dropIfExists('clinics');
    }
};
```

### 4.2. Modelos Eloquent Delgados

Los modelos deben ser puros y estrictamente declarativos:

```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\Models;

use App\Models\Market;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Clinic extends Model
{
    protected $table = 'clinics';

    protected $fillable = [
        'code',
        'phone',
        'email',
        'latitude',
        'longitude',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    public function translations(): HasMany
    {
        return $this->hasMany(ClinicTranslation::class, 'clinic_id');
    }

    public function specialists(): HasMany
    {
        return $this->hasMany(Specialist::class, 'clinic_id');
    }

    public function translation(?string $locale = null, ?int $marketId = null): ?ClinicTranslation
    {
        $locale = $locale ?? app()->getLocale();
        return $this->translations
            ->where('locale', $locale)
            ->when($marketId, fn ($q) => $q->where('market_id', $marketId))
            ->first() ?? $this->translations->first();
    }
}
```

---

## 5. Conexiones a Bases de Datos Externas

Un módulo complejo frecuentemente necesita interactuar con orígenes de datos externos (ERPs médicos, SAP, CRMs o bases de datos de clientes). Existen dos estrategias según el escenario:

### 5.1. Enfoque A: Conexión Estática en `config/database.php`

Cuando la base de datos externa es fija para todo el entorno:

1. **Registrar conexión en `config/database.php`**:
```php
'connections' => [
    // ...
    'external_erp' => [
        'driver' => 'mysql',
        'url' => env('DB_EXTERNAL_ERP_URL'),
        'host' => env('DB_EXTERNAL_ERP_HOST', '127.0.0.1'),
        'port' => env('DB_EXTERNAL_ERP_PORT', '3306'),
        'database' => env('DB_EXTERNAL_ERP_DATABASE', 'erp_database'),
        'username' => env('DB_EXTERNAL_ERP_USERNAME', 'erp_user'),
        'password' => env('DB_EXTERNAL_ERP_PASSWORD', ''),
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
        'strict' => true,
    ],
],
```

2. **Asignar la conexión en el Modelo**:
```php
namespace Modules\Clinics\Models;

use Illuminate\Database\Eloquent\Model;

class ExternalPatient extends Model
{
    protected $connection = 'external_erp';
    protected $table = 'pacientes';
    protected $primaryKey = 'id_paciente';
    public $timestamps = false;
}
```

### 5.2. Enfoque B: Conexión Dinámica en Runtime desde `cms_modules.settings`

Cuando las credenciales de la base de datos externa son configurables por el usuario administrador desde la interfaz del CMS:

```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\Services;

use App\Core\Modules\ModuleManager;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ExternalCrmDatabaseService
{
    public function __construct(
        protected ModuleManager $moduleManager
    ) {}

    /**
     * Configura y devuelve la conexión PDO a la base de datos externa configurada.
     */
    public function getConnection(): Connection
    {
        $manifest = $this->moduleManager->getModuleManifest('clinics');
        $host = config('modules.clinics.external_db_host');
        $database = config('modules.clinics.external_db_database');
        $username = config('modules.clinics.external_db_username');
        $password = config('modules.clinics.external_db_password');

        if (empty($host) || empty($database)) {
            throw new RuntimeException('Las credenciales de la base de datos externa no están configuradas en el módulo Clínicas.');
        }

        $connectionName = 'dynamic_clinics_crm';

        // Configurar conexión en tiempo de ejecución
        Config::set("database.connections.{$connectionName}", [
            'driver' => 'mysql',
            'host' => $host,
            'port' => 3306,
            'database' => $database,
            'username' => $username,
            'password' => $password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'timeout' => 5, // Timeout estricto para evitar bloqueos
        ]);

        // Purgar caché de conexión para aplicar cambios
        DB::purge($connectionName);

        /** @var Connection */
        return DB::connection($connectionName);
    }

    /**
     * Ejemplo de consulta remota segura.
     */
    public function fetchRecentPatients(int $limit = 50): array
    {
        return $this->getConnection()
            ->table('pacientes')
            ->select('id', 'nombre_completo', 'dni', 'fecha_alta')
            ->orderByDesc('fecha_alta')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}
```

---

## 6. Múltiples CRUDs: Controladores Invocables y Fat Services

Siguiendo la especificación de Clean Architecture del proyecto, **está prohibido crear controladores con métodos múltiples (`index`, `create`, `store`)**. Cada verbo y acción HTTP reside en su propia clase invocable.

### 6.1. DTO Inmutable (`ClinicData`)

```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\DTOs;

readonly class ClinicData
{
    public function __construct(
        public string $code,
        public ?string $phone,
        public ?string $email,
        public ?string $latitude,
        public ?string $longitude,
        public bool $isActive,
        public array $translations, // ['es' => ['name' => '...', 'address' => '...'], 'en' => ...]
    ) {}
}
```

### 6.2. Fat Domain Service (`ClinicService`)

Toda la lógica de persistencia, validación de negocio y emisión de eventos ocurre dentro de `DB::transaction`:

```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Clinics\Contracts\ClinicServiceInterface;
use Modules\Clinics\DTOs\ClinicData;
use Modules\Clinics\Models\Clinic;

class ClinicService implements ClinicServiceInterface
{
    public function createClinic(ClinicData $dto): Clinic
    {
        return DB::transaction(function () use ($dto): Clinic {
            /** @var Clinic $clinic */
            $clinic = Clinic::create([
                'code' => strtoupper($dto->code),
                'phone' => $dto->phone,
                'email' => $dto->email,
                'latitude' => $dto->latitude,
                'longitude' => $dto->longitude,
                'is_active' => $dto->isActive,
            ]);

            foreach ($dto->translations as $locale => $trans) {
                $clinic->translations()->create([
                    'locale' => $locale,
                    'market_id' => $trans['market_id'] ?? null,
                    'name' => $trans['name'],
                    'slug' => Str::slug($trans['name']),
                    'address' => $trans['address'] ?? null,
                    'description' => $trans['description'] ?? null,
                    'meta_title' => $trans['meta_title'] ?? $trans['name'],
                    'meta_description' => $trans['meta_description'] ?? null,
                ]);
            }

            return $clinic->load('translations');
        });
    }

    public function deleteClinic(Clinic $clinic): bool
    {
        return (bool) DB::transaction(fn () => $clinic->delete());
    }
}
```

### 6.3. Form Request (`StoreClinicRequest`) con `toDTO()`

```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Clinics\DTOs\ClinicData;

class StoreClinicRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:50', 'unique:clinics,code'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:100'],
            'is_active' => ['boolean'],
            'translations' => ['required', 'array'],
            'translations.*.name' => ['required', 'string', 'max:255'],
            'translations.*.address' => ['nullable', 'string'],
        ];
    }

    public function toDTO(): ClinicData
    {
        return new ClinicData(
            code: (string) $this->validated('code'),
            phone: $this->validated('phone'),
            email: $this->validated('email'),
            latitude: $this->validated('latitude'),
            longitude: $this->validated('longitude'),
            isActive: (bool) $this->boolean('is_active', true),
            translations: (array) $this->validated('translations'),
        );
    }
}
```

### 6.4. Controladores Invocables Delgados ($\le 8$ Líneas)

#### `IndexController.php`:
```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\Http\Controllers\Admin\Clinics;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Modules\Clinics\Models\Clinic;

class IndexController extends Controller
{
    public function __invoke(Request $request): View
    {
        $query = Clinic::with('translations');

        if ($search = $request->input('search')) {
            $query->where('code', 'LIKE', "%{$search}%")
                  ->orWhereHas('translations', fn ($q) => $q->where('name', 'LIKE', "%{$search}%"));
        }

        $clinics = $query->latest('id')->paginate(15)->withQueryString();

        return view('clinics::admin.clinics.index', compact('clinics'));
    }
}
```

#### `StoreController.php`:
```php
<?php

declare(strict_types=1);

namespace Modules\Clinics\Http\Controllers\Admin\Clinics;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Clinics\Contracts\ClinicServiceInterface;
use Modules\Clinics\Http\Requests\Admin\StoreClinicRequest;

class StoreController extends Controller
{
    public function __invoke(StoreClinicRequest $request, ClinicServiceInterface $service): RedirectResponse
    {
        $clinic = $service->createClinic($request->toDTO());

        return redirect()
            ->route('admin.clinics.index')
            ->with('success', "Clínica '{$clinic->code}' dada de alta correctamente.");
    }
}
```

---

## 7. Reutilización Exhaustiva del UI Kit (`<x-admin.*>`)

El CMS dispone de un catálogo de componentes Blade listos para usar en `resources/views/components/admin/`. Estos componentes se adaptan automáticamente entre **dbCMS** y **termoCMS**:

| Componente | Etiqueta Blade | Propósito |
| :--- | :--- | :--- |
| **Datatable Header** | `<x-admin.datatable-header>` | Barra de búsqueda, conteo total y botón de acción nuevo. |
| **Tabla Estilizada** | `<x-admin.table>` | Contenedor de tabla responsiva con slots `head`, filas y `pagination`. |
| **Badge de Estado** | `<x-admin.status-badge>` | Píldora de estado (Activo, Inactivo, Pendiente, etc.) adaptada al tema. |
| **Tarjeta de Formulario**| `<x-admin.form-card>` | Agrupador modular de campos con título, subtítulo e icono. |
| **Input de Texto** | `<x-admin.input>` | Campo de formulario con soporte para errores de validación. |
| **Textarea** | `<x-admin.textarea>` | Área de texto multilinea estilizada. |
| **Select** | `<x-admin.select>` | Selector dropdown nativo o asistido. |
| **Interruptor Toggle** | `<x-admin.toggle>` | Switch interactivo Alpine.js (verde neón en dbCMS, cian médico en termoCMS). |
| **Acciones de Formulario**| `<x-admin.form-actions>`| Barra inferior con botones *Guardar*, *Cancelar* y *Eliminar Registro*. |
| **Modal de Confirmación** | `<x-admin.confirm-modal>`| Diálogo global Alpine.js para eliminaciones y acciones críticas. |
| **Estado Vacío** | `<x-admin.empty-state>` | Pantalla ilustrada cuando no hay registros tras un filtrado. |

### 7.1. Plantilla de Listado (`index.blade.php`)

```blade
@extends('admin.layout')

@section('content')
<div class="space-y-6">
    <!-- Header con Búsqueda y Botón Nuevo -->
    <x-admin.datatable-header
        title="Centros Médicos y Clínicas"
        subtitle="Listado y control de disponibilidad de sedes sanitarias"
        icon="fa-solid fa-hospital"
        :count="$clinics->total()"
        search-placeholder="Buscar por nombre o código..."
        create-route="{{ route('admin.clinics.create') }}"
        create-label="Nueva Clínica"
    />

    <!-- Tabla de Registros -->
    <x-admin.table :records="$clinics">
        <x-slot:head>
            <th class="px-4 py-3 text-left">Código</th>
            <th class="px-4 py-3 text-left">Nombre Comercial</th>
            <th class="px-4 py-3 text-left">Teléfono</th>
            <th class="px-4 py-3 text-center">Estado</th>
            <th class="px-4 py-3 text-right">Acciones</th>
        </x-slot:head>

        @forelse($clinics as $clinic)
            <tr class="border-b border-gray-800/40 hover:bg-gray-800/20 transition-colors">
                <td class="px-4 py-3 font-mono font-bold">{{ $clinic->code }}</td>
                <td class="px-4 py-3 font-semibold">{{ $clinic->translation()?->name ?? '—' }}</td>
                <td class="px-4 py-3">{{ $clinic->phone ?? '—' }}</td>
                <td class="px-4 py-3 text-center">
                    <x-admin.status-badge :active="$clinic->is_active" />
                </td>
                <td class="px-4 py-3 text-right space-x-2">
                    <a href="{{ route('admin.clinics.edit', $clinic) }}" class="text-sky-400 hover:underline">
                        <i class="fa-solid fa-pen-to-square"></i>
                    </a>
                    <button type="button"
                            @click="$dispatch('confirm-action', {
                                action: '{{ route('admin.clinics.destroy', $clinic) }}',
                                method: 'DELETE',
                                title: 'Eliminar Clínica',
                                message: '¿Estás seguro de que deseas eliminar la sede {{ $clinic->code }}?',
                                buttonText: 'Eliminar Definitivamente',
                                buttonClass: 'btn-neon bg-red-600 border-red-500'
                            })"
                            class="text-red-400 hover:text-red-300">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </td>
            </tr>
        @empty
            <tr>
                <td colspan="5">
                    <x-admin.empty-state
                        icon="fa-solid fa-hospital"
                        title="No se encontraron sedes clínicas"
                        description="Prueba con otros términos de búsqueda o da de alta un nuevo centro."
                        action-route="{{ route('admin.clinics.create') }}"
                        action-label="Crear Primera Clínica"
                    />
                </td>
            </tr>
        @endforelse

        <x-slot:pagination>
            {{ $clinics->links() }}
        </x-slot:pagination>
    </x-admin.table>
</div>

<!-- Modal Global de Confirmación -->
<x-admin.confirm-modal />
@endsection
```

### 7.2. Plantilla de Formulario (`create.blade.php`)

```blade
@extends('admin.layout')

@section('content')
<form action="{{ route('admin.clinics.store') }}" method="POST" class="space-y-6 max-w-4xl mx-auto">
    @csrf

    <x-admin.form-card title="Datos Principales" subtitle="Identificación corporativa y de contacto" icon="fa-solid fa-hospital">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <x-admin.input name="code" label="Código Identificador" placeholder="CLI-MAD-01" required />
            <x-admin.input name="phone" label="Teléfono de Contacto" placeholder="+34 91 000 00 00" />
            <x-admin.input type="email" name="email" label="Correo Electrónico" placeholder="info@clinica.es" />
            
            <div class="pt-6">
                <x-admin.toggle name="is_active" label="Sede Activa" description="Disponible en el directorio público y buscador" checked />
            </div>
        </div>
    </x-admin.form-card>

    <x-admin.form-card title="Información Multi-idioma" subtitle="Detalles visibles según el idioma de navegación" icon="fa-solid fa-language">
        <div class="space-y-4">
            <x-admin.input name="translations[es][name]" label="Nombre (Español)" placeholder="Clínica Central Madrid" required />
            <x-admin.textarea name="translations[es][address]" label="Dirección Física (Español)" placeholder="Calle Serrano 120, 28006 Madrid" rows="2" />
        </div>
    </x-admin.form-card>

    <x-admin.form-actions
        cancel-route="{{ route('admin.clinics.index') }}"
        submit-label="Guardar Clínica"
    />
</form>
@endsection
```

---

## 8. Verificación y Testing con Pest en Sail

Siguiendo el protocolo **Zero-Host**, nunca ejecutes `pest` o `artisan` en el host:

```bash
# 1. Ejecutar las migraciones del módulo
./vendor/bin/sail artisan migrate --path=modules/Clinics/Database/Migrations

# 2. Ejecutar los tests de la entidad
./vendor/bin/sail pest modules/Clinics/Tests

# 3. Validar tipado estricto con PHPStan Nivel 8/9
./vendor/bin/sail bin phpstan analyse modules/Clinics --level=8
```

### Ejemplo de Test de Integración con Pest (`ClinicsCrudTest.php`):

```php
<?php

use App\Models\User;
use Modules\Clinics\Models\Clinic;

beforeEach(function (): void {
    $this->admin = User::factory()->create(['role' => 'admin']);
});

test('un administrador puede listar y crear sedes clínicas', function (): void {
    $this->actingAs($this->admin)
        ->get(route('admin.clinics.index'))
        ->assertOk()
        ->assertSee('Centros Médicos y Clínicas');

    $payload = [
        'code' => 'CLI-TEST-01',
        'phone' => '123456789',
        'is_active' => '1',
        'translations' => [
            'es' => [
                'name' => 'Clínica de Pruebas',
                'address' => 'Avenida Principal 1',
            ],
        ],
    ];

    $this->actingAs($this->admin)
        ->post(route('admin.clinics.store'), $payload)
        ->assertRedirect(route('admin.clinics.index'));

    expect(Clinic::where('code', 'CLI-TEST-01')->exists())->toBeTrue();
});
```

---

## 9. Inferencia y Arquitectura Zero-Knowledge entre Módulos

En un CMS modular de escala empresarial, **ningún módulo núcleo debe conocer la existencia de otros módulos**. Desinstalar retira disponibilidad y conserva datos; eliminar una carpeta es una operación distinta. Las nuevas fronteras usan contratos y adaptadores neutros. Las combinaciones compatibles deben comprobarse; no hay una garantía global sobre cualquier eliminación física. Véase [mantenimiento](ARCHITECTURE_MAINTENANCE_GUIDE.md).

### 9.1. Inyección Dinámica de Relaciones Eloquent
En lugar de importar traits o relaciones en los modelos de `Pages`, `Blog` o `Shop`, el módulo extensor registra la relación en tiempo de arranque dentro de su `ServiceProvider::boot()`:

```php
// En ContentAuditModuleServiceProvider.php:
public function boot(): void
{
    parent::boot();

    // Inyección no intrusiva de relación polimórfica en modelos auditables
    foreach ([Page::class, Post::class, Product::class] as $modelClass) {
        if (class_exists($modelClass)) {
            $modelClass::resolveRelationUsing('audits', function ($model) {
                return $model->morphMany(ContentAudit::class, 'auditable');
            });
        }
    }
}
```

### 9.2. Observadores Autónomos del Ciclo de Vida
El módulo extensor escucha los eventos `saving`, `saved` o `deleted` registrando observadores directamente sobre las clases del núcleo:

```php
foreach ([Page::class, Post::class, Product::class] as $modelClass) {
    if (class_exists($modelClass)) {
        $modelClass::observe(ContentAuditObserver::class);
    }
}
```

### 9.3. Inyección de UI mediante View Composers y Stacks Blade
El módulo extensor no modifica las plantillas `.blade.php` de los módulos base. En su lugar, utiliza View Composers de Laravel para inyectar su interfaz en los stacks globales del layout:

- `@stack('admin_form_sidebar')`: Inyecta tarjetas de revisión editorial, auditorías o métricas en la barra lateral de cualquier formulario.
- `@stack('admin_modals')`: Inyecta modales globales como el Copiloto de IA o gestores multimedia.
- `@stack('admin_scripts')`: Inyecta librerías JS o puentes de interacción.

```php
// En ContentAuditModuleServiceProvider.php:
View::composer(['admin.pages.edit', 'blog::admin.posts.edit', 'shop::admin.products.edit'], function ($view) {
    // Inyecta el widget de auditoría sin que el formulario original lo sepa
});
```

### 9.4. Eventos DOM Desacoplados en el Frontend
Para interactuar con interfaces complejas (por ejemplo, permitir que un asistente de IA inserte bloques en el `BlockManager`), se utilizan eventos nativos del navegador (`CustomEvent`), evitando llamadas directas a métodos internos:

```javascript
// El Asistente de IA despacha el bloque generado:
window.dispatchEvent(new CustomEvent('cms-insert-block', {
    detail: {
        type: 'hero',
        position: 0,
        payload: { title: 'Título Generado' }
    }
}));
```

---

## 10. Resumen del Flujo de Creación de un Nuevo Módulo

```
+---------------------------------------------------------------------------------------------------+
| 1. CREAR DIRECTORIO Y MANIFIESTO                                                                  |
|    - modules/{Nombre}/module.json con "identifier", "provider", "settings"                       |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 2. REGISTRAR EL SERVICE PROVIDER                                                                  |
|    - Extender BaseModuleServiceProvider e implementar getAdminNavigation()                       |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 3. MIGRACIONES Y MODELOS DELGADOS                                                                 |
|    - Tablas locales con soporte multi-mercado o conexión externa ($connection = '...')           |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 4. SERVICIOS DE DOMINIO (FAT SERVICES) Y DTOs                                                     |
|    - Todo cambio de estado atómico con DB::transaction() y DTOs inmutables                        |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 5. CONTROLADORES INVOCABLES DE ACCIÓN ÚNICA                                                       |
|    - IndexController, StoreController, EditController, UpdateController, DestroyController       |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 6. VISTAS BLADE REUTILIZANDO <x-admin.*>                                                          |
|    - Compatible automáticamente con dbCMS (Cyberpunk) y termoCMS (Corporativo Blanco)             |
+---------------------------------------------------------------------------------------------------+
                                                  |
                                                  v
+---------------------------------------------------------------------------------------------------+
| 7. VERIFICACIÓN Y TESTING DENTRO DE SAIL                                                          |
|    - ./vendor/bin/sail pest y PHPStan Nivel 8/9                                         |
+---------------------------------------------------------------------------------------------------+
```

---

## 12. Admin UI Kit & Andamiaje CRUD coherente

Todo módulo debe construir su backoffice con el **Admin UI Framework**
([`docs/13_ADMIN_UI_FRAMEWORK.md`](13_ADMIN_UI_FRAMEWORK.md)). Reutilizar, nunca copiar y pegar:

- **Listados**: `datatable-header` + `table` + `datatable-pagination`; y chips
  `market-chips` / `locale-chips` cuando la entidad sea multi-mercado/multi-idioma.
- **Formularios**: `form-header` + `form-card` + campos (`input`/`select`/`textarea`/`toggle`) + `form-actions`.
- **Modales**: `modal` / `confirm-modal` (evento global `confirm-delete`).
- **Mercado ↔ idioma**: únicamente `market-picker` + `locale-tabs` con el **mismo `group`**;
  prohibido reimplementar `isLanguageActive` / `toggleMarket`.
- **Ajustes del módulo**: declararlos por `type` en `module.json`
  (`market_select`, `market_multiselect`, `language_select`, `language_multiselect`,
  `entity_select`/`entity_multiselect` con `"source"`, …). El renderer genérico elige el componente.

### Andamiaje

```bash
./vendor/bin/sail artisan make:cms-module Portfolio --crud --pageable
```

Genera el módulo con `index` (tabla + kit) y `create` / `edit` / `_form` ya maquetados con los componentes.

El andamiaje nace **traducible y multi-mercado**, sin columnas de texto en la tabla base:

- Migración limpia (un único archivo): tabla base (`is_active`, `order`),
  `{singular}_translations` (`locale` + `market_id` nullable, `title`/`slug`/`content`)
  y pivote `market_{singular}`.
- Modelo con `translations()`, `markets()`, `translation($locale, $market)` y accesores
  `title`/`slug`/`content` resueltos por idioma (con override por mercado).
- Servicio de dominio que normaliza `translations[locale][...]` y sincroniza mercados
  dentro de `DB::transaction()`.
- Controladores invocables (`Get`/`CreateGet`/`Post`/`EditGet`/`Put`/`Delete`) + trait de
  validación + `Routes/admin.php` protegido con `CheckPermission:manage:{identifier}`.
- Formulario `_form` con `locale-tabs` + `market-picker` y campos por idioma; listado con
  `market-chips` / `locale-chips`.
- El proveedor llama a `parent::boot()`, de modo que `Routes/admin.php` se registra solo.

Sin `--crud` se genera únicamente el listado (`GetController` + `index`) y su ruta.

## 11. Formularios públicos: framework de campos, honeypot y captcha

El renderizado y la validación de campos son **infraestructura del core**
(`App\Core\Forms`, ver [`21_FORM_RENDERING_AND_FIELD_TYPES.md`](21_FORM_RENDERING_AND_FIELD_TYPES.md)):
un mismo componente pinta un formulario de BD, un array declarado en Blade o un
JSON, y una misma definición (schema) alimenta la vista y la validación.

```blade
<x-cms-form.fields
    :schema="app(\App\Core\Forms\FormSchemas::class)->blogComment()"
    variant="comment"
    layout="grid"
    honeypot="_hp_security_check"
/>
```

Reglas de integración:

- Añade un tipo de campo registrándolo en el `FieldTypeRegistry` desde el
  `boot()` del módulo; nunca ramifiques por tipo en la vista ni en el servicio.
- El honeypot se declara con el prop `honeypot` del componente (nunca a mano).
- El **captcha es un tipo de campo** (`captcha`): solo existe mientras el módulo
  Captcha está activo, el admin lo añade en el constructor y el framework pinta y
  valida lo que el formulario declara. No hay ningún interruptor global ni en
  `.env` ni en `config/`.
- Los proveedores del captcha (interno, Google reCAPTCHA v3, Cloudflare Turnstile
  o *ambos*) se eligen en los ajustes del módulo; con *ambos* el envío pasa si
  supera cualquiera de los dos.
- Regla de formato: devuelve **una regla por entrada** (el framework divide los
  strings con `|`, que Laravel ya no divide dentro de un array).

