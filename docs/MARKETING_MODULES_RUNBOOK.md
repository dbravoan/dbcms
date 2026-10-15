# Operación y piloto de CreativeStudio y SocialPublishing

Este manual corresponde a los contratos de [CreativeStudio](../specs/FEAT-CREATIVE-STUDIO.md) y [SocialPublishing](../specs/FEAT-SOCIAL-PUBLISHING.md). Ambos módulos son opcionales y se descubren inicialmente **inactivos**. Sus guías se pueden consultar antes de activarlos. SocialPublishing admite archivos manuales y funciona sin CreativeStudio; la generación con proveedores requiere AiAssistant disponible. Analytics aporta GA4 de forma opcional.

La construcción local de la imagen de producción, el arranque de rutas, las migraciones aditivas y los procesos de marketing se han comprobado. Las pruebas con HTTP simulado verifican los contratos; el piloto con credenciales, cuentas autorizadas, generación facturada y publicaciones externas reales sigue pendiente. No interpretar una guía marcada, una cuenta creada ni un catálogo de modelos como una conexión o publicación demostrada.

## Identidad y aislamiento antes del piloto

Cada minisite necesita su propia base de datos, almacenamiento persistente privado, `APP_KEY`, cuentas/configuración y un `CMS_INSTALLATION_ID` único y estable. Fijar este último explícitamente antes de crear tareas; el fallback actual deriva de `APP_KEY`. Conservar ambos valores al actualizar: cambiar la identidad oculta referencias de archivos, progreso y namespaces anteriores; rotar `APP_KEY` impide descifrar secretos existentes.

Las colas, caché y locks nuevos de marketing usan el namespace de instalación. El directorio predeterminado es `storage/app/private/marketing/<installation_id>`; `MARKETING_ARTIFACT_ROOT` permite configurar una raíz privada dedicada. El usuario de los workers debe poder leer y escribir ahí. No servir ese directorio mediante Nginx ni `storage:link`. Los archivos sociales tienen copia propia y las URLs firmadas para ingestión externa son temporales.

El prefijo general Redis heredado y la cola general se conservan para no abandonar tareas históricas de IA. **No se han migrado automáticamente todas las claves antiguas.** Antes de compartir Redis entre instalaciones, comprobar también `REDIS_PREFIX`, `CACHE_PREFIX` y `SESSION_COOKIE` distintos, o usar instancias Redis independientes. No rotar el prefijo de una instalación con jobs antiguos pendientes: primero drenar su cola y recuperar sus tareas. Este aislamiento es un requisito de entrada al piloto.

Revisar `.env.example`, `.env.production.example`, `config/marketing.php`, `config/database.php` y `config/queue.php`. Redis debe admitir locks. El canal `marketing` usa la conexión Redis `marketing` y `MARKETING_QUEUE_RETRY_AFTER=1500`; este margen supera los timeouts de los cuatro workers. La conexión respeta `REDIS_URL` cuando se configura. Mantener estable la configuración de todas las réplicas de una instalación.

## Release, migración y procesos

Usar una release que haya superado el gate para su SHA exacto; seguir [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) para VPS/Plesk o Docker de producción. Registrar por instalación versión de `VERSION`, SHA, checksum del paquete o digest de imagen, fecha UTC, responsable, identidad de instalación y resultado de migración. No registrar claves ni JSON de cuentas de servicio.

```bash
git rev-parse HEAD
./vendor/bin/sail artisan app:version
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan migrate:status
./vendor/bin/sail artisan schedule:list
```

Estos comandos son para Sail local. En VPS/Plesk, desde la release y con la imagen CLI fijada por SHA, usar `bash bin/vps-artisan.sh <comando-artisan>`; por ejemplo, `bash bin/vps-artisan.sh migrate --force`. El despliegue existente ya ejecuta una migración: no añadir una segunda ejecución concurrente. Conservar `.env`, claves Passport, archivos y DB persistentes. Un rollback de código no revierte estas migraciones.

En Sail los procesos nuevos se levantan como servicios separados:

```bash
./vendor/bin/sail up -d creative-generation creative-render social-delivery social-reconciliation scheduler
./vendor/bin/sail ps creative-generation creative-render social-delivery social-reconciliation scheduler
./vendor/bin/sail logs --tail=100 creative-generation creative-render social-delivery social-reconciliation scheduler
```

La imagen de producción incorpora los mismos programas en `docker/production/supervisord.conf` como usuario `dbcms`; Sail los ejecuta como `sail`. En VPS/Plesk se deben supervisar estos comandos mediante la CLI PHP nativa y `bin/vps-artisan.sh` de la release, con cierre ordenado y margen de 1500 segundos para los workers. El alojamiento web existente no arranca por sí solo estos procesos.

