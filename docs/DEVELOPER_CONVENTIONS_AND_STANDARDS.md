# Convenciones de desarrollo de dbCMS

El contrato vigente es [Core and extensions](../specs/CORE_AND_EXTENSIONS.md).
[AGENTS.md](../AGENTS.md) resume las instrucciones de trabajo y
[TESTING](TESTING.md) define los carriles de verificación. Esta guía explica cómo
aplicar esas decisiones; las especificaciones antiguas no las sustituyen.

## Responsabilidades y nombres

- El núcleo vive en `app/`. Pages pertenece a `App\Content\Pages` y las
  secciones a `App\Core\Sections`; ninguno es un módulo opcional.
- Un módulo posee sus entidades, migraciones, rutas, permisos, navegación,
  capacidades y vistas en `modules/{Nombre}`. Su proveedor está en la raíz del
  módulo. Discovery lee manifiestos; instalar modifica estado y ejecuta migraciones.
- Los paquetes de `block-packages/` aportan definiciones visuales al mismo registro
  que los bloques de los módulos. No crear otro catálogo de widgets.
- Controladores nuevos: acciones invocables con nombre descriptivo y verbo HTTP,
  como `PagePutController`. Agrupar por dominio; no dividir una acción en wrappers
  para reducir artificialmente su tamaño.
- Servicios propietarios toman decisiones y coordinan escrituras; lectores preparan
  consultas y datos de presentación. Blade recibe datos y no ejecuta SQL/Eloquent.

## Entradas, servicios y efectos

El controlador valida, delega y construye HTTP. La brevedad es una consecuencia de
esa separación, no un límite de líneas o sentencias. No mover validación de
transporte a un servicio vacío para satisfacer un contador.

Este ejemplo usa contratos existentes:

```php
use App\Content\Pages\Contracts\PageDomainServiceInterface;
use App\Content\Pages\Models\Page;
use App\Http\Requests\Admin\EditorialEntityRequest;
use Illuminate\Http\RedirectResponse;

final class PagePutController
{
    public function __construct(private readonly PageDomainServiceInterface $service) {}

    public function __invoke(EditorialEntityRequest $request, Page $page): RedirectResponse
    {
        $this->service->updatePage($page, $request->validated());

        return redirect()->route('admin.pages.index');
    }
}
```

Para entidades declaradas, usar `EntityMutationService` y sus handlers propietarios:
traducciones, mercados, secciones y revisión editorial comparten la misma mutación.
No crear un segundo camino de guardado para web, IA o MCP. El transporte autentica
al actor; sus permisos y scopes no proceden del contenido propuesto por un modelo.

Una interfaz se justifica por sustitución o una frontera real entre subsistemas.
Un helper concreto puede inyectarse directamente. Los DTO `readonly` describen
entradas o salidas significativas y viven en su propietario; no todos los arrays
necesitan un DTO. El dominio no reconstruye un Request para resolver contexto.
Un DTO que contiene Eloquent no vuelve inmutable ese modelo.

Las escrituras relacionadas usan una transacción y bloqueos cuando un invariante
lo requiere. Efectos remotos y notificaciones salen después del commit. La
reconstrucción de búsqueda ante cambios de disponibilidad es local y forma parte
de la transacción para no dejar contenido oculto en el índice.

## Extensiones y presentación

El módulo declara `requires` y registra contribuciones desde su proveedor. La
comprobación de disponibilidad es `ModuleManager::isModuleEnabled()`: manifiesto,
requisitos, instalación, activación y `availability_setting` si lo declara. Un
ajuste arbitrario llamado `is_enabled` no reemplaza el ciclo del módulo.

Entidades administrativas y contenido privado no se exportan automáticamente.
Las aportaciones públicas declaran su contrato y aplican publicación, mercado,
idioma y disponibilidad antes de aparecer en búsqueda, sitemap o corpus GEO.

Usar componentes semánticos `<x-admin.*>` y comprobar `dbcms` y `termocms`.
Los temas de frontoffice resuelven vistas mediante el resolutor compartido;
seguir la [guía de temas](17_FRONTOFFICE_THEME_GUIDE.md). Los bloques desactivados
se preservan como no disponibles en el editor y no se ejecutan ni renderizan.
Esta versión está orientada a instalaciones nuevas: no añadir aliases históricos
ni conversiones de payloads retirados.

## Runtime y verificación

PHP, Composer, Artisan y Pest se ejecutan mediante Sail local o directamente dentro
del contenedor CI/bootstrap. Arrancar Sail explícitamente; sin `vendor/`, seguir
[Zero Host](06_ZERO_HOST_PHP_SAIL_RUNBOOK.md). Conservar datos, `.env`, claves y locks.

```bash
./vendor/bin/sail up -d
make test
make test-filter FILTER='SectionContract'
make test-integration
# Instalación, updater, despliegue, concurrencia o validación de release:
make test-release
```

Integración y release usan archivos y base de datos desechables. No ejecutar la
suite completa con `sail pest` sobre la instalación de trabajo. La separación de
base de datos no aísla archivos. PHPStan nivel 8 sin baseline, Pint y build son
comprobaciones distintas; `make test-integration` las incluye.

Cada test debe observar un fallo concreto con fixtures mínimos. Mantener evidencias
de autorización, rollback y concurrencia; retirar pruebas de contratos eliminados.
Verificar consumidores y registro dinámico antes de borrar código. Informar del
alcance ejecutado, sin presentar un filtro como suite completa.
