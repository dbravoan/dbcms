# Guía Canónica de Estándares, Convenciones y Clean Architecture en dbCMS

> **Framework**: Laravel 13 / Enterprise Modular Architecture  
> **Patrón Arquitectónico**: Clean Architecture + Domain-Driven Design (DDD) Bounded Contexts  
> **Regla de Ejecución**: Zero-Host Containerization (`./bin/sail-run`)  
> **Calidad Estática**: PHPStan Nivel 8 (0 errores) + Laravel Pint (Strict) + Pest PHP  

---

## 1. Filosofía Arquitectónica Fundamental

Esta guía establece las convenciones estrictas y de obligado cumplimiento para cualquier desarrollador (humano o agente de IA) que contribuya al código de **dbCMS** (y su variante de marca **termoCMS**).

La arquitectura vigente y sus recorridos están en [Arquitectura](01_ARCHITECTURE_AND_CLEAN_CODE.md) y [Mantenimiento](ARCHITECTURE_MAINTENANCE_GUIDE.md). Los ejemplos de módulos de este documento son orientativos; no sustituyen los contratos y servicios compartidos del código.

### Principios Fundamentales
1. **Separación Estricta de Responsabilidades (SoC)**:
   - **Capa de Presentación (Delgada / Thin)**: Controladores y Requests. Únicamente deserializan la entrada HTTP, llaman al servicio de dominio y devuelven una vista o respuesta JSON.
   - **Capa de Dominio (Robusta / Fat)**: Servicios de Dominio, DTOs inmutables, Interfaces y Excepciones semánticas. Toda la lógica de negocio y mutación transaccional reside aquí.
   - **Capa de Persistencia (Esquema Puro)**: Modelos Eloquent. Definen únicamente `$fillable`, `$casts`, relaciones y scopes de consulta elementales. Cero algoritmos o efectos secundarios.
2. **Arquitectura Modular Desacoplada (Zero-Knowledge)**:
   - El núcleo consume contratos de extensión; los módulos registran adaptadores propietarios. Una dependencia explícita de otro módulo debe declararse y comprobar su disponibilidad.
   - Las nuevas reglas editoriales y de IA no se copian en Pages/Blog/Shop: se consumen a través de contratos y del ejecutor común. Los adaptadores heredados se extraen al modificar su área; las pruebas tienen un alcance explícito.
   - La inferencia y enriquecimiento funcional entre módulos se realiza exclusivamente mediante:
     - **Contratos y registros propietarios** de entidades, capacidades, settings, notificaciones y contribuidores públicos.
     - **Observadores Dinámicos** (`saving`, `saved`, etc.) registrados en el `boot()` del proveedor del módulo extensor.
     - **Relaciones Dinámicas Eloquent** (`resolveRelationUsing`).
     - **View Composers de Blade** inyectando marcado de forma no intrusiva en `@stack('admin_form_sidebar')`, `@stack('admin_modals')` o `@stack('admin_scripts')`.
     - **Eventos DOM Desacoplados** en JavaScript (ej. `window.dispatchEvent(new CustomEvent('cms-insert-block', { detail: { ... } }))`).
3. **Zero-Host Execution**:
   - **Jamás** ejecutar `php`, `composer`, `artisan`, `pest` o `pint` directamente en el sistema operativo anfitrión.
   - Toda invocación de herramientas CLI se ejecuta a través del puente containerizado:
     ```bash
     ./bin/sail-run php artisan <comando>
     ./bin/sail-run ./vendor/bin/pest
     ./bin/sail-run ./vendor/bin/phpstan
     ./bin/sail-run ./vendor/bin/pint
     ```
4. **Respeto a la Configuración y Cacheo**:
   - **Prohibido usar `env()` fuera del directorio `config/`**. Siempre emplear `config('cms.propiedad', 'default')`. De lo contrario, `php artisan config:cache` romperá la aplicación en producción devolviendo `null`.

---

## 2. Convenciones de Controladores (Thin Controllers)