| Proceso | Comando Artisan dentro del contenedor | Timeout |
| --- | --- | --- |
| Generación | `queue:work marketing --queue=creative-generation --sleep=2 --tries=1 --timeout=720` | 720 s |
| Composición | `queue:work marketing --queue=creative-render --sleep=2 --tries=1 --timeout=300` | 300 s |
| Envío social | `queue:work marketing --queue=social-delivery --sleep=2 --tries=1 --timeout=600` | 600 s |
| Reconciliación/métricas | `queue:work marketing --queue=social-reconciliation --sleep=3 --tries=1 --timeout=180` | 180 s |
| Scheduler | `schedule:work` | Proceso continuo |

Ejecutar un scheduler por instalación o usar réplicas con el mismo cache compartido: los eventos emplean mutex por instalación y `onOneServer`. El scheduler ejecuta `creative-studio:recover` y `social-publishing:dispatch` cada minuto; `social-publishing:dispatch --metrics`, cada hora. No añadir además un cron que dispare los mismos eventos sin revisar la configuración. Reiniciar ordenadamente los workers al cambiar de release con `queue:restart` y verificar sus logs.

## Multimedia y almacenamiento

Antes de activar, comprobar GD con soporte de Freetype, FFmpeg, FFprobe, una fuente TTF legible y espacio temporal/privado suficiente en **la imagen utilizada por los workers**. La imagen de producción incluye estas dependencias; una imagen Sail ya creada debe actualizarse si le faltan.

```bash
./vendor/bin/sail php -r 'var_export(["gd" => extension_loaded("gd"), "freetype" => function_exists("imagettfbbox")]);'
./vendor/bin/sail run ffmpeg -version
./vendor/bin/sail run ffprobe -version
./vendor/bin/sail run sh -lc 'test -r /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
```

El vídeo compuesto es silencioso: no conserva el audio de los clips ni incorpora voz o música. La fuente predeterminada es DejaVu Sans. Las variables efectivas son `MARKETING_FONT_PATH`, `MARKETING_FFMPEG_BINARY` y `MARKETING_FFPROBE_BINARY`; conservar la licencia y versión de las fuentes. `MARKETING_MAX_ARTIFACT_BYTES` limita cada artefacto a 536870912 bytes por defecto. Verificar tamaño, MIME, dimensiones y codec de una composición local, además de su ZIP/manifest. Un archivo aceptado por el CMS puede no cumplir las restricciones de una red: revisar la vista previa de destino antes de aprobar.

## Acceso, activación y cuentas

Resolver `<admin>` con `ADMIN_PATH` de esa instalación; no asumir `/admin` ni copiar una URL de otro minisite. Con una sesión administrativa, abrir:

| Superficie | Ruta |
| --- | --- |
| Activación/configuración | `/<admin>/modules` |
| CreativeStudio | `/<admin>/creative-studio` |
| SocialPublishing | `/<admin>/social-publishing` |
| Guías de CreativeStudio | `/<admin>/modules/creative-studio/guides` |
| Guías de SocialPublishing, incluidas GA4/TikTok | `/<admin>/modules/social-publishing/guides` |
| Guía concreta | `/<admin>/modules/<identifier>/guides/<guide>` |

En Módulos, instalar si corresponde y activar explícitamente solo lo necesario. Verificar permisos de las personas: `manage:creative-studio`; `read:social-publishing`, `manage:social-publishing`, `approve:social-publishing` y `manage:social-accounts` según su función. Las credenciales de generación se guardan en AiAssistant; no pegarlas en briefs, archivos o notas.

Los retornos OAuth registrados son `/<admin>/social-publishing/oauth/{provider}/callback`, para `facebook`, `instagram`, `linkedin` y `youtube`. Copiar la URL exacta que muestra la guía de esa instalación. Registrar aplicaciones del proveedor, dominios/HTTPS, permisos y cuentas de prueba antes del piloto. Completar la selección explícita de cuenta y la comprobación desde SocialPublishing. Las guías de API key, GA4 y TikTok no tienen retorno OAuth del CMS. TikTok usa exportación manual en el adaptador actual.

## Presupuesto, revisión y piloto limitado

CreativeStudio mantiene un presupuesto de instalación y otro por campaña. En ajustes, configurar `installation_budget`, `currency`, `estimate_text`, `estimate_image`, `estimate_video` y `concurrency`; importes enteros en unidades menores de la moneda, estimaciones positivas para cada modalidad solicitada y moneda de campaña coincidente. El presupuesto inicial cero bloquea generación facturable. Revisar también límites y saldo del proveedor: los límites locales son estimaciones, no un tope de facturación externo.

