# 25 · Conectar una IA al CMS (guía de conexión e interacción)

> **Qué es**: cómo conectar cualquier IA/agente al CMS por MCP, cómo escribirle y
> qué hará en cada caso.
> **Relacionado**: [`23_MCP_BACKOFFICE_PARITY.md`](23_MCP_BACKOFFICE_PARITY.md) (qué puede hacer) · [`24_MCP_REAL_CONNECTION_WALKTHROUGH.md`](24_MCP_REAL_CONNECTION_WALKTHROUGH.md) (prueba real paso a paso)

---

## 1. Los transportes (elige uno)

| | **Nativo (Streamable HTTP)** | **JSON-RPC del CMS** | **SSE del CMS** |
| :--- | :--- | :--- | :--- |
| URL | `POST /mcp/cms` | `POST /api/mcp` | `GET /api/mcp/sse` + `POST /api/mcp/message?sessionId=…` |
| Protocolo | MCP estándar (`initialize`, `server/discover`) | JSON-RPC con `tools/list` y `tools/call` | JSON-RPC sobre sesión |
| Clientes | Claude Desktop, Cursor, VS Code, Inspector, SDK oficial | curl, Postman, SDK propio, integraciones a medida | Clientes que exigen transporte SSE |
| Autenticación | `Authorization: Bearer <token>` | igual | cabecera Bearer (no token en URL) |
| Cuándo usarlo | **Recomendado** para cualquier IA | Scripts, automatizaciones, pruebas | Clientes que solo entienden SSE |

Los tres proyectan el catálogo común, filtrado por identidad, scopes, RBAC y
módulos activos. Web, API, MCP y jobs comparten ExecutorCore (`CapabilityExecutor`).

---

## 2. Antes de conectar: dos decisiones

### 2.1 Qué token (lo que la IA podrá hacer)

| Escenario | Alcances | Resultado |
| :--- | :--- | :--- |
| IA que redacta y propone publicación | `mcp:access,mcp:read,mcp:write` | borradores; publicación mediante revisión humana en el panel |
| IA que solo redacta/lee | `mcp:access,mcp:read,mcp:write` | puede crear y editar, **no borrar** |
| IA de consulta/análisis | `mcp:access,mcp:read` | solo lectura |
| Servicio automático | scopes mínimos + `m2m:service`, `client_credentials` | requiere cuenta CMS vinculada y rol explícito; scopes y RBAC se comprueban juntos |

```bash
# Token personal (para tu IA de escritorio)
./bin/sail-run php artisan cms:issue-mcp-token \
  --user=1 --name="Asistente editorial" \
  --scopes=mcp:access,mcp:read,mcp:write,mcp:destructive

# Cliente máquina-a-máquina (para un servicio)
./bin/sail-run php artisan cms:create-m2m-client --name="Automatizacion" --role=editor \
  --scopes=mcp:access,mcp:read,mcp:write,mcp:destructive,m2m:service
```

Desde el panel: **Administración → Servidor MCP & Agentes → 1-Clic: Emitir Token**.

> Guarda el token y el secreto del cliente en un gestor de secretos. No los
> incluyas en el repositorio ni en URLs. `--role=editor` requiere que ese rol
> exista y permita los scopes pedidos; el comando no amplía sus permisos.

### 2.2 Qué usuario respalda el token

Además del alcance, el agente hereda **los permisos de la persona** que emitió el
token (`manage:posts`, `manage:shop`, `manage:modules`…). Regla práctica:

| Si la IA debe… | Emite el token con un usuario… |
| :--- | :--- |
| escribir blog y páginas | Editor o Administrador |
| gestionar tienda y pedidos | usuario con `manage:shop` |
| tocar módulos, temas, ajustes o mantenimiento | Administrador |
| solo analizar y proponer | un usuario de lectura |

Las cuentas M2M también tienen usuario y rol: el cliente queda asociado en
`oauth_service_accounts` con sus scopes permitidos. Un cliente sin asociación
es rechazado; conceder scopes nunca sustituye un permiso CMS.

---

## 3. Configurar cada cliente

