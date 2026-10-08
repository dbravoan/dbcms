# ADR: Fronteras de aplicación verificables

Estado: aceptado e implementado. Fecha: 12 de octubre de 2026.

## Contexto

Permitir todos los modelos, servicios y módulos en un test genérico de dependencias no demuestra que un controlador sea delgado. Las fronteras extraídas necesitan controles que analicen acciones y dependencias reales, sin declarar superada la deuda restante mediante exclusiones globales.

## Decisiones

`tests/Support/ArchitectureBoundaryInspector.php` utiliza el parser PHP existente. Resuelve alias y analiza el árbol del código; no ejecuta consultas para inspeccionarlo. El guard dedicado comprueba los controladores invocables extraídos de instalación, configuración, notificaciones, paquetes y mantenimiento, además de menús, edición de formularios y flujo público de compra. Los grupos comprueban que los archivos existen y que su inventario no queda vacío.

Los controladores no incorporan bucles, persistencia, PDO, correo, procesos ni código ejecutable; sus acciones tienen como máximo diez líneas de código y diez sentencias. Los constructores dependen de contratos de aplicación. Los adaptadores HTTP existentes se admiten como composición de transporte: no se exige crearles una interfaz vacía. Los modelos de identidad/contexto y los parámetros resueltos por rutas pueden representar entradas; sus operaciones ORM siguen prohibidas en el controlador.

Los lectores antiguos con varias acciones conservan su compatibilidad y sus propios tests de delegación. No se aplica la regla de una sola acción a un adaptador que existe expresamente para conservar URLs. Esto no autoriza a incorporar nuevas reglas de negocio a esos adaptadores.

Los servicios de aplicación nuevos implementan contratos y no consumen Request, Response ni la capa HTTP del CMS. El guard se extiende a los servicios de menús, listados, formularios, catálogo, carrito y compra. El análisis de constructores detecta ciclos dentro del conjunto revisado. Los fixtures adversarios demuestran que el guard detecta alias ORM, acciones largas, bucles, dependencia HTTP y ciclos entre interfaces.

Los efectos remotos directos de Mail/Http se comprueban en closures de transacción, distinguiendo callbacks `afterCommit`. El envío de notificaciones conserva el trabajo después del commit, con registro de fallos; un fallo SMTP o de cola no transforma en fracaso una operación local que ya fue confirmada. Esta comprobación sintáctica no sustituye la revisión de llamadas transitivas, de SDKs personalizados ni las pruebas de rollback.

## Puertos opcionales y mantenimiento

`MaintenanceModeInterface` está en el núcleo y tiene una implementación nula. Sin el módulo físico el CMS puede construir acciones genéricas y atender peticiones; la implementación nula informa de estado inactivo y rechaza modificaciones, evitando éxito simulado.

El módulo registra su implementación y conserva sus decisiones de estado. Desactivarlo hace inefectivo un archivo de mantenimiento anterior; al reactivarlo, el estado guardado puede volver a entrar en vigor. La previsualización y la respuesta pública reutilizan `renderHtml()`, pero cada adaptador HTTP establece su estado y cabeceras. El contrato de configuración del módulo devuelve HTML, no una Response.

El bypass de mantenimiento requiere `access:admin`. Tener simplemente algún rol ya no concede acceso a páginas públicas durante el bloqueo. Las rutas de login, instalador, salud y backoffice conservan sus excepciones operativas.

La subida de paquetes usa `ModulePackageUploadServiceInterface` y `ModulePackageDTO`. `ModuleManager` implementa el puerto sin añadir otra implementación de las reglas de instalación. `uploadPackage(UploadedFile)` permanece como adaptador compatible; el controlador nuevo transforma el archivo validado en DTO.

## Rendimiento y límites

Las lecturas públicas de mantenimiento se concentran en su servicio. La configuración de módulos conserva la serialización con bloqueo de fila y la asociación segura entre destino y credencial. Al cambiar un endpoint de proveedor cloud, su validación puede resolver DNS dentro de esta operación de configuración; no se realiza una petición HTTP allí. Esta ruta administrativa poco frecuente no es el camino de lectura pública. Mover la resolución a un preflight futuro debe mantener una segunda validación local bajo el bloqueo; separar fases sin esa garantía reintroduciría carreras de credenciales.

El guard de ciclos conoce los constructores del conjunto revisado, no todas las resoluciones diferidas del contenedor. La prueba de arranque en un proceso nuevo cubre la ausencia física de Mantenimiento. No se atribuye esta evidencia automáticamente a cualquier módulo externo. PHPStan y los tests funcionales complementan estos controles.

## Guía breve para desarrollar o usar IA sobre estas áreas

1. Leer la especificación aplicable y el contrato del servicio propietario antes de cambiar una acción.
2. Mantener la forma HTTP en controller/FormRequest/adaptador; enviar DTOs al dominio.
3. Registrar extensiones desde el módulo dueño y aportar disponibilidad, permisos y comportamiento ante ausencia; no importar su implementación en el núcleo.
4. Escribir una regresión que reproduzca el defecto y comprobar el fallo antes de implementarlo.
5. Ejecutar PHP, Composer y pruebas dentro de Docker, con base y runtime privados si los tests modifican entorno, locks, claves o rutas físicas.
6. Revisar el inventario de archivos y los fixtures adversarios del guard al ampliar una frontera; no quitar archivos de un glob para hacer pasar el test.
7. Distinguir operación persistida, aceptación del transporte y entrega remota. No repetir efectos inciertos ni convertir una clave guardada en prueba de disponibilidad.

Documentos relacionados: `INSTALLATION_AND_MODULE_SETTINGS_BOUNDARIES.md`, `MODULE_NOTIFICATION_AND_PACKAGE_SECURITY.md` y las especificaciones de las fronteras extraídas.
