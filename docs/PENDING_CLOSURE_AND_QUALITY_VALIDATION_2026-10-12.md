# Cierre de pendientes, arquitectura y Auditoría y Calidad

Fecha: 12 de octubre de 2026. Estado: cierre implementado y gate conjunto superado.
Este informe actualiza [la revisión anterior](FINAL_SYSTEM_AUDIT_2026-10-12.md);
no reemplaza sus evidencias históricas ni suma sus pruebas a las de esta ronda.

## Correcciones realizadas

| Pendiente anterior | Comportamiento implementado | Evidencia y operación |
| --- | --- | --- |
| Correo era sólo un log | Job real, entrega persistida, preferencias y permisos vigentes, envío tras commit, fallo observable | SMTP local aislado con un mensaje; [correo](MODULE_NOTIFICATION_AND_PACKAGE_SECURITY.md) |
| ZIP sin staging/límites | Inspección, límites expandidos, CRC/rutas/enlaces, staging privado y promoción atómica sin reemplazar módulos | Paquetes inválidos, colisión y rollback; mismo documento |
| Fechas públicas incompletas | Traducciones, bloques y mercados publicados participan en fecha e invalidación | Regresiones de cambios públicos y privados; [descubrimiento](GEO_PUBLIC_CONTRIBUTORS.md) |
| Sitemap sin segmentación de límites | Hijos con límites de URLs/bytes y rutas regionales segmentadas | XML, rutas e índices con límites inferiores de prueba |
| Publicación dependía de TTL general | Huella temporal y vencimiento de caché en la siguiente publicación; filtros vigentes al leer | Casos antes/en la frontera sin nuevo guardado ni cron |
| GEO conocía sólo contribuciones incorporadas | Registro público de entidades/widgets propietarios y selección contextual | Sólo módulos/mercados/idiomas disponibles, sin precios ocultos ni borradores |
| Búsqueda de páginas/galerías anunciada sin fuente | Fuentes reales y actualización de relaciones; indexación agrupada | Contextos, desactivación, programación y reconstrucción |
| Reconstrucción fallida dejaba índice parcial | Sustitución local atómica y batching que conserva la excepción original | Fuente que falla después del primer lote |
| Controladores y dependencias amplios | Lectores/servicios y DTO; contratos de editorial/mantenimiento/estadísticas; adaptadores compatibles | Restricciones de arquitectura y recorridos HTTP conservados |
| Contador editorial global revelaba revisiones ajenas | Conteo por actor, permiso, propiedad y módulo disponible | Autor sólo ve su expediente; sin permiso o módulo, cero |
| Búsqueda releía el mercado por cada resultado | Resolución única del mercado para la lista | Fixture de diez resultados: 12 búsquedas de mercado antes, máximo 3 tras corregir (incluye fulltext/fallback) |
| Carrito releía catálogo para cada total | Resumen único compartido por API/cart/checkout y reglas en servicio propietario | Una consulta de productos por resumen, importes y compra manual |

## Auditoría y Calidad revisada

Se reprodujeron fallos de autoridad con flujo desactivado, historial incompleto,
restauración parcial, revisión obsoleta, aprobación que modificaba contenido público
sin publicar, bypass por guardado sincronizado y avisos de versiones reemplazadas.
El servicio común conserva traducciones, bloques, mercados y relaciones; la revisión
se vincula a actor, entidad y versión. Desactivar/reinstalar no concede autoridad ni
reactiva por sí solo el flujo.

Guardar o aprobar sin publicación mantiene privada la modificación y conserva la
fecha pública. Publicar o una restauración aplicada actualiza la representación
pública después del commit. MCP/IA requieren confirmación humana para decisiones
que puedan publicar, despublicar o restaurar. Un prompt no concede aprobación.

Las comprobaciones incluyen ambos temas, propiedad de revisión y entidad,
conflictos, recuperación transaccional, calidad de traducciones/bloques completos,
historial al borrar y notificaciones vigentes. El panel local se abrió sin modificar
contenido del usuario: [captura](evidence/pending-closure-2026-10-12/content-audit-live.jpg).
La cola estaba vacía; la captura acredita renderizado, las mutaciones están probadas
con fixtures aislados. Operación detallada: [flujo editorial](CONTENT_AUDIT_WORKFLOW.md).

## Arquitectura y mantenimiento

Se extraen consultas/decisiones de formularios, menús, lectores públicos de los
cinco dominios, compra/carrito, instalador, settings y mantenimiento. Se conservan
rutas, nombres y formatos mediante adaptadores. El núcleo editorial consume un
contrato estable; sus DTO no exponen modelos del módulo opcional. Mantenimiento y
estadísticas de búsqueda tienen fronteras equivalentes. Las operaciones ausentes
no simulan aprobación ni éxito de una modificación.

Las restricciones AST comprueban aliases, consultas/persistencia, tamaño de acción,
contratos y dependencias HTTP; incluyen fixtures inválidos para demostrar que fallan.
También distinguen efectos dentro de una transacción de los diferidos al commit.
Su alcance es explícito: no se declara que cualquier controlador heredado cumpla
la nueva frontera por pasar el test genérico de capas.

Se alinean las guías existentes, retirando cifras y garantías no acreditadas,
ejemplos que parecían implementaciones reales y recomendaciones de DTO que recibían
Request. La [guía de mantenimiento](ARCHITECTURE_MAINTENANCE_GUIDE.md) deja mapa,
decisiones/tradeoffs, recorrido común, puntos de extensión, medición, verificación
aisladamente y límites heredados para IAs y desarrolladores.

## Verificación externa y alcance

- **Gemini:** generación real de texto con `gemini-2.5-flash` correcta. Se conserva
  únicamente estado/modelo/tiempo/uso en evidencia privada; no la clave.