Las reservas contemplan todas las llamadas de texto/imagen/escenas de una solicitud y se conservan si la aceptación remota es incierta. Las tareas completadas siguen contabilizadas; no se aplica un reinicio mensual automático. Distinguir coste reservado/estimado de coste confirmado. Antes de aumentar presupuesto, comprobar reservas y tareas pendientes. La composición con archivos manuales reserva cero y permite comprobar el render sin una petición generativa.

Secuencia de piloto: componer/importar primero una muestra local; revisar archivo final y descarga; conectar una cuenta autorizada de prueba; crear un borrador con destino y UTM; revisar texto, archivos, cuenta, permisos/visibilidad y fecha; aprobar en la interfaz humana la revisión exacta y programar una única entrega. Toda modificación de contenido, archivos, destino, metadatos o identidad/autorización de cuenta exige nueva revisión/aprobación. MCP/API pueden preparar y operar dentro de sus permisos; la aprobación exige la interfaz humana autenticada.

Registrar identificadores locales de campaña/revisión/entrega, hash aprobado, recibo/identificador remoto, resultado visible en la red, hora y costes observados. Después de una entrega, revocar o desconectar la cuenta de prueba si termina el piloto. Ampliar volumen únicamente después de confirmar generación, entrega, recuperación y aislamiento en esa instalación.

## Incidencias, cancelación y resultados inciertos

| Situación | Acción operativa |
| --- | --- |
| CreativeStudio `paused` | Revisar módulo, permisos y proveedor; reanudar explícitamente desde la tarea. |
| CreativeStudio `uncertain` | Consultar la tarea/recibo remoto; usar reconciliación únicamente con correlación persistida. Sin identificador remoto, el CMS rechaza un nuevo pago automático. |
| Worker creativo interrumpido | `creative-studio:recover` marca leases vencidos como inciertos y recupera referencias pendientes; no significa que una generación externa fallara. |
| Entrega social `pending_confirmation` | Comprobar en el proveedor y usar la reconciliación de esa entrega. La ausencia de respuesta no autoriza repetir una mutación. |
| Entrega `blocked` o `paused` | Corregir permisos/cuenta/módulo y revisar antes de programar explícitamente; una reactivación no publica automáticamente lo atrasado. |
| Desactivación/desinstalación | El módulo pierde disponibilidad y conserva tablas/archivos; revisar tareas externas en curso antes de decidir cualquier retirada definitiva. |

Cancelar generación solo está permitido para tareas pendientes/pausadas sin aceptación remota iniciada. Cancelar una entrega social evita trabajo todavía no iniciado y solicita detener las etapas siguientes; no borra ni retracta una publicación ya aceptada por una red. Desactivar un módulo tampoco deshace una llamada externa en curso. Conservar recibos y verificar el resultado remoto antes de liberar reservas o crear un nuevo intento.

No usar `queue:retry all`, repetir la misma publicación a ciegas, vaciar Redis, borrar recibos ni editar estados por SQL para salir de una incidencia. Los workers están configurados con `--tries=1`; la recuperación del dominio decide qué referencias pueden retomarse y qué casos exigen confirmación. Consultar logs sin publicar credenciales, tokens ni URLs de carga firmadas.

## GA4, conversiones y límites de atribución

Analytics debe estar activo para recoger tráfico o devolver informes GA4. En sus ajustes, `ga4_measurement_id` controla la instrumentación del navegador; `ga4_property_id` es el ID numérico de la propiedad y `ga4_service_account_json` la credencial de cuenta de servicio, guardada cifrada y nunca reimpresa en el formulario. Habilitar Google Analytics Data API y dar a esa cuenta acceso de lectura a la propiedad. El informe usa `analytics.readonly`; GA4 no precisa un callback OAuth del CMS.

`ga4_conversion_events` configura nombres permitidos. Solo tras una acción de negocio exitosa emitir `window.cmsTrackConversion(nombre, parametros)` o el evento `cms-conversion-success` con `{name, params}`. El cliente exige consentimiento analítico vigente; al revocarlo deja de emitir. Solo se aceptan identificadores públicos escalares (`content_id`, `item_id`, `campaign_id`), valor numérico y moneda; no enviar valores de formularios, email ni datos personales. Configurar como eventos clave en GA4 los eventos que deban contar como conversión: la lista local por sí sola no cambia la propiedad.

