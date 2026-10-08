# Revisión final del sistema — 12 de octubre de 2026

> Corte histórico anterior a la ronda de correcciones. El [cierre de pendientes y arquitectura](PENDING_CLOSURE_AND_QUALITY_VALIDATION_2026-10-12.md) registra los cambios posteriores, la verificación conjunta y las condiciones externas aún pendientes.

Estado: revisión completada; gate global y arranque productivo local superados,
con límites pendientes declarados. Este documento distingue código,
pruebas con fixtures, contratos con HTTP simulado, funcionamiento local y servicios
externos. No certifica todas las combinaciones posibles ni posicionamiento perfecto.

## Alcance y protección del entorno

Se inventariaron **16 módulos** y **151 rutas administrativas registradas** en el
runtime local, además de API, MCP, jobs, módulos públicos, instaladores y despliegue.
El recuento depende de la configuración y del discovery al recogerlo; no es una
medida de cobertura de líneas ni una prueba de todas sus variantes.

Las correcciones se reprodujeron primero con regresiones. Cada área usó MySQL
desechable propio. Los tests CLI de instalación se ejecutaron en una copia con
`.env`, storage y uploads independientes: aislar sólo la base no protege los archivos
del usuario. El gate final usa otra imagen, red y servicios propios del proyecto
Compose `dbcms-finalaudit20261012`. No se hicieron cambios en GitHub, despliegues,
generaciones remotas IA ni envíos de correo a usuarios.

Una repetición focal del smoke utilizó inicialmente la base `testing` del runtime
de desarrollo al no propagarse sus variables al contenedor. Se corrigió la invocación
y se repitió contra MySQL desechable. La base de contenidos del CMS no fue el destino.
El resultado final acreditado procede del runtime CI separado, incluidos sus archivos.

## Hallazgos corregidos

| Área | Fallo reproducido | Corrección y evidencia |
|---|---|---|
| Entrada administrativa | Cualquier rol concedía acceso aunque faltara `access:admin` | Permiso explícito; web redirige a acceso y JSON deniega; regresiones de rol mínimo |
| Política editorial | Autor podía desactivar revisión o cambiar roles aprobadores | Ajustes requieren administración; pestaña restringida |
| Revisiones privadas | Cola/diff y comparaciones aceptaban entidades ajenas | Política por actor, entidad y propiedad; comparación de la misma entidad; independiente del toggle editorial |
| Búsquedas administrativas | Fuentes privadas y resolución por IDs omitían límites del listado | Registro de permisos por fuente, disponibilidad de módulo y mismo ámbito en search/resolve |
| Biblioteca y dashboard | Lecturas sin permiso y leads/borradores ajenos en dashboard | Acceso a medios autorizado; servicio de dashboard filtra por permiso y propiedad |
| Auditoría MCP | Ruta directa de log sin la misma autorización que el catálogo | Revalidación de permiso administrativo y scopes en el servicio común |
| Medios MCP | MIME falso y archivos grandes aceptados por ruta alternativa | Validación común de MIME/extensión y 50 MB; límite base64 antes de decodificar |
| Instalación CLI | Escritura `.env` interpolada/no atómica, demo implícita y foundation incompleto | Serialización compartida, reemplazo 0600, demo elegida, foundation y resultados de comandos comprobados |
| Credenciales de arranque | Contraseña administrativa pública por defecto | Cuenta y contraseña introducidas por usuario; validación mínima y sin impresión |
| Clave de cifrado | Clave válida del entorno no persistida; generación podía anunciar éxito sin guardarla | Conserva y verifica la clave sin rotarla; escritura atómica; rechaza claves incompatibles y exige email explícito en instalación no interactiva |
| Export GEO | Ficheros públicos estáticos sobrevivían a retirada de contenido | Snapshot privado y Nginx dinámico; rechazo de destinos públicos, incluidos symlinks |
| Taxonomías | Categorías fuera de mercado/inactivas en URLs e índices | Reglas comunes de actividad y mercado en Blog/Shop, sitemap y corpus |
| Corpus público | Precio oculto expuesto; faltaban bloques y fuentes canónicas | Respeta visibilidad; usa bloques publicados y enlaza URLs públicas |
| Idiomas | Alternate de producto en mercado inactivo y estado compartido del request anterior | Pivots disponibles y DTO con alternates explícitos |
| Publicación programada | Fecha futura ya accesible/indexable | Scopes públicos exigen fecha no futura |
| Honestidad del panel | GEO 100%, validación externa ficticia y tipos schema ignorados | Observaciones locales sin garantías; tipos realmente implementados |
| Imágenes SEO | Logo e imagen social opcionales apuntaban a archivos ausentes | Omite fallbacks locales inexistentes y normaliza URLs relativas; no consulta destinos externos ni inventa una marca |
| Template | Release heredada intentaba publicar imagen en registry del core | Destino de imagen desde el repositorio actual; regresión RegulaSE |