- **OpenAI:** la llamada real devolvió HTTP 429. No se anuncia generación realizada
  ni se deduce su causa exacta. Cuota/facturación/límites son condiciones de la cuenta.
- **Imagen/vídeo, almacenamiento y otras aplicaciones:** contratos HTTP simulados;
  no se acreditan entregas externas sin conexión/modalidad operativa.
- **Correo:** SMTP aceptado en Mailpit local. `sent` no certifica recepción, lectura
  ni ausencia de rebote del proveedor de producción.
- **Dominio:** el usuario confirmó que todavía no existe uno publicado. HTTPS,
  canonical definitivo, validadores/rastreo y recuperación del alojamiento real se
  comprobarán después del despliegue conforme al [procedimiento](GITHUB_TEMPLATE_REGULASE_RUNBOOK.md).

SEO/GEO/AEO describen contenido público coherente. La heurística editorial no
verifica autoría, hechos o normativa; los tests no certifican ranking ni todas las
combinaciones posibles. No se publicaron contenidos, código o repositorios ni se
contactó con personas externas durante esta revisión.

## Cambios de instalación y compatibilidad

La migración aditiva `2026_10_12_110000_add_notification_email_delivery_and_preferences`
añade privacidad/visibilidad y tablas de preferencias/entregas. Los avisos privados
se mantienen privados si se elimina su destinatario. Se aplicó esta migración
específica al entorno local existente; no se ejecutó un reset de su contenido.

En una instalación existente: aplicar migraciones de la release validada antes
de activar sus funciones, reconstruir búsqueda para incorporar Pages/Gallery y
reiniciar workers para cargar código/configuración nuevos. Conservar APP_KEY y
claves OAuth, comprobar permisos y hacer copia previa. Una recuperación de código
no revierte datos automáticamente. SMTP necesita configuración propia y worker si
la cola es asíncrona. No se habilitan proveedores ni se amplían roles por suposición.

## Gate final

`bash bin/ci-check` terminó con salida **0** en el proyecto Docker aislado
`dbcms-pendingclosure20261012`, con base, filesystem y claves propios.

| Verificación | Resultado sobre la instantánea final |
| --- | --- |
| Pest: arquitectura, unidad, integración, regresión y concurrencia | **1.430 pruebas / 15.081 aserciones; todas pasan**, 816,15 s |
| Node: comportamiento del navegador | **40 pruebas**, sin fallos ni omisiones |
| PHPStan nivel 8 | **0 errores**; 952 archivos del alcance declarado |
| Pint | **1.214 archivos** conformes |
| Composer/npm | Validación e instalación correctas; cero avisos de vulnerabilidad conocidos al ejecutar sus auditorías |
| Compilación | Vite y ambas imágenes Docker construidas correctamente |
| Instalación productiva limpia | Un administrador, cero páginas/posts/productos, ocho idiomas y ocho mercados; clave suministrada conservada |
| Configuración y OAuth | `.env` y clave privada con permisos 0600; depuración desactivada; contraseña administrativa verificada internamente |
| Assets | Dos entradas del manifiesto y ningún archivo referenciado ausente; las 19 fuentes advertidas por Vite existen y responden 200 en runtime |
| HTTP real con Nginx/PHP-FPM | Nueve comprobaciones principales y cuatro hijos XML del sitemap correctos; PHP subido 404 y `.env` 403 |
| Igualdad de código | **1.576 archivos** comprobados por SHA-256 en CI y producción, sin ausencias ni diferencias |
| Entorno local existente | Índice reconstruido: 9 documentos, incluidos 4 páginas y 2 galerías; namespace público invalidado y worker reiniciado sin cancelar tareas activas |

La integración encontró además un observador que leía `page_id` en una traducción
cuyo campo real era `page_section_id`, y una política de credenciales que dependía
de la implementación concreta del almacenamiento. Se corrigieron sin desactivar
el modo estricto ni debilitar la prueba de endpoint. Cuatro fallos adicionales eran
expectativas/mocks anteriores a los nuevos contratos o a la retirada de estadísticas
ficticias; se adaptaron conservando aislamiento de mercado, publicación y privacidad.
La llamada antigua de la portada se alineó también con la inyección de dependencias.

Las ejecuciones focales no se suman como pruebas distintas del gate global. La
matriz de GET administrativo por ambos temas está incluida en Pest; no acredita por
sí sola todas las mutaciones. La instalación real fue exclusivamente local y aislada.

Imagen productiva local: `dbcms:pending-closure-20261012`,
`sha256:cd2d17ac08eb53e83442ab3838e0d633ddaf3b5a3f1e4ad65246589f4954b79b`.
No es una imagen publicada ni un commit nuevo. Los textos de los informes se
completan tras recoger resultados; no forman parte de la huella de código ejecutable.
El estado de dependencias refleja avisos conocidos en el momento del gate.

Evidencia técnica: `storage/app/runtime-verification/pending-closure-20261012/`,
excluida del repositorio y sin credenciales. La revisión conserva los cambios
existentes del working tree. No se crea commit, push o despliegue por documentarlo.


## Entrega y limpieza

Se retiraron las bases, contenedores, red/volúmenes del proyecto CI y copias privadas
de esta ronda. Las claves OAuth de fixtures y el archivo de entorno productivo QA
se eliminaron. Se conservan imágenes locales y logs restringidos para reproducir la
verificación. Los servicios y contenidos del entorno de desarrollo siguen disponibles.

La documentación vigente está enlazada desde README y el índice. Se comprobaron
sus enlaces locales, comandos y listado de documentos, además del whitespace del
diff. La guía distingue reglas para nuevo código, adaptadores heredados y casos
realmente cubiertos por los guards; no atribuye a toda la base una pureza que no se
ha demostrado.