Las UTM se congelan en la revisión que se aprueba. El adaptador filtra exactamente `sessionManualCampaignName` y `sessionManualAdContent` y devuelve `sessions`/`keyEvents` agregados para fechas ISO, hasta 366 días. Las métricas de una red y GA4 son fuentes distintas; no equivalen a ventas demostradas ni a atribución individual. Consentimiento denegado, bloqueadores, procesamiento, umbrales y configuración de eventos limitan lo observable.

`unavailable` conserva valores nulos; `stale` identifica un informe previo con su fecha de obtención; una respuesta válida sin filas sí puede indicar cero. Un cambio de cuenta/credencial no reutiliza el informe de la identidad anterior. No transformar falta de datos en cero ni presentar datos antiguos como una consulta actual. Conservar fuente, intervalo, frescura y limitaciones al exportar un informe.

## Referencia Matrix y cierre del piloto

La referencia Matrix está preservada en `/home/david/.codex/backups/dbcms-matrix-reference/20261013T104120Z`: bundle Git, archivo del árbol de trabajo y manifest. **No contiene un dump de la base de datos remota**; tampoco demuestra una copia completa de datos o credenciales de producción. Conservar y verificar ese material mientras se realiza el piloto.

La retirada de Matrix queda pendiente de un piloto autorizado con cuentas externas reales y evidencia de aceptación funcional. No eliminar repositorio, credenciales, datos remotos ni backup como parte del despliegue de estos módulos. Antes del cierre, completar las copias operativas de DB/archivos/claves de cada instalación y registrar su procedimiento de restauración junto con versión y SHA desplegados.

## Integración prevista en 1.2.0

Los módulos se integran con el actualizador del núcleo en 1.2.0. La validación del árbol integrado y del SHA que se publique está **PENDIENTE**: consultar [la matriz de integración](RELEASE_1.2.0_INTEGRATION.md). Las cifras siguientes corresponden a la rama de trabajo anterior; no certifican esta integración. Conservar las copias históricas y de Matrix. El piloto externo sigue pendiente y no se autoriza retirar Matrix ni publicar automáticamente.

La política 1.2.0 declara `rollback_compatible=true` tras revisar expresamente las cinco migraciones aditivas: solo crean tablas o amplían tablas nuevas de los módulos, sin cambiar contratos de core 1.1.0; los módulos parten inactivos. Esto habilita el recorrido standalone. Un rollback de código no revierte DB, y el rollback automático sigue prohibido tras `migration_started`; cualquier fallo posterior o incierto exige recuperación guiada. El arranque de core 1.1.0 con DB migrada queda PENDIENTE de E2E. Seguir la recuperación guiada del [actualizador](CORE_UPDATES_RUNBOOK.md), con respaldo comprobado de DB, archivos y claves.

## Evidencia histórica anterior a la integración

Comprobado el 13 de octubre de 2026 sobre cambios locales que parten de `f90131bb7dc745210443ab37d8bc76fa83a46736`; todavía no corresponden a una release publicada ni a un piloto externo.

| Gate | Evidencia |
| --- | --- |
| CreativeStudio, SocialPublishing, guías, GA4 y traspaso JPEG | 82 pruebas Pest, 567 aserciones; incluye MP4 real de 30 segundos, claims antiguos, aprobación, OAuth y archivos independientes |
| Credenciales, comprobaciones de IA y documentación | 31 pruebas Pest, 126 aserciones |
| Grupos con archivos compartidos | 169 pruebas Pest en serie, 1312 aserciones |
| Regresión de los grupos heredados sensibles al aislamiento | 50 pruebas Pest en serie, 191 aserciones |
| Instalación, actualización y activación explícita | 40 pruebas Pest, 146 aserciones; instalar/reinstalar no activa estos módulos |
| Análisis y estilo | PHPStan nivel 8 sin errores; Pint correcto |
| Frontend | 46 pruebas Node correctas y build Vite correcto |
| Runtime | Imagen Docker construida; GD, Redis, DejaVu, FFmpeg/FFprobe y rutas comprobados; cuatro workers y scheduler arrancados |

Los números de los grupos no se suman: existen pruebas repetidas. La exploración paralela del conjunto restante produjo 1340 pruebas correctas, 12 avisos y ocho fallos: siete de fixtures/aislamiento y uno por el helper de traducción ausente de las dependencias permitidas. Se declaró ese helper de Laravel y los archivos afectados se verificaron después en serie. Usar el gate canónico en serie para esos grupos heredados. No se han añadido ignores a PHPStan ni relajado las comprobaciones de aprobación, presupuesto o integridad.

Las migraciones locales son aditivas. Los módulos quedan desactivados y no se han ejecutado generaciones facturadas ni publicaciones externas. El piloto completo sigue pendiente de cuentas, permisos y credenciales autorizados, siguiendo los tutoriales incluidos.
