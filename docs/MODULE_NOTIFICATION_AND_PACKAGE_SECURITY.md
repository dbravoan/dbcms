# Correo de módulos y subida de paquetes

Fecha de verificación: 12 de octubre de 2026.

## Correo real de notificaciones

`ModuleNotificationServiceInterface::notify()` conserva su firma y añade al final `inApp: bool = true`. `sendEmail` e `inApp` son independientes: el flujo de calidad puede enviar correo sin mostrar un aviso en el panel. Las preferencias del usuario nunca desactivan los avisos internos.

Cada envío solicitado crea una fila por destinatario en `notification_email_deliveries` y encola `SendModuleNotificationEmailJob` después del commit. La transacción fallida elimina también las entregas y no publica trabajo en la cola. Es necesario ejecutar el worker de la instalación con su conexión de cola real; con `sync`, el envío se procesa en la petición una vez confirmada la transacción.

Los destinatarios son cuentas humanas vigentes con `access:admin`, permisos del módulo, permiso adicional `data.required_permission` cuando se declara, preferencias activas y la política del módulo. Los módulos pueden registrar políticas en `NotificationRecipientPolicyRegistry`; Auditoría revalida la autoridad para revisar una entrega de tipo `submission`. Un destinatario privado eliminado no redirige el mensaje a un administrador ni convierte su aviso en contenido global.

Los avisos globales se envían exclusivamente a los roles declarados en `config/module_notifications.php` (`admin`, `super-admin` inicialmente). No se utiliza la dirección del remitente como destinatario de respaldo. El correo contiene título y mensaje escapados y un enlace al panel; no incluye el campo privado `data`, tokens ni un enlace externo aportado como acción.

La campana del backoffice permite activar o desactivar el correo para la cuenta actual. Ambos temas usan el mismo componente. `POST /admin/notifications/email-preference` requiere sesión autorizada y CSRF; no acepta `user_id`. El contrato permite preferencias por módulo (`*` es el bloqueo global): un bloqueo global no se anula mediante una preferencia particular.

Configurar el transporte en el entorno de la instalación, manteniendo usuario y contraseña fuera del repositorio:

```dotenv
MODULE_NOTIFICATION_EMAIL_ENABLED=true
MAIL_MAILER=smtp
MAIL_HOST=servidor-smtp-de-la-instalacion
MAIL_PORT=587
MAIL_FROM_ADDRESS=remitente-verificado-del-proyecto
```

Los transportes `log`, `array` y combinaciones de failover que incluyan uno de ellos no representan entrega real y producen estado `failed`. Los tests utilizan `Mail::fake()` deliberadamente y de forma explícita. Para producción se debe verificar la configuración concreta del proveedor, remitente autorizado, cifrado y conexión; la prueba de esta revisión utiliza únicamente un Mailpit aislado y una dirección `example.invalid`.

Estados persistidos:

| Estado | Significado |
| --- | --- |
| `pending` | Trabajo todavía no iniciado. Puede indicar falta de worker o error al encolar. |
| `sending` | El worker reclamó la entrega. Si queda detenido, el resultado remoto puede ser incierto. |
| `sent` | Laravel completó la entrega al transporte; no demuestra lectura, llegada al buzón o ausencia de rebote. |
| `skipped` | Cuenta, módulo, permisos o preferencias ya no permiten enviar. |
| `failed` | El transporte o una operación local falló. Se registra la clase del error, sin credenciales ni detalles privados. |

Cada job tiene un solo intento. La reclamación condicional de `pending` impide que un job repetido envíe dos veces. No reintentar automáticamente un envío `sending` o `failed` tras un corte: SMTP puede haber aceptado el mensaje antes de perderse la respuesta. Revisar el proveedor y la cola de fallidos antes de decidir reenviar. Tras cambios de configuración o permisos, reiniciar los workers mediante el procedimiento de despliegue existente.

## Paquetes de módulos

`POST /admin/modules/upload` mantiene las restricciones `access:admin` y `manage:modules`, también en su FormRequest. Instala código aportado por un administrador de confianza; la validación del ZIP no comprueba que el PHP sea benigno. Revisar la procedencia y el código antes de instalar cualquier paquete.

El archivo debe contener exactamente una carpeta con el nombre del módulo (por ejemplo `Example/`) y su `module.json`. La carpeta comienza por una mayúscula y contiene letras y números. El manifiesto requiere `identifier`, `name`, `version` y `provider`; el proveedor pertenece a `Modules\\Example\\...` y su archivo debe estar incluido. Un paquete externo no puede declararse módulo de núcleo.

Límites por defecto del servicio, configurables en `cms.module_packages.*`:

| Clave | Límite |
| --- | --- |
| `max_upload_bytes` | 50 MiB; el FormRequest mantiene también este máximo. |
| `max_expanded_bytes` | 200 MiB en total. |
| `max_file_bytes` | 50 MiB por archivo. |
| `max_entries` | 2.000 entradas. |
| `max_compression_ratio` | 200 por entrada. |

Se rechazan rutas absolutas, `..`, componentes vacíos, separadores Windows, unidades, flujos NTFS, nombres reservados, componentes ocultos, caracteres de control, rutas excesivas, duplicados (incluyendo variantes por mayúsculas), conflictos archivo/directorio, enlaces, archivos especiales y entradas cifradas. Se validan tamaños y CRC al copiar cada archivo; no se usa extracción directa sobre la instalación.

El staging se crea en `modules/.package-staging` con permisos `0700`, junto al destino para permitir el renombrado atómico. El bloqueo de instalación tiene permisos `0600`. La carpeta o identificador existente produce error y se conserva. El staging se elimina tanto al finalizar como al fallar. Si la persistencia del nuevo registro falla, sólo se retira la carpeta recién creada.

Un paquete subido queda **sin instalar y desactivado**. La instalación posterior ejecuta las migraciones y activa el módulo mediante el circuito existente. La desinstalación conserva datos. La aplicación registra proveedores para descubrir rutas: PHP de módulos se ejecuta dentro del proceso del CMS, incluso para registrar elementos de módulos desactivados. No usar esta función para ejecutar código sin revisión.

## Pruebas de esta revisión

Los casos focales cubren destinatarios privados, eliminación de cuentas, preferencias, revocación, rollback, errores SMTP, falsos transportes, ambos temas, rutas ZIP, enlaces, límites, conflictos y rollback de registro. La entrega SMTP local fue confirmada por `sent` y un mensaje en Mailpit aislado; no se contactó con personas ni servidores de correo externos.