Los controladores siguen el patrón **Single-Action Invokable Controller** con sufijo del verbo HTTP correspondiente.

### 2.1. Nomenclatura Estándar
| Verbo HTTP | Propósito | Nomenclatura Canónica |
| :--- | :--- | :--- |
| **GET** | Listados / Índices | `[Recurso]GetController` / `IndexGetController` |
| **GET** | Formulario de Creación | `[Recurso]CreateGetController` / `CreateGetController` |
| **POST** | Guardar Nuevo Registro | `[Recurso]PostController` / `StorePostController` |
| **GET** | Formulario de Edición | `[Recurso]EditGetController` / `EditGetController` |
| **PUT / PATCH** | Actualizar Registro | `[Recurso]PutController` / `UpdatePutController` |
| **DELETE** | Eliminar Registro | `[Recurso]DeleteController` / `DestroyDeleteController` |

### 2.2. Reglas de Implementación
- **Límite de líneas**: El método `__invoke()` debe tener un **máximo de 10 líneas de código**.
- **Prohibido código SQL o consultas Eloquent complejas**: Los controladores no construyen queries `where()->join()->get()`.
- **Inyección de Dependencias**: Los servicios requeridos se inyectan en el `__construct()` mediante propiedades `private readonly`.

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Modules\Blog\Contracts\Services\PostDomainServiceInterface;
use Modules\Blog\Http\Requests\Admin\StorePostRequest;

final class PostStorePostController extends Controller
{
    public function __construct(
        private readonly PostDomainServiceInterface $postService
    ) {}

    public function __invoke(StorePostRequest $request): RedirectResponse
    {
        $post = $this->postService->create($request->toDTO());

        return redirect()->route('admin.blog.posts.edit', $post->id)
            ->with('success', __('Artículo creado exitosamente.'));
    }
}
```

---

## 3. Capa de Dominio (Fat Services & DTOs)

### 3.1. Servicios de Dominio

Para entidades declaradas, reutilizar `App\Services\Capabilities\EntityCrudService` y handlers propietarios: el guardado común completa traducciones, bloques, mercados e historial editorial dentro de la transacción. El ejemplo simplificado siguiente ilustra persistencia; **no debe copiarse como ruta alternativa** que omita autorización, revisión o confirmación.
- Cada servicio implementa su interfaz contract (`App\Contracts\Services\*Interface` o `Modules\{Modulo}\Contracts\Services\*Interface`).
- Las mutaciones de base de datos que afecten a más de una tabla o relación deben envolverse en transacciones atómicas `DB::transaction()`.
- Lanzan excepciones de dominio semánticas (`DomainServiceException`, `ValidationException`, etc.) ante estados no válidos.

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\Services;

use Illuminate\Support\Facades\DB;
use Modules\Blog\Contracts\Services\PostDomainServiceInterface;
use Modules\Blog\DTOs\PostDataDTO;
use Modules\Blog\Models\Post;

final class PostDomainService implements PostDomainServiceInterface
{
    public function create(PostDataDTO $dto): Post
    {
        return DB::transaction(function () use ($dto): Post {
            $post = Post::create([
                'user_id' => $dto->userId,
                'category_id' => $dto->categoryId,
                'is_published' => $dto->isPublished,
                'published_at' => $dto->publishedAt,
            ]);

            foreach ($dto->translations as $locale => $transData) {
                $post->translations()->create([
                    'locale' => $locale,
                    'title' => $transData->title,
                    'slug' => $transData->slug,
                    'content' => $transData->content,
                ]);
            }

            $post->markets()->sync($dto->marketIds);

            return $post;
        });
    }
}
```

### 3.2. Data Transfer Objects (DTOs)
- Clases inmutables con `final readonly class`.
- Tipos estrictos en todas las propiedades.
- El FormRequest o un adaptador HTTP convierte la petición en DTO. Los nuevos DTO del dominio no importan `Request`; un método `fromLegacy(array)` es un adaptador de formato, no autorización.