### 3.1 Claude Desktop

`~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) o
`%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{
  "mcpServers": {
    "dbCMS": {
      "command": "npx",
      "args": [
        "-y", "mcp-remote",
        "https://TU-DOMINIO/mcp/cms",
        "--header", "Authorization: Bearer ${DBCMS_TOKEN}"
      ],
      "env": { "DBCMS_TOKEN": "eyJ0eXAiOiJKV1Qi…" }
    }
  }
}
```

Reinicia Claude Desktop: las herramientas aparecen en el icono de conectores.

### 3.2 Cursor

`.cursor/mcp.json` (proyecto) o `~/.cursor/mcp.json` (global):

```json
{
  "mcpServers": {
    "dbCMS": {
      "url": "https://TU-DOMINIO/mcp/cms",
      "headers": { "Authorization": "Bearer eyJ0eXAiOiJKV1Qi…" }
    }
  }
}
```

### 3.3 VS Code (Copilot / extensión MCP)

`.vscode/mcp.json`:

```json
{
  "servers": {
    "dbCMS": {
      "type": "http",
      "url": "https://TU-DOMINIO/mcp/cms",
      "headers": { "Authorization": "Bearer eyJ0eXAiOiJKV1Qi…" }
    }
  }
}
```

### 3.4 OpenCode

`opencode.json` en la raíz del proyecto (o global):

```json
{
  "$schema": "https://opencode.ai/schema.json",
  "mcp": {
    "dbCMS": {
      "type": "remote",
      "url": "https://TU-DOMINIO/mcp/cms",
      "headers": { "Authorization": "Bearer eyJ0eXAiOiJKV1Qi…" }
    }
  }
}
```

### 3.5 Inspector oficial (el depurador de MCP)

```bash
npx @modelcontextprotocol/inspector --config /ruta/mcp-inspector.json
```

con

```json
{ "mcpServers": { "dbCMS": { "type": "http", "url": "http://localhost:8080/mcp/cms",
  "headers": { "Authorization": "Bearer eyJ0eXAiOiJKV1Qi…" } } } }
```

Sirve para ver el catálogo, probar una llamada y leer el error exacto.

### 3.6 Sin cliente: curl (el más útil para depurar)

```bash
TOKEN=eyJ0eXAiOiJKV1Qi…

# 1) handshake
curl -s -X POST http://localhost:8080/mcp/cms \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize",
       "params":{"protocolVersion":"2025-06-18","capabilities":{},
                 "clientInfo":{"name":"curl","version":"1"}}}'

# 2) catálogo
curl -s -X POST http://localhost:8080/mcp/cms -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/list","params":{}}'

# 3) llamada
curl -s -X POST http://localhost:8080/mcp/cms -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":3,"method":"tools/call",
       "params":{"name":"cms_entity_list","arguments":{"type":"post","per_page":3}}}'
