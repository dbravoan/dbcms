# 25 · Conectar una IA al CMS (guía de conexión e interacción)

Configura un cliente compatible con MCP Streamable HTTP para usar
`https://tu-cms.example/mcp/cms`, con `Authorization: Bearer <token>`.
El SDK `laravel/mcp` negocia `initialize` y ofrece el catálogo de capacidades
permitidas para esa identidad. El cliente debe aceptar JSON y eventos SSE del
transporte estándar, conservar la sesión si se negocia y usar la versión acordada.

## Identidad y alcance

| Tarea | Scopes mínimos | Permisos CMS |
| --- | --- | --- |
| Consultar contenido | `mcp:access,mcp:read` | Lectura del recurso correspondiente |
| Crear y editar borradores | `mcp:access,mcp:read,mcp:write` | Gestión de la entidad |
| Proponer borrados | Añadir `mcp:destructive` | Gestión de la entidad y revisión humana |
| Servicio M2M | Añadir `m2m:service` | Cuenta CMS y rol vinculados explícitamente |

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan cms:issue-mcp-token --user=1 \
  --name="Asistente editorial" --scopes=mcp:access,mcp:read,mcp:write
```

También puedes emitirlo desde **Administración → Servidor MCP & Agentes**.
Guárdalo en el gestor de secretos del cliente. El rol del usuario y los scopes
se comprueban conjuntamente; dar un scope no amplía sus permisos. Un cliente
M2M sin cuenta CMS asociada no puede actuar. Los detalles están en la
[especificación OAuth](07_PASSPORT_OAUTH2_SPECIFICATION.md).

## Configuración del cliente

En el apartado de servidores MCP remotos, indica la URL y la cabecera Bearer.
La sintaxis del archivo de configuración depende del cliente; evita introducir
el token en un archivo versionado. Un cliente que solo admite procesos locales
necesita un adaptador compatible con el transporte HTTP estándar.

Para comprobar la conexión independientemente del cliente, sigue los comandos
`initialize`, `tools/list` y `tools/call` de la
[prueba de conexión](24_MCP_REAL_CONNECTION_WALKTHROUGH.md). No uses un listado
histórico de herramientas: sus esquemas se descubren en cada instalación.

## Instrucciones para el agente

Una instrucción inicial útil es:

```text
Antes de editar, consulta las herramientas disponibles y el esquema de la entidad.
Confirma el recurso, su identificador, mercado e idioma a partir de lecturas.
Conserva los campos que no he pedido cambiar. Redacta como borrador y presenta
la propuesta de publicación. Si la operación requiere revisión humana, entrega
el enlace de la ejecución y espera su aprobación en el panel.
```

Describe el resultado y su ámbito: «actualiza el resumen de esta página en español
para España» es más preciso que «arregla el contenido». Para una traducción,
identifica el idioma de origen y de destino y los campos que deben conservarse.
Para una investigación pública o guía editorial, utiliza las capacidades que
aporte el módulo instalado y consulta el esquema antes de llamarlas.

## Capacidades y límites

El núcleo registra páginas, recursos administrativos y operaciones compartidas.
Blog, Shop, Gallery, Forms, Search, ContentAudit y AiAssistant aportan sus
capacidades mediante sus propietarios. Un módulo ausente o desactivado no debe
aparecer como disponible ni poder ejecutarse mediante un nombre conocido.

Las herramientas de entidades permiten descubrir tipos, listar, leer y mutar
contenido. Los recursos administrativos y las acciones especializadas tienen
sus propios esquemas y permisos. Consulta `cms_capabilities` cuando esté
disponible y la [paridad de capacidades](23_MCP_BACKOFFICE_PARITY.md).

Las acciones sensibles pasan por una ejecución revisable desde el backoffice.
`confirm:true` no concede autoridad y el agente no puede aprobarse a sí mismo.
La revisión editorial y la aprobación de una ejecución son controles distintos.
Una respuesta HTTP `200` puede contener un rechazo de la herramienta: inspecciona
el resultado completo antes de afirmar que se ha guardado o publicado algo.

## Seguimiento

`cms_mcp_audit` permite consultar la actividad a quien tenga el permiso requerido;
la tabla canónica es `capability_audit_logs`. Usa el panel de tokens para revocar
accesos que ya no sean necesarios. Nunca publiques tokens, claves o registros
con datos privados en un informe de conexión.
