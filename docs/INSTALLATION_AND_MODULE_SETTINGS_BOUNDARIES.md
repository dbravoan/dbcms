# Instalación y configuración mediante servicios compartidos

Contrato actual para instalaciones nuevas. Verificación según [TESTING](TESTING.md).

El asistente de instalación web conserva sus URLs y nombres de ruta. Sus nueve acciones son controladores invocables que validan mediante FormRequests y delegan en contratos. La instalación por consola utiliza los mismos servicios para el entorno, MySQL, aprovisionamiento y cierre con cuenta administrativa.

Los valores de conexión se validan sin cambiar el nombre solicitado de la base. Se rechazan componentes capaces de alterar el DSN, puertos fuera de rango y nombres no admitidos. Se verifica la conexión antes de guardar; `auto_create=false` impide crear automáticamente una base inexistente. Los errores no incluyen contraseñas ni mensajes internos de PDO.

La escritura del entorno mantiene el serializador existente, reemplazo atómico y permisos `0600`. La clave de cifrado inyectada se conserva y verifica, también cuando está entre comillas. Dos claves válidas pero distintas entre entorno y archivo producen error; no se rotan automáticamente.

El aprovisionamiento comprueba los resultados de migraciones, claves y siembra. Una pareja OAuth existente debe ser RSA válida, coincidir y tener su clave privada protegida; una pareja incompleta o inválida detiene el proceso sin reemplazarla. Se reutiliza el cliente OAuth personal vigente. La demo se solicita explícitamente tanto en web como en consola; el valor inicial del selector web es desactivado.

El cierre de instalación se serializa con un bloqueo privado. Cuenta y roles se guardan en una transacción. Si falla la configuración o el sellado, se revierte la cuenta y se restaura el entorno previo y el archivo de instalación previo. La instalación web rechaza un correo ya existente, evitando usar el asistente para restablecer otra cuenta. La reinstalación de consola requiere la opción explícita `--force`. Las migraciones ejecutadas previamente se conservan: la recuperación del cierre no revierte automáticamente esquemas o datos.

La protección de instalación existente (archivo de sellado, middleware, CSRF y límite de peticiones) conserva sus rutas. El nuevo archivo de cierre se escribe con permisos `0600`.

La configuración de módulos se organiza de forma equivalente. `ModuleSettingsAdministrationInterface` entrega un DTO para la vista y recibe un DTO para actualizar, con validación derivada del esquema. Los módulos registran sus ajustes especializados en `ModuleSettingsExtensionRegistry`; el núcleo ya no conoce el controlador de Mantenimiento. Este módulo conserva sus URLs y sincroniza la configuración mediante su servicio propietario, reutilizando el renderizador público para la vista previa.

`ModuleSettingsService::setMany` bloquea la fila durante la transacción y actualiza cachés después del commit. Los campos secretos continúan cifrados; valores vacíos, espacios o máscaras no los reemplazan. La normalización de proveedores IA está registrada por su módulo y utiliza la misma regla que la pantalla dedicada: un destino distinto requiere una nueva clave explícita. Esta política se aplica también a cambios mediante el servicio compartido y MCP. Una API key enmascarada o formada sólo por espacios no autoriza asociar la clave anterior a otro destino.

Los ajustes generales usan `SiteSettingsService::update()` con un patch de claves
canónicas, como `general.site_name`. El servicio valida reglas compartidas,
rechaza claves desconocidas y conserva las omitidas dentro de una transacción.
Los nombres con guiones bajos del formulario se convierten en su FormRequest;
no constituyen un segundo contrato del servicio.

Verificar estos límites con los carriles aislados de [TESTING](TESTING.md).
Las pruebas de instalación necesitan archivos y base de datos desechables;
los proveedores externos se sustituyen por límites HTTP controlados cuando no
se está ejecutando un piloto real autorizado.