El permiso explícito endurece compatibilidad: un rol personalizado con sólo
`manage:posts` necesita también `access:admin` para la interfaz web. No se amplían
roles personalizados automáticamente. MCP/API siguen sus propias identidades,
permisos y scopes; ser administrador web no sustituye scopes OAuth.

Se revisó también la publicación de la plantilla con un escaneo limitado por patrones:
1.584 archivos candidatos del working tree y 50 commits recientes, sin coincidencias
de los patrones examinados. No equivale a una auditoría completa de secretos ni cubre
todo el historial, tags o estado privado ignorado. La revisión previa al push sigue
siendo obligatoria en el procedimiento documentado.

## Cobertura por módulo y núcleo

| Área | Superficies revisadas | Familias de pruebas existentes y nuevas |
|---|---|---|
| Pages | Crear/editar, mercados, traducciones, secciones, revisión, publicación, rutas públicas | CorePages, EditorialMutationParity, LocalizedEditorPersistence, FinalDiscoveryIntegrity |
| Blog | Posts, categorías, fechas, fuentes, listados, comentarios y metadata | BlogCategoryMutationParity, PostBlockManager, publicación futura y categorías por mercado |
| Shop | Producto/categoría, carrito acumulado, checkout transaccional, pedidos/presupuestos, precio público | ShopMutationParity, ShopSafetyRegression, MarketPriceRegression, concurrencia de stock |
| Gallery | CRUD, imágenes/alt, mercados, traducción y revisión | GalleryMutationParity, GalleryModuleLocalizedRouting |
| Forms | Builder/campos, publicación, envío, leads y aislamiento | FormBuilder, FormFieldFramework, Submission, permisos fuente y dashboard |
| AiAssistant | Permisos, modelos/modalidades, borradores, contexto, bloques/portadas y traducción | AiDraftSafety, AiEntityTranslationIntegration, MediaEntrypoints y respuestas simuladas |
| Mcp | Identidad humana/M2M, scopes, transporte/SSE, capacidades, auditorías y confirmaciones | McpBackofficeCapabilities, M2mAudit, HttpTransport, AiParity y mínimo privilegio |
| ContentAudit | Ajustes, cola, propietario, aprobación/rechazo, diff/rollback y snapshot live | ContentAuditModule, EditorialEnforcement, comparación privada y política desactivada |
| Search | Indexación, consulta pública, fuentes y selectores administrativos | SearchIndexing, SearchEngine, EntitySearch y resolve/search equivalente |
| Maintenance | Disponibilidad, acceso/preview y respuesta pública | MaintenanceModule y muestra de superficie administrativa |
| Analytics | Consentimiento y carga de scripts | GDPR y regresiones Node de consentimiento |
| Meta | Configuración/cifrado, consentimiento y hooks | Contratos de tracking y aislamiento de configuración |
| Ads | Configuración, posiciones y disponibilidad | TrackingAvailability y seguridad de salida |
| Captcha | Drivers, campo opt-in, validación y disponibilidad | CaptchaDrivers y FormFieldFramework |
| Charts | Configuración de bloque, payload y salida | DynamicWidgets y ChartLinePayload |
| BeforeAfter | Imágenes, estructura y teclado | DynamicWidgets y evidencia de interfaz de la ronda anterior |
| Núcleo | Mercados/idiomas, menús, usuarios/roles, conexiones, medios, temas, notificaciones | RBAC, MarketsLanguages, Menus, ExternalConnections, MediaManagerSecurity, ThemeSystemIntegrity |
| Orquestación | Catálogo efectivo, actor, aprobaciones, persistencia, idempotencia y recuperación | NativeExecution, CapabilityArchitecture, ModuleExecutionIndependence |
| Entrega | Dependencias, Docker, Nginx, configuración, health, release y recuperación | DeploymentSafety, VpsDeploymentContract, CLIInstallerSafety y gate Docker |