```php
<?php

declare(strict_types=1);

namespace Modules\Blog\DTOs;

final readonly class PostDataDTO
{
    /**
     * @param array<string, PostTranslationDTO> $translations
     * @param list<int> $marketIds
     */
    public function __construct(
        public int $userId,
        public ?int $categoryId,
        public bool $isPublished,
        public ?string $publishedAt,
        public array $translations,
        public array $marketIds
    ) {}
}
```

---

## 4. Modelos Eloquent y Normalización Multi-Mercado (Patrón 3-Tablas)

En **dbCMS**, el contenido internacionalizado utiliza una estructura relacional limpia y escalable de **3 tablas**:

1. **Tabla Base de la Entidad**: Contiene metadatos invariables del idioma (claves foráneas numéricas, IDs de usuario, banderas globales, timestamps).
   - Ejemplo: `posts` (`id`, `user_id`, `category_id`, `is_published`, `created_at`).
2. **Tabla de Traducciones (1 a N)**: Contiene los campos localizados por idioma (`locale`).
   - Ejemplo: `post_translations` (`id`, `post_id`, `locale`, `title`, `slug`, `content`, `meta_title`, `meta_description`).
   - Consultar la migración real: Pages/Blog/Gallery incluyen `market_id` en algunas traducciones y secciones; Product y ProductCategory usan clave por entidad/locale. Una fila genérica tiene `market_id` nulo donde se admite. No son esquemas intercambiables.
3. **Tabla Pivot de Mercados (N a M)**: Asocia la entidad a uno o varios mercados geográficos/económicos.
   - Ejemplo: `post_market` (`post_id`, `market_id`).

Los guardados comunes del editor actualizan las traducciones genéricas y mantienen las variantes de mercado; no deben colapsarse al hacer una traducción IA o restaurar un snapshot. En MySQL, un índice único con una columna nullable no garantiza unicidad entre todas las filas con NULL: mantener el circuito de escritura serializado por entidad, y no inferir garantías adicionales de la notación conceptual de tres tablas. Una normalización futura requiere migración y revisión de datos existentes.

### Reglas de los Modelos
- No incluir lógica de negocio, cálculos de facturación, ni envíos de correos.
- Declarar siempre tipos explícitos para `$fillable` y `$casts`.

---

## 5. Inferencia y Arquitectura Zero-Knowledge entre Módulos

### La Regla de Oro
> **Los módulos núcleo NO conocen la existencia de módulos opcionales o asistentes.**

### Implementación Canónica:

#### 1. Inyección de Relaciones Eloquent en Tiempo de Ejecución
Si el módulo `ContentAudit` necesita relacionar `Page` con sus auditorías, lo hace desde `ContentAuditModuleServiceProvider::boot()`:
```php
Page::resolveRelationUsing('audits', function (Page $page) {
    return $page->morphMany(ContentAudit::class, 'auditable');
});
```

#### 2. Observadores Autónomos del Ciclo de Vida
`ContentAuditObserver` se suscribe a los modelos deseados en `boot()`, sin que el modelo use ningún Trait externo:
```php
foreach ([Page::class, Post::class, Product::class] as $modelClass) {
    if (class_exists($modelClass)) {
        $modelClass::observe(ContentAuditObserver::class);
    }
}
```

#### 3. Inyección de Interfaz de Usuario mediante View Composers
Los módulos extensores inyectan sus componentes en las vistas del núcleo escuchando vistas genéricas del backoffice:
```php
// En AiAssistantModuleServiceProvider.php:
View::composer('admin.layout', function ($view) {
    // Inyecta el modal y el puente JS del asistente en el stack global @stack('admin_modals')
    View::addNamespace('ai_assistant', base_path('modules/AiAssistant/Resources/views'));
});
```

#### 4. Eventos Desacoplados de Frontend
Para comunicarse con el Gestor de Bloques (`BlockManager`), el Asistente de IA no accede a variables privadas, sino que despacha un evento nativo:
```javascript
window.dispatchEvent(new CustomEvent('cms-insert-block', {
    detail: {
        type: 'hero',
        position: 0,
        payload: { title: 'Título Generado por IA', ... }
    }
}));
```

