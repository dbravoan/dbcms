# Operación y piloto de CreativeStudio y SocialPublishing

Este manual corresponde a los contratos de [CreativeStudio](../specs/FEAT-CREATIVE-STUDIO.md) y [SocialPublishing](../specs/FEAT-SOCIAL-PUBLISHING.md). Ambos módulos son opcionales y se distribuyen sin instalar. Sus guías se pueden consultar antes de activarlos. SocialPublishing admite archivos manuales y funciona sin CreativeStudio; la generación con proveedores requiere AiAssistant disponible. Analytics aporta GA4 de forma opcional.

Las pruebas con HTTP simulado verifican contratos locales. Cada instalación necesita validar sus credenciales, cuentas autorizadas, generación facturada y publicaciones externas mediante un piloto autorizado. No interpretar una guía marcada, una cuenta creada ni un catálogo de modelos como una conexión o publicación demostrada.

## Composición explícita

`marketing` contiene archivos privados y contratos de integración. CreativeStudio,
SocialPublishing y Analytics lo declaran como dependencia obligatoria. Seleccionar
la infraestructura junto con los módulos utilizados antes de instalar. La
selección no descarga dependencias Composer ni npm durante peticiones HTTP.
El núcleo no registra sus servicios, colas ni workers al arrancar.

## Identidad y aislamiento antes del piloto

Cada minisite necesita su propia base de datos, almacenamiento persistente privado, `APP_KEY`, cuentas/configuración y un `CMS_INSTALLATION_ID` único y estable. Fijar este último explícitamente antes de crear tareas; el fallback actual deriva de `APP_KEY`. Conservar ambos valores al actualizar: cambiar la identidad oculta referencias de archivos, progreso y namespaces anteriores; rotar `APP_KEY` impide descifrar secretos existentes.

Las colas, caché y locks nuevos de marketing usan el namespace de instalación. El directorio predeterminado es `storage/app/private/marketing/<installation_id>`; `MARKETING_ARTIFACT_ROOT` permite configurar una raíz privada dedicada. El usuario de los workers debe poder leer y escribir ahí. No servir ese directorio mediante Nginx ni `storage:link`. Los archivos sociales tienen copia propia y las URLs firmadas para ingestión externa son temporales.

El prefijo general Redis heredado y la cola general se conservan para no abandonar tareas históricas de IA. **No se han migrado automáticamente todas las claves antiguas.** Antes de compartir Redis entre instalaciones, comprobar también `REDIS_PREFIX`, `CACHE_PREFIX` y `SESSION_COOKIE` distintos, o usar instancias Redis independientes. No rotar el prefijo de una instalación con jobs antiguos pendientes: primero drenar su cola y recuperar sus tareas. Este aislamiento es un requisito de entrada al piloto.

Revisar `.env.example`, `.env.production.example`, `modules/Marketing/Config/marketing.php`, `config/database.php` y `config/queue.php`. Redis debe admitir locks. El canal `marketing` usa la conexión Redis `marketing` y `MARKETING_QUEUE_RETRY_AFTER=1500`; este margen supera los timeouts de los cuatro workers. La conexión respeta `REDIS_URL` cuando se configura. Mantener estable la configuración de todas las réplicas de una instalación.

## Release, migración y procesos

Usar una release que haya superado el gate para su SHA exacto; seguir [DEPLOYMENT_GUIDE.md](DEPLOYMENT_GUIDE.md) para VPS/Plesk o Docker de producción. Registrar por instalación versión de `VERSION`, SHA, checksum del paquete o digest de imagen, fecha UTC, responsable, identidad de instalación y resultado de migración. No registrar claves ni JSON de cuentas de servicio.

```bash
git rev-parse HEAD
./vendor/bin/sail artisan app:version
./vendor/bin/sail artisan migrate --force
./vendor/bin/sail artisan migrate:status
./vendor/bin/sail artisan schedule:list
```

Estos comandos son para Sail local. En VPS/Plesk, desde la release y con la CLI PHP nativa compatible, usar `bash bin/vps-artisan.sh <comando-artisan>`; por ejemplo, `bash bin/vps-artisan.sh migrate --force`. El despliegue existente ya ejecuta una migración: no añadir una segunda ejecución concurrente. Conservar `.env`, claves Passport, archivos y DB persistentes. Un rollback de código no revierte estas migraciones.

