# Prueba funcional local del CMS — 10 de octubre de 2026

> Historical evidence. This records the behavior and checks of its stated date; current contracts live in [Core and extensions](../../specs/CORE_AND_EXTENSIONS.md) and [Testing](../TESTING.md).

## Alcance y entorno

Prueba del CMS en funcionamiento: navegador del backoffice y transporte HTTP MCP real. Entorno local Sail, proyecto Docker `dbcms`, web `http://localhost:8080`, administración `/control`, MySQL `laravel`, Redis y worker de colas. No se realizó despliegue ni se usaron proveedores IA externos.

Las verificaciones de esta sesión son distintas de la suite aislada descrita en `NATIVE_ORCHESTRATION_VERIFICATION.md`. No se ejecutó `migrate:fresh`, ni se borraron datos, ni se rotaron claves OAuth o la clave de la aplicación.

## Preparación realizada

1. Comprobación de los contenedores existentes: aplicación y cola en ejecución; MySQL, Redis y Mailpit saludables.
2. Comprobación de Laravel 13.32.0 y su ventana de soporte mediante Sail.
3. Inventario inicial: cero usuarios, roles, páginas, artículos y productos; un mercado `es` y un idioma `es`, sin asociación entre ambos. Dieciséis módulos activos.
4. Copia de seguridad transaccional de MySQL antes de las modificaciones: `storage/app/runtime-verification/before-runtime-20261010.sql`, 106.712 bytes, archivo con permisos 600 y directorio con permisos 700. No se incorpora al repositorio.
5. Aplicación de siete migraciones aditivas pendientes: presupuestos, revisión editorial, cuentas de servicio OAuth, permisos IA, ejecuciones/aprobaciones, estado de auditoría y permisos de galería.
6. Creación mínima de una cuenta administrativa de verificación local, con contraseña aleatoria no publicada, usando el rol estándar `admin`; no se cargó el seeder de contenidos demo. Asociación del mercado e idioma existentes `es`.
7. Acceso mediante la opción de persona demo ya habilitada en este entorno local. Reinicio del worker existente para cargar los nuevos contratos y bindings.

## Incidencias encontradas durante la navegación

### Editor de páginas y artículos

El formulario aparecía cortado después de la cabecera del primer bloque. Los botones de guardar y la columna de publicación quedaban dentro de una plantilla inactiva del DOM. La causa fue un `template` sin cerrar en el formulario de bloques y un contenedor sin cerrar en el gestor. Evidencia inicial: `evidence/runtime-2026-10-10/01-editor-truncado.jpg`.

### Ruta administrativa configurable

El enlace del copiloto apuntaba a `/admin/ai-assistant` mientras el panel estaba configurado en `/control`. Se revisaron las rutas y enlaces relacionados de IA y MCP para respetar la configuración real.

Se corrigieron los registros de rutas de ambos módulos, las consultas de estado y enlaces del gestor de archivos, notificaciones y vista de módulos. El navegador comprobó posteriormente que el copiloto abre `/control/ai-assistant`.

### Worker anterior al cambio de código

La primera solicitud IA falló al resolver `CapabilityPresentationInterface` en el worker antiguo. Después de reiniciar la cola con el código actual, la misma petición terminó en fallo explícito: «El proveedor de text no está configurado». No se generó contenido ni se comunicó éxito. Evidencia: `evidence/runtime-2026-10-10/02-ia-sin-proveedor.jpg`.

Se añadió una presentación segura de tareas IA: las respuestas de estado no devuelven excepciones internas almacenadas por workers antiguos. Los errores conservan un código estructurado y un mensaje apto para el usuario; los estados de aprobación, conflicto e incertidumbre mantienen su significado.

### Vista de aprobación MCP

La página de revisión mostraba únicamente el título porque el componente enviaba su script a una pila que el layout administrativo no renderizaba. Después de corregir la pila apareció una segunda dependencia ausente: la etiqueta CSRF de la sesión. Se incorporó a los tres layouts administrativos, manteniendo la protección CSRF.

Tras ambas correcciones el navegador mostró «Esperando tu aprobación», el contenido concreto y el botón «Aprobar este cambio». No se pulsó ese botón. Evidencia: `evidence/runtime-2026-10-10/06-aprobacion-mcp-pendiente.jpg`.

### Guía de conexión MCP del panel

La configuración visible ofrecía ejemplos con bearer en la URL y describía scopes antiguos. Se actualizó para usar `/mcp/cms`, cabecera `Authorization: Bearer`, scopes efectivos y revisión humana. Los transportes propios y SSE quedan descritos como compatibilidad. La navegación verificó la guía actualizada sin emitir otro token.

## Resultados funcionales

### Contenido introducido desde el navegador

| Contenido | ID | Estado | Verificación |
| --- | --- | --- | --- |
| Página «Verificación funcional del CMS — 10 octubre 2026» | 1 | Borrador | Título, slug, bloque Hero, CTA, metadatos y mercado `es` guardados y releídos en `/control/pages/1/edit`. |
| Artículo «Prueba editorial desde el navegador — CMS local» | 2 | Borrador | Título, slug, entradilla, bloque Markdown, metadatos, autor y mercado `es` guardados y releídos en `/control/blog/posts/2/edit`. |

