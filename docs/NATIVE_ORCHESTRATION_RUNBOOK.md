# Orquestación nativa: activación y recuperación

El módulo opcional Orchestration aporta planificación y ejecución de instrucciones; depende de AiAssistant. El núcleo mantiene la autorización y la aprobación humana de capacidades. `App\Core\Capabilities` proporciona catálogo, autorización y ejecución; los módulos registran esquemas y handlers de dominio. MCP y la API son adaptadores. La entrada de instrucciones usa un modelo de texto y está desactivada por defecto. Las herramientas concretas continúan funcionando con esa entrada desactivada y sin modelo.

## Preparación de la release

1. Realizar backup verificable de MySQL, uploads, configuración privada, APP_KEY y claves Passport. Registrar la release anterior y su imagen CLI. No introducir configuración privada en los artefactos.
2. Ejecutar las verificaciones en contenedor sobre el código y lockfiles de la release: Pest, PHPStan8, Pint, Composer/npm audit y build. Usar `bin/ci-check` dentro de la imagen CI; en Sail se pueden ejecutar los mismos comandos por separado.
3. Mantener el módulo `orchestration` desactivado durante la actualización. Verificar el esquema de la instalación antes de habilitar la entrada. El despliegue documentado usa mantenimiento; no promete ausencia de interrupciones.
4. Conservar el alojamiento VPS/Plesk existente: directorio transferido por rsync en `releases/`, datos en `shared/` y enlace `current`. `bin/deploy-vps-release.sh` ejecuta las migraciones aplicables en instalaciones terminadas de la línea compatible con la CLI PHP nativa del VPS y exige HTTP 200. Un fallo tras iniciar migraciones mantiene el cierre y requiere revisión. La modalidad web Docker es una alternativa explícita.
5. Revisar permisos y clientes M2M existentes. Una clave de API guardada no verifica una conexión. Comprobar catálogo y modalidad seleccionada antes de habilitar el modelo.
6. Comprobar borrador completo, revisión de publicado, aprobación humana, pedido offline, aislamiento entre visitantes y desinstalación/reinstalación en staging. Instalar y activar `orchestration` con su dependencia `ai_assistant` sólo tras superar esas verificaciones, reiniciar workers y reconstruir configuración mediante `bin/vps-artisan.sh` en VPS/Plesk o la CLI de la imagen en alojamiento Docker.

La base 3.0.1 requiere una instalación nueva: esta preparación no convierte una
base anterior a v3. Para entregas posteriores, verificar compatibilidad y el gate
local documentado en [TESTING](TESTING.md) antes de publicar artefactos.

## Esquema y permisos

Las tablas nuevas son `cms_executions`, `cms_execution_steps`, `cms_execution_approvals`, `oauth_service_accounts` y presupuestos de Shop. Usuarios incorporan identificación de cuentas de servicio. ContentAudit incorpora `pending_revision_id` para separar borrador y versión visible. La auditoría del núcleo utiliza `capability_audit_logs`. Las migraciones describen instalaciones nuevas; no hay conversión del esquema histórico ni adaptadores de transporte MCP.

`use:ai` permite consumir IA y `manage:ai` administrar conexiones. Sólo los roles estándar administrador/editor/autor/marketing reciben uso; administración se limita a administradores. Los roles personalizados permanecen intactos y deben revisarse expresamente.

Galería separa su escritura mediante `manage:galleries`. Productos, categorías, artículos, páginas y galerías usan los mismos handlers desde el backoffice y las capacidades; las revisiones conservan también precios por mercado e imágenes.

Las sesiones web usan permisos sin scopes OAuth ficticios. API exige `ai:use` para el transporte y scopes adicionales read/write/admin según cada capacidad. MCP exige `mcp:access` y `mcp:read`, `mcp:write` o `mcp:destructive`, además de los permisos de la cuenta.

Un cliente M2M sin asociación explícita queda denegado. Crear uno nuevo mediante el backoffice seleccionando rol o mediante el comando existente con `--role=slug` y scopes explícitos. El servicio crea cuenta y asociación en una transacción; no concede permisos por inferencia. Nunca reutilizar un token humano huérfano como cuenta de servicio.

Los catálogos requieren autenticación y sólo describen operaciones autorizadas/disponibles. `confirm:true` no autoriza publicaciones ni borrados. Las llamadas que requieren aprobación devuelven `confirmation_required`, `execution_id`, `review_url` y ejecución. Login/registro API aplican límites de intentos. El número comercial de un pedido no autoriza su lectura; el acceso exige propietario, sesión de compra o enlace firmado de 24 horas.

