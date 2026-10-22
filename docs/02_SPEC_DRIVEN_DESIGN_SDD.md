# Especificar cambios antes de implementarlos

Leer [Core and extensions](../specs/CORE_AND_EXTENSIONS.md) y la especificación
propietaria antes de modificar comportamiento. Una especificación reduce decisiones
pendientes: objetivo, comportamiento observable, restricciones, contratos afectados
y criterios de aceptación. Usar la [plantilla](../specs/templates/FEATURE_SPEC_TEMPLATE.md)
con el detalle proporcional al cambio.

No generar DTOs, interfaces, endpoints o eventos por completar una plantilla.
Declararlos solo si el comportamiento los necesita y ubicarlos en el núcleo,
módulo o paquete que los posee. Para un refactor indicar qué se elimina y qué
invariantes permanecen. Para un cambio de datos describir transacción, publicación,
autorización y recuperación cuando sean pertinentes.

Implementar y verificar el recorrido real con el fixture más pequeño útil.
Una regresión de un defecto debe fallar antes de corregirlo cuando sea viable.
[TESTING](TESTING.md) define pruebas rápidas, integración aislada y release;
pasar los casos cubiertos no demuestra corrección para todos los estados posibles.

Las especificaciones Markdown son contratos para revisión humana. El esquema
`specs/schemas/feature_spec.schema.json` sirve para especificaciones JSON; no
valida directamente Markdown. Una especificación sustituida debe enlazar su
reemplazo; conservar las evidencias fechadas sin presentarlas como reglas actuales.
