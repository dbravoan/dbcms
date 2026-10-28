# Auditoría y Calidad: operación editorial

La auditoría interviene cuando `content_audit` está instalado, activo y su opción **Activar flujo de auditoría** está habilitada. Desactivar el flujo deja accesible su configuración para quienes pueden administrar módulos. Desactivar o desinstalar el módulo retira su acceso y sus capacidades; conserva las tablas e historial. Reinstalar no inventa revisiones históricas ni recupera contenido eliminado.

## Autoridad y contenido privado

Los permisos de la entidad y el acceso administrativo siguen siendo necesarios. Los roles sujetos a auditoría necesitan revisión para publicar. Los roles aprobadores y los permisos explícitos de aprobación determinan quién puede revisar; apagar el flujo no convierte a un autor en auditor. Los transportes OAuth añaden scopes a estos permisos. Las sesiones web no reciben scopes inventados.

La cola, su contador de pendientes, los diffs y el historial respetan permisos, propiedad y disponibilidad del módulo propietario. Un autor no obtiene acceso a borradores ajenos por conocer su número ni conoce su cantidad mediante la campana del menú. Los servicios utilizados por web, IA, MCP y jobs comparten las mismas reglas; un job debe conservar un actor autorizado, nunca inferirlo de un identificador proporcionado por el modelo.

## Guardar y revisar

- **Guardar borrador** conserva una versión privada y permite retirarla de la cola.
- **Enviar a revisión** sitúa esa versión en la cola. También funciona al modificar una entidad ya publicada: el visitante sigue viendo la versión vigente.
- Las nuevas instantáneas conservan atributos, traducciones completas, bloques, mercados y relaciones que declare el módulo.
- El comparador de un borrador pendiente usa la versión pública como referencia. La revisión muestra traducciones, datos específicos y bloques. El editor permite inspeccionar la estructura completa.

Cada decisión web se vincula al auditor, entidad y versión mostrada. Los tokens de revisión caducan a los 30 minutos. Aprobar, rechazar o restaurar desde una pantalla anterior devuelve un conflicto si el contenido cambió. Hay que abrir la revisión actual; reenviar el mismo formulario no autoriza una versión nueva. Restaurar añade además la versión histórica elegida a esa vinculación.

## Aprobar, publicar y rechazar

Con **Publicar automáticamente tras aprobación** activo, aprobar aplica y publica la revisión conforme a su intención de visibilidad. Con esta opción desactivada, la aprobación conserva la revisión privada; no sustituye silenciosamente el texto que ya ven los visitantes. La publicación posterior debe solicitarse expresamente y pasar por autorización y confirmación.

Rechazar un borrador pendiente archiva la versión rechazada y conserva la publicación vigente. Rechazar la revisión de contenido público sin borrador puede despublicarlo; por eso `cms_audit_reject`, igual que aprobación y restauración, exige confirmación humana desde MCP/IA. El modelo no puede concederla mediante `confirm:true`.

**Exigir motivo** determina si la interfaz exige una razón. Cuando se proporciona una razón debe ser descriptiva y admite entre 5 y 2.000 caracteres. MCP conserva su contrato anterior de razón obligatoria.

Una restauración es atómica: errores en traducciones, bloques o relaciones cancelan toda la restauración. Anula el puntero pendiente anterior y conserva un nuevo registro de la operación. Los bloqueos de entidad serializan la escritura de versiones; las revisiones pendientes se comparan con el contenido vigente antes de aplicarse.

Una eliminación mediante los servicios comunes archiva una última instantánea privada y marca el expediente como eliminado, evitando entradas pendientes para una entidad desaparecida. El historial no sustituye una copia de seguridad ni ofrece recuperación automática de una entidad borrada.

## Calidad de estilo

El umbral de 0 a 100 y su activación se configuran en la misma pantalla. Se comprueban traducciones y textos de bloques completamente persistidos dentro de la transacción, incluidos los guardados por los servicios comunes y las revisiones de contenido publicado. Un segundo idioma no evita el control por colocarse después del primero.