En Sail los procesos nuevos se levantan como servicios separados:

```bash
./vendor/bin/sail --profile creative --profile social up -d
./vendor/bin/sail ps creative-generation creative-render social-delivery social-reconciliation scheduler
./vendor/bin/sail logs --tail=100 creative-generation creative-render social-delivery social-reconciliation scheduler
```

Sail mantiene estos workers fuera del arranque básico: activar solo los perfiles
`creative` o `social` necesarios. En producción Supervisor los inicia únicamente
con `DBCMS_CREATIVE_WORKERS=true` o `DBCMS_SOCIAL_WORKERS=true`; ambos valen `false`
por defecto. En VPS/Plesk se supervisan explícitamente mediante la CLI PHP nativa
y `bin/vps-artisan.sh`, con cierre ordenado y margen de 1500 segundos.

| Proceso | Comando Artisan dentro del contenedor | Timeout |
| --- | --- | --- |
| Generación | `queue:work marketing --queue=creative-generation --sleep=2 --tries=1 --timeout=720` | 720 s |
| Composición | `queue:work marketing --queue=creative-render --sleep=2 --tries=1 --timeout=300` | 300 s |
| Envío social | `queue:work marketing --queue=social-delivery --sleep=2 --tries=1 --timeout=600` | 600 s |
| Reconciliación/métricas | `queue:work marketing --queue=social-reconciliation --sleep=3 --tries=1 --timeout=180` | 180 s |
| Scheduler | `schedule:work` | Proceso continuo |

Ejecutar un scheduler por instalación o usar réplicas con el mismo cache compartido: los eventos emplean mutex por instalación y `onOneServer`. El scheduler ejecuta `creative-studio:recover` y `social-publishing:dispatch` cada minuto; `social-publishing:dispatch --metrics`, cada hora. No añadir además un cron que dispare los mismos eventos sin revisar la configuración. Reiniciar ordenadamente los workers al cambiar de release con `queue:restart` y verificar sus logs.

## Multimedia y almacenamiento

Antes de activar, comprobar GD con Freetype, FFmpeg, FFprobe, fuente TTF y espacio
privado en **la imagen utilizada por los workers**. La imagen básica no instala
FFmpeg ni fuentes: construir la variante creativa con
`docker build --build-arg WITH_CREATIVE_TOOLS=true -f docker/production/Dockerfile .`.
Una imagen Sail debe incorporar esos binarios antes de activar composición.

```bash
./vendor/bin/sail php -r 'var_export(["gd" => extension_loaded("gd"), "freetype" => function_exists("imagettfbbox")]);'
./vendor/bin/sail exec laravel.test ffmpeg -version
./vendor/bin/sail exec laravel.test ffprobe -version
./vendor/bin/sail exec laravel.test sh -lc 'test -r /usr/share/fonts/truetype/dejavu/DejaVuSans.ttf'
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

## Cierre del piloto

Antes de activar producción, completar las copias operativas de base de datos, archivos y claves de cada instalación y comprobar su restauración. Registrar el resultado del piloto con sus cuentas autorizadas, versión y SHA, sin publicar credenciales ni datos privados.

## Instalación y validación en v3

CreativeStudio, SocialPublishing y Analytics requieren el módulo Marketing.
Subir un ZIP no instala ni activa el módulo. La instalación explícita instala y
activa el módulo cuando se satisfacen sus dependencias; desactivarlo después
conserva sus datos. La base
3.0.1 es para instalaciones nuevas; no convierte instalaciones anteriores.
La política de actualización declara `rollback_compatible: false`. Un rollback
de código no revierte la base de datos; seguir la recuperación guiada del
[actualizador](CORE_UPDATES_RUNBOOK.md), con respaldo comprobado de DB, archivos y claves.

La preparación local de release debe verificar aprobación, OAuth, presupuestos,
archivos independientes, generación multimedia y workers en el entorno desechable.
Un piloto externo requiere cuentas, permisos y credenciales autorizados. Las pruebas
simuladas y el build local no certifican una publicación externa.