---

## 6. Sistema de UI del Backoffice (`<x-admin.*>`)

Todo el panel de administración se compone utilizando el kit unificado de componentes Blade ubicado en `resources/views/components/admin/`:

- `<x-admin.table>` y `<x-admin.datatable-header>`
- `<x-admin.form-card title="..." description="...">`
- `<x-admin.input name="..." label="..." />`
- `<x-admin.modal id="..." title="...">`
- `<x-admin.empty-state title="..." action-label="..." />`

### Adaptación Dinámica de Marca (dbCMS vs termoCMS)
Los componentes leen internamente `config('cms.brand')` y adaptan sus clases CSS:
- `CMS_BRAND=dbcms`: Modo oscuro de alta tecnología ciberpunk (`#1A1A1A`, detalles `#00FF99` verde neón).
- `CMS_BRAND=termocms`: Modo claro corporativo/clínico (`bg-slate-50`, texto `slate-900`, acentos azul médico `indigo-600`).

`CMS_BRAND` es la **única fuente de verdad** de la marca: selecciona el directorio del tema de backoffice (`resources/views/themes/admin/{CMS_BRAND}`) y deriva `cms.admin_theme`, `cms.name` y `cms.tagline`. Las antiguas variables `CMS_ADMIN_THEME` y `CMS_THEME_FLAVOR` se eliminaron por redundantes. El tema de **frontoffice** se controla aparte con `CMS_THEME`.

**Prohibido hardcodear colores específicos de un tema en vistas del backoffice de los módulos.** Utilizar las clases semánticas estándar de Tailwind configuradas para los componentes.

---

## 7. Pruebas Automatizadas con Pest PHP (TDD)

### Filosofía de Pruebas
1. **Red-Green-Refactor**: Primero authorar la prueba en rojo, luego escribir el servicio de dominio para ponerla en verde.
2. **Ubicación de Tests**:
   - `tests/Unit/Services/`: Pruebas de lógica pura de servicios sin peticiones HTTP.
   - `tests/Feature/`: Pruebas de integración HTTP completa simulando usuarios autenticados con roles RBAC (`admin`, `editor`, `author`, `marketing`).
   - `tests/Architecture/`: Pruebas de arquitectura que verifican las invariantes del sistema: modelos delgados, aislamiento de marca, integridad de la documentación y cumplimiento de las convenciones.

```bash
# Ejecutar la suite completa dentro del contenedor
./bin/sail-run ./vendor/bin/pest

# Ejecutar una prueba específica
./bin/sail-run ./vendor/bin/pest tests/Feature/Cms/CorePagesModuleTest.php
```

---

## 8. Verificación Estática y Formato de Código

Antes de dar por concluida cualquier tarea o realizar un commit:

1. **PHPStan Nivel 8**:
   ```bash
   ./bin/sail-run ./vendor/bin/phpstan --memory-limit=2G
   ```
   *Criterio de salida*: `[OK] No errors`. 0 errores permitidos.

2. **Laravel Pint**:
   ```bash
   ./bin/sail-run ./vendor/bin/pint --test
   ```
   *Criterio de salida*: `PASS` en todos los archivos del repositorio.

3. **Pest Tests**:
   ```bash
   ./bin/sail-run ./vendor/bin/pest
   ```
   *Criterio de salida*: 100% de tests en estado verde.

---

## 9. Versionado Semántico 2.0.0

El proyecto implementa SemVer estricto a través del comando integrado `app:version`:
- **Patch (`1.0.X`)**: Corrección de errores, parches de seguridad, optimizaciones sin cambios en API.
- **Minor (`1.X.0`)**: Nuevas funcionalidades retrocompatibles, nuevos módulos, nuevos tipos de bloques.
- **Major (`X.0.0`)**: Cambios disruptivos en el microkernel, refactorización de migraciones o ruptura de contratos.

```bash
# Consultar versión actual
./bin/sail-run php artisan app:version

# Incrementar versión de parche
./bin/sail-run php artisan app:version patch
```