Tarjeta/Stripe quedan desactivados; transferencia y contrarreembolso generan pedidos pendientes de pago. Cambiar un endpoint de IA/almacenamiento exige credenciales nuevas explícitas para ese destino. Las conexiones distinguen configurada, verificada e indisponible. No hay resultados simulados de producción cuando faltan credenciales.

Desinstalar retira acceso y capacidades, conserva tablas y contenido. Reinstalar ejecuta migraciones pendientes y recupera datos. Discovery no reactiva módulos desinstalados. HTML compartido conserva sólo contenido público y se hidrata por petición; las respuestas finales son privadas y no se reutilizan en CDN. CAPTCHA y formularios dependientes de sesión permanecen fuera de snapshots. Los embeds ejecutables se aíslan del documento del CMS y los textos se sanean antes de mostrarse.

Las rutas con mercado e idioma explícitos rechazan mercados inactivos e idiomas no activos o no asociados a ese mercado. No sirven la portada de un contexto alternativo. Las rutas públicas usan el mercado, idioma y prefijo localizado canónicos del módulo.

## Ejecución y aprobación

La petición contiene instrucciones, mercado, idioma y contexto descriptivo del editor; no identidad ni permisos controlados por el cliente. La clave de idempotencia identifica una petición concreta. El modelo propone como máximo 20 pasos y una revisión automática; el CMS valida el plan completo antes de ejecutar. Datos obligatorios ausentes u objetivos ambiguos requieren respuesta del usuario.

Las lecturas, generaciones y escrituras de borradores solicitadas se ejecutan con permisos vigentes. Los cambios de contenido publicado quedan como revisión, conservando la versión pública. Las llamadas a modelos y almacenamiento necesario para generar ese borrador están autorizadas por la petición.

Publicar, borrar o cambiar aplicaciones externas detiene el paso. El usuario abre `review_url`, revisa solicitud/contenido y aprueba desde su sesión web con CSRF. No existe endpoint OAuth de aprobación ni aprobación del modelo. El CMS liga la decisión a actor original, argumentos, plan y versión del contenido, incluida revisión pendiente. Cambiar cualquiera de ellos invalida la decisión. Un administrador con `manage:ai` y `manage:users` puede revisar la ejecución de una cuenta de servicio, pero la ejecución sigue limitada por los permisos/scopes originales de esa cuenta.

La revisión editorial existente también muestra el borrador pendiente y exige un `review_token` generado al abrirla. El token está ligado al auditor y a la versión, caduca a los 30 minutos y deja de servir si cambia el contenido o se consume la aprobación. Los clientes que llamaban directamente al POST de aprobación deben abrir antes la revisión.

## Recuperación

Una desactivación solicitada queda pendiente de revisión y se conserva al aprobar; la aprobación no reactiva automáticamente ese contenido. Si cambian las existencias o la versión pública después de editar, se rechaza la aprobación obsoleta. Descartar esa revisión conserva su historial y permite empezar un borrador nuevo sobre el estado vigente.

Los pasos completados conservan checkpoint y no se repiten. `pending_remote` conserva proveedor e ID remoto y consulta la operación ya enviada; no crea otra generación. `failed` permite reintentar tras corregir causa y revalidar permisos. Una planificación interrumpida sin pasos vuelve a planificarse al reanudar.

`uncertain` significa que una operación comenzó y falta evidencia concluyente de su efecto. Comprobar el proveedor o el servicio afectado y conservar la evidencia antes de iniciar otra solicitud. No reenviar automáticamente ni marcar éxito por suposición. Cancelar impide iniciar pasos siguientes; no deshace un efecto que ya está en curso.

Si el health check falla al desplegar, recuperar el enlace a la release anterior, recargar PHP-FPM y reiniciar procesos con la CLI compatible. En alojamiento Docker, recuperar la imagen anterior. Conservar las migraciones aditivas y los datos; nunca revertirlos automáticamente. Mantener la entrada de instrucciones desactivada mientras se investiga. El procedimiento detallado está en `DEPLOYMENT_GUIDE.md` y `DEPLOYMENT_AND_VERSIONING.md`.

## Matriz y evidencia

La especificación `specs/native_cms_orchestration_spec.md` define equivalencia de permisos/efectos entre web, IA, API, MCP y jobs. Las regresiones cubren cuentas revocadas, propiedad, aprobación, contenido publicado, checkpoints, proveedores, medios, stock, mercados, caché y módulos. Las verificaciones actuales siguen [TESTING](TESTING.md). No sustituye la validación de credenciales reales ni autoriza un despliegue.
