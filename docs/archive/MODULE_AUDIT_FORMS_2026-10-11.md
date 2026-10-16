# Paridad Forms web/MCP — 2026-10-11

> Historical evidence. This records the behavior and checks of its stated date; current contracts live in [Core and extensions](../../specs/CORE_AND_EXTENSIONS.md) and [Testing](../TESTING.md).

La auditoría reprodujo dos defectos de escritura nativa: `attributes.fields` se ignoraba y el escritor genérico intentaba insertar una columna `slug` inexistente en `custom_form_translations`. Se mantuvo el esquema de datos existente y se centralizó el guardado en `FormMutationHandler`, reutilizando `FormSchemaService`.

Web y MCP ahora comparten validación, normalización de claves, persistencia de etiquetas/opciones, asignación de mercados y transacciones. Las claves normalizadas duplicadas y los tipos no registrados se rechazan; se respeta el máximo de 50 caracteres de la columna. El editor HTTP usa FormRequest y un adaptador del escritor común. Crear por MCP conserva el valor seguro de borrador; la activación necesita aprobación humana. El formulario HTTP conserva su casilla de activación.

`cms_entity_types` describe `attributes.fields`; las lecturas devuelven `relations.fields`. Cada campo incluye `field_key`, `type`, `is_required`, `validation_rules` y traducciones por idioma (`label`, `placeholder`, `options`). Las opciones de entrada conservan el formato de texto del editor, una opción por línea. Omitir `fields` en una actualización conserva la estructura; una lista vacía la elimina. Las actualizaciones parciales de traducciones conservan los atributos e idiomas omitidos.

Para un formulario activo, las modificaciones quedan en revisión: la definición pública sigue intacta. El contrato común de revisiones captura campos, IDs, orden y traducciones; permite hidratar el editor pendiente y restaurar la definición aprobada. La pantalla de edición enlaza a `/control/audit/forms/{id}/review`. El lector común tiene una extensión declarada para relaciones de módulos, sin conocer modelos de Forms.

## Validación e inventario

`FormMutationParityTest`: RED 5 → GREEN 8 pruebas / 22 aserciones, Foundation TestCase con SQLite `:memory:` y migración de Forms dentro de esa conexión. Comprueba creación nativa, lector de campos, parches, claves/tipos inválidos, adapter web, captura/hidratación/restauración y preservación de definición activa. No toca MySQL de desarrollo ni la base compartida de pruebas. Se ajustó la expectativa del test funcional existente `FormBuilderAndManagerTest` para verificar live/pending/approve; su ejecución completa corresponde al gate del orquestador. Pint y PHPStan del bloque, verdes.

Formulario QA **2**, slug `qa-20261011-mcp-form`, creado por MCP real con dos campos (`qa_email`, `qa_message`), ambos localizados ES/EN y asignados al mercado ES 1. Un parche parcial EN conserva los dos campos y los textos de éxito. El formulario previo **1**, creado por el orquestador en UI, no se modificó en esta intervención.

La publicación QA 2 terminó mediante revisión humana en `/control/executions/02a23dee-e6f6-4441-a660-6600b252bdd1`: ejecución `completed`, formulario activo y dos campos ES/EN. El worker anterior había preparado una huella sin el nuevo contrato de campos. Reiniciar la cola no reemplaza una revisión ya almacenada: se añadió el botón **Actualizar revisión**, que solicita `resume` y una nueva aprobación humana, sin aprobar automáticamente. Tres capturas consecutivas de la huella actual fueron iguales; la huella antigua difería. Regresión de render de ese CTA: 1 prueba / 2 aserciones, verde.

El envío HTTP real del formulario QA 2 devolvió 200 y creó el envío **2**. Omitir el email requerido devuelve 422, el honeypot devuelve 422 y solicitar el formulario desde UK devuelve 404. Los clientes/tokens propios de Forms quedaron revocados al concluir. El envío previo **1** no se modificó.

No se borraron fixtures, envíos anteriores ni formularios. No hay migraciones nuevas ni modificación de credenciales persistentes. Los snapshots históricos que carezcan de relaciones de módulo conservan la estructura almacenada, sin inventar una definición anterior de campos.
