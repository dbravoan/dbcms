# Registro del núcleo y las extensiones

El núcleo incluye páginas, menús, publicación, secciones, medios, localización,
temas y SEO técnico. `PagesServiceProvider` registra las páginas desde
`app/Content/Pages`; no existe un registro `cms_modules.pages` ni un contrato de
compatibilidad para tratar páginas como un módulo.

## Instalación y disponibilidad

`ModuleManager::discoverModules()` lee y valida manifiestos sin crear directorios
ni modificar la base de datos. La pantalla de módulos compone ese catálogo con
los estados almacenados; abrirla no instala paquetes ni crea registros.
`reconcileModules($identifiers)` actualiza metadatos explícitamente y preserva
instalación, activación y ajustes existentes. `installConfiguredModules()` instala
solo `config('cms.modules')`, cuyo valor inicial es una lista vacía.

`requires` expresa versiones SemVer por identificador, incluyendo `core`.
Dependencias ausentes, incompatibles, identificadores duplicados y ciclos fallan
antes de activarse. Las dependencias deben seleccionarse explícitamente. El orden
de registro respeta el grafo. Se admiten guiones y guiones bajos en identificadores.
`registerConfiguredModules()` registra los proveedores durante la fase `register`
de Laravel, antes de cargar las rutas. La lectura inicial usa el constructor de
consultas, ya que Eloquent todavía no ha recibido su conexión en esa fase.
Desactivar o desinstalar un módulo conserva sus tablas y datos. Desactivar rechaza
dependientes activos; desinstalar rechaza cualquier dependiente instalado, aunque
esté inactivo. Ambas operaciones bloquean los estados en orden estable dentro de
la transacción.

Un ajuste booleano solo controla la disponibilidad del módulo si su manifiesto
lo declara con `availability_setting`. Esto separa el interruptor del módulo de
opciones de dominio como el modo de mantenimiento. Guardar ese ajuste respeta
las mismas restricciones de dependencias. Los ajustes secretos siguen cifrados;
un valor cifrado inválido no se interpreta como una contraseña en texto plano.

## Contribuciones administrativas

Cada proveedor aporta su navegación con `getAdminNavigation()`. El núcleo no
mantiene una lista de módulos conocidos. Los elementos declaran ruta, parámetros,
permiso, icono y, opcionalmente, hijos, ruta de creación, patrón activo e insignia.
Los permisos se aplican también a los hijos. Las páginas y los paquetes de bloques
son enlaces propios del núcleo.

`getAdminDashboard(User $user)` devuelve `null` o una contribución con `views` y
`data`. Las ranuras son `metrics`, `actions` y `details`. Las consultas y vistas
pertenecen al módulo: Blog conserva el filtro de autor/publicación, Forms controla
sus contactos y Shop su catálogo. El núcleo agrega contribuciones de módulos
habilitados y mantiene sus propias métricas y permisos.

Los selectores AJAX conservan sus listas permitidas de campos y permisos.
`config/entity_search.php` solo declara entidades del núcleo; cada módulo registra
sus fuentes desde su propio `Config/entity_search.php`.

## Descubrimiento público

Las entidades administrativas son privadas por defecto. Un `EntitySchema` aporta
`PublicContentDefinition` para participar en menús, sitemaps y auditoría SEO.
La definición declara segmento, icono, frecuencia, prioridad, portada de colección
y, cuando hace falta, el método que compone una ruta jerárquica de slugs.
`PublicContentDiscovery` aplica publicación, módulo habilitado, mercado activo y
traducción existente. Los consumidores comparten su resolución canónica de URL.

Los sitemaps locales y globales recorren el mismo catálogo; sus alternativas
lingüísticas solo enlazan representaciones públicas en idiomas activos. La
partición de documentos y el control de calidad de páginas de filtro se mantienen.
No se anuncian segmentos de módulos ausentes o desactivados.

`PublicSemanticRegistry` combina las aportaciones de texto y destinos públicos.
Blog, Shop y Gallery poseen sus representaciones de índice y corpus: fechas de
artículos, precios autorizados, SKU, características y pies de imágenes siguen en
sus módulos. Los destinos que no son entidades, como la búsqueda indexable,
implementan `PublicUrlContributorInterface`. El feed RSS y su ruta pertenecen a
Blog. Las páginas siguen teniendo índice y corpus en el núcleo.