Esta puntuación es una **heurística de redacción**: cadencia, clichés, mayúsculas y puntuación. No verifica hechos, normativa, accesibilidad, SEO, citas ni autoría humana o por IA. Un editor debe comprobar fuentes, jurisdicción, vigencia y afirmaciones. Los aprobadores conservan la exención configurada de esta comprobación automática de estilo.

**Registrar cambios de administradores y editores** controla el seguimiento automático de guardados directos. Las decisiones editoriales y eliminaciones siguen teniendo su propia trazabilidad cuando el flujo está activo.

## Notificaciones y publicación pública

Los eventos y avisos se emiten después del commit; una transacción cancelada no deja una notificación de una operación inexistente. Correo y campana son opciones independientes. El envío por correo necesita transporte configurado y un trabajador de cola cuando ésta es asíncrona.

Antes de entregar un correo se comprueban de nuevo cuenta, preferencias, permisos, entidad existente, módulo propietario disponible y versión/estado editorial vigentes. Un aviso pendiente de una versión reemplazada, autor reasignado o módulo retirado se omite. Las credenciales SMTP no se incluyen en los mensajes ni en el historial editorial.

Guardar un borrador o aprobarlo sin publicarlo no cambia `dateModified` ni `lastmod` de la representación pública. Una publicación o restauración aplicada invalida cachés e índices después del commit. Las llamadas a proveedores IA quedan fuera de las transacciones editoriales.

## Contrato con el núcleo

`EntityMutationService`, `ContentSectionActions`, navegación y dashboard dependen de `App\Core\Content\Contracts\EditorialWorkflowInterface`, sin importar clases del módulo. ContentAudit registra `EditorialWorkflow` como implementación directa del contrato y utiliza `EditorialVisibilityInterface` para la visibilidad de contadores y revisores. `PendingEditorialDraft` transporta el identificador y la instantánea privada; `EditorialDecision` devuelve estado, versión y referencia pendiente opcional, sin exponer modelos del módulo.

Si el módulo no está registrado, `NoEditorialWorkflow` permite que los servicios comunes creen, actualicen y eliminen entidades sin consultar tablas editoriales. No concede aprobación: preparar una revisión, aprobar, rechazar o restaurar devuelve denegación. Los permisos de la entidad y las confirmaciones de efectos sensibles siguen aplicándose por separado.

La finalización editorial ocurre después de sincronizar traducciones, secciones y relaciones dentro de la transacción común. Los observadores cubren guardados directos compatibles; no sustituyen ese punto de finalización. Los controladores presentan resultados y el módulo conserva la propiedad de sus consultas, decisiones y notificaciones.

## Rendimiento del changelog

El changelog global pagina solo las columnas de metadatos de la revisión: identificador, entidad, usuario, versión, acción, título, notas y fecha. No carga `snapshot` ni `diff` en el listado, porque esos JSON pueden ser grandes y MySQL agota `sort_buffer_size` al ordenarlos. El detalle de una revisión y el comparador cargan la instantánea bajo demanda; el diff de la revisión usa la instantánea anterior completa. La migración `2026_10_28_000001_add_created_at_index_to_content_audit_revisions_table.php` añade el índice de `created_at` que sostiene el orden del feed.

## Verificación del contrato

`ContentAuditReliabilityTest` cubre desactivación física, autoridad con flujo apagado, historial completo, restauración, conflicto en rechazo/restauración, publicación diferida, autor de job, fechas públicas, borrado y avisos obsoletos. Se complementa con pruebas del módulo, botones de borrador, enforcement, paridad editorial, galerías, formularios y propiedad de capacidades.

Las pruebas usan una base y un filesystem temporales independientes. Las pruebas con MCP usan claves OAuth exclusivas de QA. La verificación de los componentes administrativos incluye dbCMS y termoCMS; el correo utiliza transporte simulado y no envía mensajes externos.

`OptionalEditorialWorkflowTest` verifica escritura común con el adaptador por defecto y sin tablas editoriales, y denegación de decisiones de revisión. `EditorialWorkflowBoundaryTest` inspecciona el árbol sintáctico para impedir dependencias del módulo en los cinco consumidores del núcleo. El contrato de aprobación conserva el tipo de entidad, separado de su identificador numérico. La regresión de navegación distingue contador propio, autoridad del aprobador y ausencia de permisos o módulos.