La nueva matriz HTTP comprueba las **rutas GET administrativas sin parámetros**
en ambos temas, además de settings de todos los módulos descubiertos. APIs sin sus
argumentos obligatorios deben devolver validación; features deshabilitadas pueden
devolver 404 conforme a su gate. Esto se registra por ruta. Las rutas con parámetros,
mutaciones, jobs y protocolos se comprueban por sus pruebas de dominio: el smoke
de GET no acredita crear, publicar o borrar. Una captura tampoco acredita todas las
acciones de una pantalla. Mantener esta distinción evita inventar «100% funcional».

Las únicas redirecciones admitidas por el smoke de pantallas son las de acceso ya
autenticado y los alias de dashboard, SEO y ajustes de Shop/Maintenance, comprobando
también su destino. Una
redirección a instalación o login desde otra pantalla no cuenta como éxito.

La captura de [la auditoría SEO local](evidence/final-audit-2026-10-12/seo-audit-live.jpg)
documenta la interfaz TermoCMS revisada. Se comprobaron los mismos contratos de
renderizado con dbCMS. No se invocaron acciones de publicación desde el navegador.

## SEO, GEO y AEO: criterios de salida

- Sólo URLs públicas y disponibles en el idioma/mercado efectivo; sin borradores,
  snapshots pendientes, entidades inactivas ni publicaciones futuras.
- Canonical y alternates con destino real, metadata coherente con el contenido y
  JSON-LD serializado con seguridad, limitado a los tipos implementados.
- Sitemap/RSS parseables y corpus fiel a bloques publicados, con sus fuentes.
- Retirada o cambio de mercado invalida las superficies dependientes; el export
  privado no es otra publicación. No exponer precios cuya visibilidad esté desactivada.
- Las observaciones editoriales describen campos y estructura; no validan hechos,
  normas, credenciales de autores ni citas de buscadores.

No hay un score universal «SEO/GEO/AEO perfecto». Google no exige un archivo
especial ni un schema GEO para sus funciones de IA; la indexabilidad y el contenido
útil siguen siendo esenciales. El diagnóstico del CMS no certifica ranking.
[Guía oficial de Google](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide).
Rich Results/Schema validator, HTTPS y canonical definitivo, Search Console y Bing
requieren el dominio público y su autorización; no se simula ese resultado localmente.

## Límites y trabajos pendientes explícitos

1. `ModuleNotificationService::dispatchEmailNotification` registra un log; no acredita
   enviar o recibir email. Las notificaciones internas sí tienen pruebas. No ofrecer
   confirmaciones de email de RegulaSE hasta implementar y probar el transporte real.
2. `ModuleManager::uploadPackage` todavía necesita staging de extracción, límites
   de tamaño expandido/número de entradas y comprobación de extracción antes de
   instalar. Es una operación privilegiada; no se ha demostrado una explotación remota
   sin permisos. Evitar paquetes de terceros no revisados en el primer lanzamiento.
3. `lastmod`/`dateModified` no incluyen todas las modificaciones directas de traducciones
   y secciones. Segmentar adicionalmente los sitemaps antes de superar 50.000 URLs
   o 50 MB por fichero; esa escala no se ha alcanzado ni probado en esta instalación.
4. La disponibilidad exactamente al vencer una fecha futura puede depender de TTL
   e invalidación programada; no se garantiza publicación instantánea al segundo.
5. GEO no registra automáticamente cualquier módulo futuro ni expande todos los
   widgets dinámicos. Cada nuevo tipo necesita su contrato, extractor y pruebas.
6. No se repitieron generaciones externas agotadas o inciertas: Gemini/OpenAI y
   modalidades imagen/vídeo, proveedores y almacenamiento externos requieren cuota,
   conexiones compatibles y una ronda real autorizada. Los HTTP simulados validan
   contratos, no credenciales reales ni entregas del proveedor.
7. Arquitectura y seguridad no tienen certificación absoluta: hay código heredado
   pendiente de adelgazar y revisiones por muestras. PHPStan tiene el alcance declarado
   en `phpstan.neon`; no prueba todos los comportamientos ni incluye tests/Blade.
8. Las pruebas locales no sustituyen carga sostenida, restore de un backup del
   alojamiento real, recepción de correo, revisiones jurídicas o métricas de buscadores.

## GitHub y RegulaSE

La guía usa el repositorio actual `dbravoan/dbcms`, elegido por el usuario. Incluye
commit exacto, exclusión de estado privado e historial, template, copia independiente,
instalación sin demo, marca/tema, claves propias, alojamiento existente y estrategia
de actualizaciones. No se publicó ni creó el proyecto por documentar sus pasos.

- [Procedimiento de plantilla y creación](GITHUB_TEMPLATE_REGULASE_RUNBOOK.md).
- [Definición editorial de RegulaSE](REGULASE_PROJECT_BLUEPRINT.md).