## Verificación aislada

`ModuleManifestGraphTest` prueba el grafo sin arrancar Laravel ni tocar la base de
datos. `ModuleLifecycleSmokeTest` instala una extensión de prueba autocontenida,
comprueba la ruta y conserva una fila después de desinstalar; no requiere que el
catálogo de módulos comerciales esté presente. Los tests de integración declaran
sus composiciones, como `PublicContentComposition`, antes de migrar.

`bin/test-core-distribution.sh` se ejecuta dentro de Docker con
`DBCMS_TEST_ISOLATED=1` y credenciales administrativas del MySQL desechable en
`DBCMS_TEST_DB_ADMIN_USER` y `DBCMS_TEST_DB_ADMIN_PASSWORD`. Crea una copia física
sin `modules/`, copia y regenera su propio autoload, crea una base de datos única,
instala sin demo y arranca un servidor HTTP local. Verifica autenticación real con
cookies y CSRF, creación/publicación de una página, lectura anónima y sitemap.
El servidor, la copia y la base de datos se eliminan al terminar. Nunca reutiliza
la base de datos de trabajo ni enlaces de autoload al repositorio original.

## Revisión editorial

`EditorialWorkflow` de ContentAudit implementa directamente
`EditorialWorkflowInterface`. La política de acceso, las revisiones y diferencias,
la calidad, las notificaciones y la presentación tienen servicios propios. Cada
consumidor utiliza el servicio responsable. No existen alias del antiguo
`ContentAuditService`, su interfaz o su adaptador.

`ContentSnapshotService` acepta únicamente snapshots completos de versión 3:
`attributes`, `translation_records`, `section_records`, `market_ids` y
`market_pivots`. No convierte formatos históricos. Los manejadores de mutaciones
de las entidades opcionales conservan sus registros específicos, incluidos los
datos comerciales. Preparar un borrador preserva el contenido público; aprobarlo
comprueba el hash de la versión vigente bajo bloqueo de la entidad. Los tokens de
revisión vinculan actor, destino, versión y caducidad. Rechazar archiva primero el
snapshot pendiente y después borra su referencia. Las notificaciones se envían
únicamente tras confirmar la transacción.

ContentAudit conecta sus observadores a las entidades registradas cuando termina
el arranque de los proveedores. Cada esquema puede declarar `adminFormView` para
aportar su panel editorial sin importar modelos ni vistas de otro módulo.

## Instalación de paquetes visuales

Los paquetes incluidos siguen en `block-packages/`. Los ZIP subidos se guardan
en `storage/app/extensions/blocks`, fuera del directorio público y dentro del
almacenamiento persistente. En Docker sobreviven a la sustitución de la imagen
mediante `app_storage`; las copias de seguridad deben conservar ese volumen.

Los ZIP visuales son código autorizado por el administrador. Se extraen en staging y se prueban contra el catálogo actual en un proceso PHP CLI separado antes de publicar el directorio y registrar el paquete desactivado. El runtime necesita PHP CLI y `proc_open` para esta operación; no son servicios adicionales del núcleo. El límite es de 256 MiB y 10 segundos, ajustable entre 1 y 30 mediante `cms.block_packages.validation_timeout`. El proceso separado evita contaminar las clases del administrador, pero no constituye un sandbox de archivos o base de datos.

Una clase ausente, contrato inválido, namespace o tipo duplicado, excepción, salida anticipada o timeout aborta la instalación y elimina staging. La activación comprueba requisitos de versión; la disponibilidad los vuelve a evaluar al usar el catálogo. El esquema de campos es también el contrato de validación del servidor, incluidos campos obligatorios y repetidores.

Los cambios de disponibilidad reconstruyen el índice dentro de la transacción. La notificación externa y la señal estándar de reinicio de workers salen después del commit. Un cambio de módulo también elimina la caché de rutas para que la siguiente petición registre la composición vigente; un rollback la conserva. Los cambios de ajustes también reinician workers para que no conserven configuración anterior. Un fallo del proveedor no confirma instalación ni activación; las migraciones previas y sus datos se conservan.