La página usa el slug `verificacion-funcional-cms-2026-10-10` y el artículo `prueba-editorial-navegador-2026-10-10`. Se desmarcó expresamente la publicación antes del guardado. Los listados mostraron confirmación de creación y estado «Borrador». Una petición del navegador a la URL pública del artículo mostró «Página no encontrada» / 404, por lo que no quedó publicado.

Evidencias: `03-pagina-borrador-creada.jpg`, `04-articulos-browser-y-mcp.jpg` y `05-borrador-no-publico.jpg`, bajo `evidence/runtime-2026-10-10/`.

### MCP HTTP real

Endpoint probado: `http://localhost:8080/mcp/cms`, JSON-RPC 2.0, negociación MCP `2025-06-18`. Cliente de comprobación ejecutado dentro de Sail; token personal limitado a 30 minutos, scopes `mcp:access`, `mcp:read` y `mcp:write`. El bearer se mantuvo sólo en memoria.

| Operación | Resultado |
| --- | --- |
| Petición sin credenciales | HTTP 401. |
| `initialize` | HTTP 200, identificación del CMS real. |
| `tools/list` | HTTP 200, 50 herramientas. |
| Creación y lectura de artículo | HTTP 200, artículo ID 1, «Verificación MCP 2026-10-10», slug `verificacion-mcp-2026-10-10`, estado borrador. |
| Solicitud de publicación sin aprobación | `confirmation_required=true`; ejecución `approval_required`. |
| Lectura posterior | El artículo sigue siendo borrador. |
| Revocación y reutilización | Token y cliente de prueba revocados; nueva petición HTTP 401. |

El artículo MCP se verificó además en el listado y editor del navegador `/control/blog/posts/1/edit`: contenido, entradilla, metadatos, autor y mercado coherentes con el resultado MCP. No se aprobó su publicación.

La ejecución pendiente es `5ca5551f-5ef5-495c-b2c0-4b21c96c7ba2`, visible en `/control/executions/5ca5551f-5ef5-495c-b2c0-4b21c96c7ba2`. Conserva la identidad del token ya revocado; cualquier publicación futura requiere un flujo nuevo con autorización vigente.

Evidencia estructurada local: `storage/app/runtime-verification/mcp-runtime-20261010-212507.json`, permisos 600, sin bearer ni secretos. La ruta de edición correcta es `/control/blog/posts/1/edit`; el campo auxiliar `edit_url` del script de prueba usó erróneamente `/control/posts/1/edit` y no se usó para navegar.

### Otras comprobaciones

El panel y el listado de pedidos respondieron correctamente, con sus contadores y listados vacíos iniciales. Las dos solicitudes IA de esta sesión terminaron en estado `failed`; no crearon entidades. El segundo intento, con el worker actualizado, informó de la ausencia de proveedor configurado.

El inventario final confirmó una página y dos artículos, ninguno publicado; una cuenta local y cero tokens OAuth activos. La entrada nueva de orquestación sigue deshabilitada.

## Verificación de las correcciones

Ejecutada en Sail, sin reinicializar la base de datos de la aplicación:

```sh
./vendor/bin/sail pest tests/Regression/AdminModulePathTest.php tests/Regression/AiTaskPresentationTest.php tests/Regression/BlockEditorHtmlBoundaryTest.php tests/Regression/ExecutionPanelScriptStackTest.php tests/Regression/DirectoryFixtureTest.php
./vendor/bin/sail bin phpstan analyse --memory-limit=2G
./vendor/bin/sail pint --test
./vendor/bin/sail npm run build
git diff --check
```

Resultados: 13 regresiones / 63 aserciones correctas; PHPStan nivel 8 sin errores en 784 archivos; Pint correcto en 974 archivos; build Vite completado. Las regresiones usan Router aislado, PHPUnit o el TestCase de Laravel sin `RefreshDatabase`: no borran ni siembran la base de datos en uso. También se corrigió la restauración de propietario, grupo y permisos en el helper de directorios usado por las pruebas de temas. La suite completa anterior no se repitió sobre la base del CMS en funcionamiento.

Vite emitió avisos de resolución de fuentes absolutas que conserva para runtime. Los archivos existen bajo `public/fonts`; se comprobó respuesta HTTP 200 de `fa-solid-900.woff2`.

## Límites de esta verificación

No hay un proveedor IA de texto utilizable configurado. La interpretación libre y la generación con un modelo remoto no pueden verificarse positivamente en este entorno; sí se verifica el rechazo explícito sin resultados simulados. La entrada nueva de orquestación mantiene `CMS_ORCHESTRATION_ENABLED=false`.

Los contenidos y la cuenta local de prueba se conservan para inspección. Las credenciales temporales utilizadas para MCP quedaron revocadas. La copia MySQL y el registro MCP permanecen fuera del control de versiones. Las pruebas de concurrencia y proveedores con HTTP simulado pertenecen a la suite aislada; no se sustituyen por esta navegación.

La vista de aprobación ya funciona, aunque aún muestra descripciones de herramientas en inglés y algunos campos técnicos. Queda como oportunidad de mejora de presentación; la prueba valida el estado y contenido de la confirmación, sin afirmar que esa interfaz está terminada editorialmente.