```

> **Truco**: `POST /mcp/cms?limit=8` publica solo 8 herramientas. Útil con IAs
> que se saturan con listas largas (Claude Desktop, por ejemplo). `cms_capabilities`
> sigue estando disponible para que el agente descubra el resto.

Para el transporte JSON-RPC del CMS, cambia la URL a `/api/mcp` y omite
`initialize` (no lo necesita).

---

## 4. Cómo escribirle a la IA

No hay sintaxis especial: se le habla normal y ella elige la herramienta. Lo que
marca la diferencia es **dar contexto** y **pedir el resultado concreto**.

### 4.1 La primera instrucción (una sola vez)

Pega esto como prompt de sistema o primera frase:

```text
Estás conectado al CMS por MCP. Antes de actuar:
1. Llama a cms_capabilities para ver qué puedes hacer y con qué permisos.
2. Llama a cms_entity_types y cms_resource_types para conocer los campos exactos.
3. Si vas a crear o editar, confirma conmigo el tipo, el idioma y si publica.
Trabaja siempre en español salvo que te pida otro idioma, y dime al final
qué has creado o cambiado con su URL pública.
```

### 4.2 Pedir de forma explícita (mejores resultados)

| En vez de… | Escribe… |
| :--- | :--- |
| «escribe un artículo» | «crea un **artículo de blog** en **es y en en**, **publicado**, con categoría *Tecnología*, resumen de 2 líneas y un enlace interno a los artículos de GEO» |
| «actualiza el producto» | «cambia el **precio** del producto *Servidor edge* a 1.290 € y actualiza su resumen en español, **sin tocar** el inglés» |
| «ordena las categorías» | «lista las **categorías del blog** con `cms_entity_list` y dime cuáles están vacías» |
| «mira los pedidos» | «lista los **pedidos** con estado *pending* de los últimos 7 días y su importe» |

### 4.3 Flujos que funcionan muy bien

**Redactar y publicar un artículo**

> «Investiga con `cms_search` qué tenemos sobre arquitectura modular, escribe un
> artículo nuevo que enlace a esos contenidos, publícalo en es/en y dime la URL y
> una frase de meta descripción.»

La IA puede buscar, preparar el borrador y solicitar publicación. Presenta el
`review_url` de la ejecución al humano; informa la URL pública solo después de
que la aprobación y ejecución terminen correctamente.

**Auditar y arreglar**

> «Lista los artículos con meta descripción vacía (`cms_entity_list`) y
> rellénalos con una de 150 caracteres a partir de su contenido.»

**Curación SEO**

> «Mira `cms_search_stats`, elige las 3 búsquedas con más volumen y crea landings
> indexables con `cms_seo_filter_upsert`.»

**Operación**

> «Desactiva el módulo de galerías, purga la caché y confírmame que la web sigue
> en 200. Luego vuelve a activarlo.»

Para efectos sensibles, la IA presenta `execution_id` y `review_url`. La
aprobación del cambio concreto ocurre en una sesión humana del backoffice,
con permisos actuales; ningún booleano enviado por la IA concede autoridad.

**Revisión editorial**

> «Lista los artículos pendientes de revisión, audita el SEO y prepara para mí
> una propuesta concreta de publicación con su enlace de revisión.»

---

## 5. Qué hará la IA en cada caso (mapa de intenciones)

| Le pides… | Herramienta | Qué pasa exactamente |
| :--- | :--- | :--- |
| «¿qué puedes hacer?» | `cms_capabilities` | catálogo con permiso y alcance de cada capacidad |
| «¿qué campos tiene un producto?» | `cms_entity_types` | atributos escribibles + campos traducibles |
| «muéstrame los artículos / productos / páginas» | `cms_entity_list` | listado paginado con búsqueda y filtros de estado/idioma |
| «trae ese artículo» | `cms_entity_get` | todas sus traducciones, estado y URL canónica |
| «escribe/crea X» | `cms_entity_create` | crea con traducciones; **el agente queda como autor** |
| «cambia el título/precio/estado» | `cms_entity_update` | parchea solo lo indicado; las otras traducciones intactas |
| «borra X» | `cms_entity_delete` | propone borrado; devuelve ejecución y enlace para aprobación humana |
| «crea una categoría / menú / idioma / usuario» | `cms_resource_create` | crea la fila validando como el formulario del panel |
| «cambia el estado de un pedido» | `cms_resource_update` | parchea la fila (status, notas, revisado…) |
| «lista mercados / pedidos / envíos de formularios» | `cms_resource_list` | listado con búsqueda y filtros |
| «rediseña la landing» | `cms_content_sections_get` + `_sync` | lee y reemplaza los bloques de la página/post/producto |
| «sube esta imagen» | `cms_media_upload` | valida tipo y tamaño y la añade a la biblioteca |
| «activa/desactiva un módulo» | `cms_module_toggle` | disponibilidad y aprobación según el efecto de la acción |
| «cambia el tema» | `cms_theme_activate` | activa el tema del front |
| «cambia el nombre del sitio» | `cms_settings_update` | ajustes de sitio |
| «pon la web en mantenimiento» | `cms_maintenance_toggle` | requiere aprobación humana del cambio concreto |
| «no veo los cambios» | `cms_cache_purge` | solicita revisión humana antes de la purga |
| «reindexa la búsqueda» | `cms_search_reindex` | reconstruye el índice |
| «¿qué busca la gente?» | `cms_search_stats` | cobertura por entidad y términos top / sin resultados |
| «busca en el contenido» | `cms_search` | búsqueda general indexada con URL canónica |
| «aplica filtros y dame el enlace» | `cms_resolve_filter_url`, `cms_browse_content` | URL canónica, política de indexación y resultados |
| «revisa el tono anti-IA» | `cms_validate_editorial_tone`, `cms_humanize_prose` | auditoría y humanización |
| «aprueba / rechaza / revierte una revisión» | `cms_audit_approve` / `_reject` / `_rollback` | propuesta de revisión editorial; efectos sensibles pasan por aprobación humana |
| «cura una landing de filtro» | `cms_seo_filter_upsert` | título, descripción e indexabilidad |
| «¿qué ha hecho el agente?» | `cms_mcp_audit` | historial con usuario, capacidad, estado y duración |

El catálogo completo vive en `docs/23`; aquí tienes el mapa de uso diario.

---

## 6. Qué responderá en los casos límite

| Situación | Respuesta del CMS | Qué decirle a la IA |
| :--- | :--- | :--- |
| Falta alcance | `Token lacks the [mcp:write] scope required to write.` | emite un token con ese alcance |
| Falta permiso | `The user behind the token lacks the [manage:shop] permission.` | usa un usuario con ese permiso |
| Operación destructiva | `confirmation_required: true` | presenta `review_url`; el humano aprueba en el backoffice, después consulta `execution_id` |
| Falta un dato obligatorio | `validation: {campo: [mensaje]}` | corrige y reintenta |
| Tipo/entidad desconocida | lista de valores válidos | elige de la lista |
| Token caducado/revocado | `-32001 Unauthorized` | emite otro token |
| Módulo MCP apagado | `-32000 MCP Server module is currently disabled` | actívalo en el panel |
| Cuenta de servicio no vinculada | identidad M2M denegada | provisiona cliente, cuenta y rol explícito con `cms:create-m2m-client --role=…` |

Todo lo anterior son respuestas **estructuradas**: la IA puede leerlas y
reaccionar sin que se rompa nada.

---

## 7. Recomendaciones para trabajar bien

1. **Un token por IA/uso**, con el nombre del agente: la auditoría te dirá quién
   hizo qué, y puedes revocar solo ese.
2. **Menor privilegio**: si la IA no debe borrar, no le des `mcp:destructive`.
3. **Revisión humana en el panel**: abre `review_url`, inspecciona argumentos y
   cambios y aprueba la ejecución concreta. No hay aprobación por API o MCP.
4. **Pide el resultado verificable**: «dame la URL pública y el id».
5. **Revisa `cms_mcp_audit`** de vez en cuando: hay un historial con argumentos
   (secretos enmascarados) y duración.
6. **En producción**: HTTPS, tokens de usuario con el rol mínimo y rotación
   periódica (`cms_token_revoke` o el panel de tokens).

---

## 8. Diagnóstico rápido

```bash
# ¿Qué manifiesto está disponible para esta identidad?
curl -s http://localhost:8080/api/mcp/tools -H "Authorization: Bearer $TOKEN" | head -c 120

# ¿Mi token vale?
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/mcp/cms \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
# 200 ok · 401 token inválido/revocado · 403 falta alcance

# ¿Qué ha pasado?
curl -s -X POST http://localhost:8080/api/mcp -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/call","params":{"name":"cms_mcp_audit","arguments":{"limit":10,"problems_only":true}}}'
```

| Síntoma | Causa |
| :--- | :--- |
| El cliente no muestra herramientas | no completó `initialize` (usa `/mcp/cms`) o el token no es válido |
| `Method not found: initialize` | estás en `/api/mcp` (transporte del CMS, no nativo) |
| `403` en todo | falta `mcp:access` o el permiso del usuario |
| La IA dice que no puede borrar | su token no tiene `mcp:destructive` |
| Los cambios no se ven en la web | `cms_cache_purge` (con revisión humana) |