RegulaSE puede defender requisitos de calidad y competencia justa, y examinar costes
burocráticos. Debe distinguir datos, hechos normativos y opinión, y no presumir
incumplimiento por procedencia extranjera. Las importaciones deben cumplir la normativa
de producto aplicable; CE no acredita origen europeo ni una aprobación de la Comisión.
[Conformidad](https://europa.eu/youreurope/business/product-rules-compliance/general-product-compliance/index_en.htm),
[CE](https://single-market-economy.ec.europa.eu/single-market/goods/ce-marking_en).

## Verificación final

El gate final `bin/ci-check` terminó con **salida 0** en un contenedor CI construido
desde la instantánea revisada. Los logs por área son evidencia focal; no se suman
como si fueran pruebas distintas del gate global.

| Comprobación global | Resultado |
|---|---|
| Pest: arquitectura, unidad, funcionales, regresión y concurrencia | **1.302 pruebas / 12.606 aserciones; todas pasan**, 614,40 s |
| Pruebas Node de comportamiento del navegador | **40 pasan**, ninguna omitida ni fallida |
| PHPStan nivel 8 | **0 errores**, alcance app, módulos, config, database y routes |
| Pint | **1.050 archivos**, sin errores de estilo |
| Composer | Validación estricta e instalación correctas; auditoría sin avisos de vulnerabilidad |
| npm | Instalación reproducible, auditoría con cero vulnerabilidades y build Vite correcto |
| Docker productivo | Build final correcto; instalación limpia, integridad y HTTP comprobados |
| Diff y documentación | Sin errores de whitespace; siete enlaces relativos nuevos comprobados |

Las auditorías de dependencias reflejan los avisos conocidos al ejecutarlas; no
certifican ausencia absoluta de vulnerabilidades. La matriz HTTP anterior está
incluida en Pest, no se suma otra vez. Node valida código del navegador mediante
pruebas automatizadas; no son 40 recorridos visuales manuales.

Dos rondas globales anteriores encontraron un problema de formato y nueve
expectativas/fixtures incompatibles con el endurecimiento. Se corrigieron antes
de esta ronda final. Los cinco fallos de traducción se reprodujeron sin marcador
de instalación: el fixture cambia de entorno para usar el adaptador HTTP y activaba
la redirección a `/install`. Se aisló únicamente ese middleware en dichas pruebas,
manteniendo autenticación, permisos, scopes, CSRF y contratos HTTP activos.

### Resultados productivos y de superficie ya comprobados

- Matriz HTTP: **4 pruebas / 178 aserciones**. Por tema: 44 rutas GET, con 37 respuestas
  200, cinco alias/acceso autenticado 302 con destino comprobado y dos 422 por faltar
  argumentos de API. Se renderizan además los ajustes de los 16 módulos en ambos temas.
- Instalación productiva sin demo: un administrador, cero páginas/posts/productos,
  ocho idiomas y ocho mercados de foundation. La clave suministrada coincide con la
  persistida, `.env` y clave OAuth privada tienen permisos 0600; hay marcador instalado.
- Build productivo: manifiesto presente, dos entradas y ningún archivo referenciado
  ausente. Laravel arranca con dependencias de producción.
- Ocho comprobaciones HTTP reales contra Nginx/PHP-FPM: salud, login, robots,
  sitemap XML parseable, ambos corpus dinámicos, subida estática legible y PHP subido
  rechazado con 404. Los ficheros GEO estáticos de prueba no se sirven.
- Los 1.388 archivos congelados de runtime, pruebas, configuración, dependencias y
  entrega coinciden por SHA-256 con ambos contenedores construidos. El informe se
  completa después; su prosa no forma parte de esa comprobación de código.

Imagen de producción local: `dbcms:final-audit-20261012`,
`sha256:2c99e2fb041a51d61b4fb75d063d853037e0459a5c6d2a4d061d32e6e351b9ee`.
No es una imagen publicada ni un commit nuevo. HEAD previo:
`9405ce6d773ea4d5bcf455814dc55edefcd2fad6`; se validó el working tree con sus cambios.
Las evidencias técnicas privadas quedan en
`storage/app/runtime-verification/final-audit-20261012/`, excluidas de publicación.
Se retiraron los contenedores, bases, red y volúmenes temporales de esta revisión,
así como sus archivos de entorno y copia CLI privada. Se conservan los logs con
permisos restrictivos y las imágenes locales para reproducir el estado validado.
Los servicios y datos de desarrollo del usuario continúan disponibles.
