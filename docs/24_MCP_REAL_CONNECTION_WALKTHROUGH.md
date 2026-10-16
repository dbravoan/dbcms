# 24 · MCP: prueba de conexión real (paso a paso)

Esta guía verifica el endpoint `POST /mcp/cms` del SDK `laravel/mcp`.
Usa una instalación aislada, el módulo MCP instalado y activo y una cuenta con
los permisos del contenido que se va a consultar. Los ejemplos son de lectura.
El catálogo depende de esa identidad, sus scopes y los módulos disponibles.

## Preparar la identidad

Arranca Sail antes de ejecutar comandos locales. Emite un token de alcance mínimo:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan cms:issue-mcp-token --user=1 \
  --name="Prueba MCP" --scopes=mcp:access,mcp:read
read -rsp 'Bearer token: ' TOKEN; echo
BASE=http://localhost:8080
```

El identificador debe corresponder a una cuenta autorizada. El panel
**Administración → Servidor MCP & Agentes** también permite emitir el token.
No incluyas el token en URLs, archivos del proyecto o evidencias. Un servicio M2M
necesita cliente OAuth vinculado a una cuenta CMS, rol explícito y scopes
permitidos por ese rol; consulta la
[especificación OAuth](07_PASSPORT_OAUTH2_SPECIFICATION.md).

## Negociar MCP y descubrir herramientas

```bash
curl -sS -D - -X POST "$BASE/mcp/cms" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"verification","version":"1"}}}'
```

Comprueba `result.protocolVersion`, `result.serverInfo` y las capacidades
anunciadas. Utiliza la versión negociada en las siguientes peticiones; si el
servidor devuelve un identificador de sesión, consérvalo según el protocolo.
Un cliente MCP gestiona esta negociación y las notificaciones del ciclo de vida.

```bash
curl -sS -X POST "$BASE/mcp/cms" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'
```

Inspecciona el esquema de cada herramienta disponible. No compares el resultado
con un número fijo: un módulo ausente o desactivado no aporta capacidades.
`cms_capabilities` permite descubrir las capacidades del registro común cuando
está disponible para la identidad.

## Ejecutar una lectura

El núcleo aporta páginas; `page` no necesita un módulo de negocio:

```bash
curl -sS -X POST "$BASE/mcp/cms" \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call","params":{"name":"cms_entity_list","arguments":{"type":"page","per_page":3}}}'
```

Verifica el resultado del protocolo y el resultado de la herramienta: una
respuesta HTTP correcta no demuestra que la operación haya sido autorizada.
Las herramientas comprueban permisos, scopes, disponibilidad y límites de
mercado/idioma también al ejecutarse.

## Validar permisos y revisión

Prueba con una identidad de lectura y comprueba que una escritura no se permite.
Una cuenta administrativa sin el scope requerido tampoco debe escribir.
Las operaciones sensibles devuelven una ejecución para revisión humana; el
booleano `confirm` no aprueba nada. La aprobación se realiza desde el backoffice,
con una nueva comprobación de la identidad y de los datos de la ejecución.

La auditoría se consulta con `cms_mcp_audit` si la identidad está autorizada;
los registros residen en `capability_audit_logs`. Comprueba que los argumentos
privados se enmascaran. Finaliza con `unset TOKEN` y revoca el token de prueba.

## Diagnóstico

| Resultado | Comprobación |
| --- | --- |
| `401` | Token válido, vigente y enviado como Bearer |
| `403` | Scope `mcp:access`, scope de la operación y permisos de la cuenta |
| Herramienta ausente | Módulo instalado/activo, entidad registrada y catálogo de esa identidad |
| Error de protocolo | Endpoint `/mcp/cms`, handshake, versión negociada y cabecera Accept |
| Ejecución pendiente | Abrir la revisión humana correspondiente en el backoffice |

La [guía de agentes](25_CONNECTING_AI_AGENTS_OVER_MCP.md) describe cómo preparar
el cliente y formular tareas. La [paridad de capacidades](23_MCP_BACKOFFICE_PARITY.md)
documenta la relación entre transportes y servicios de dominio.
