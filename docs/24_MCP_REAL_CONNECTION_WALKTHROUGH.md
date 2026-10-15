# 24 · MCP: prueba de conexión real (paso a paso)

> **Qué es**: una guía reproducible contra el servidor MCP del CMS. Las salidas
> abreviadas ilustran el contrato; IDs, URLs y catálogo dependen de la instalación.
> **Cuándo usarlo**: para conectar un agente por primera vez, para depurar una
> integración o para comprobar que el CMS expone todo lo acordado.
> **Relacionado**: [`23_MCP_BACKOFFICE_PARITY.md`](23_MCP_BACKOFFICE_PARITY.md) (qué puede hacer un agente) · [`07_PASSPORT_OAUTH2_SPECIFICATION.md`](07_PASSPORT_OAUTH2_SPECIFICATION.md)

Entorno de ejemplo: Sail en `http://localhost:8080`, base de datos aislada con
contenido de demo y un usuario autorizado. No ejecutes ejemplos de escritura
sobre producción. Web, API, MCP y jobs comparten ExecutorCore (`CapabilityExecutor`).

---

## Paso 0 · Comprobar que el módulo MCP está activo

```bash
./vendor/bin/sail artisan tinker --execute="
echo app(\App\Core\Modules\ModuleManager::class)->isModuleEnabled('mcp') ? 'mcp: ON' : 'mcp: OFF';
"
```

Si está apagado, actívalo desde el panel (**Módulos**) o por consola:

```bash
./vendor/bin/sail artisan tinker --execute="
app(\App\Core\Modules\ModuleManager::class)->activate('mcp');
"
```

El manifiesto requiere token y se filtra por permisos, scopes y módulos.
Después de emitirlo en el paso 1, compruébalo:

```bash
curl -s http://localhost:8080/api/mcp/tools -H "Authorization: Bearer $TOKEN" | head -c 200
```

```json
{"tools":[{"name":"cms_inspect_system", ...}],
 "transport":{"http":"http://localhost:8080/api/mcp","sse":"http://localhost:8080/api/mcp/sse"},
 "authentication":{"type":"Bearer (Laravel Passport OAuth2)",
   "token_endpoint":"http://localhost:8080/oauth/token",
   "required_scopes":["mcp:access","mcp:read","mcp:write","mcp:destructive"]}}
```

---

## Paso 1 · Emitir un token

```bash
./vendor/bin/sail artisan cms:issue-mcp-token --user=1 --name="MCP Docu Test"
```

```
================================================================
  PERSONAL ACCESS TOKEN GENERADO CON ÉXITO (LARAVEL PASSPORT)
================================================================
Nombre:  MCP Docu Test
Usuario: David Bravo (Admin) (admin@db-cms.io) [ID: 1]
Scopes:  mcp:access, mcp:read, mcp:write, mcp:destructive

Bearer Access Token (Copia este token):
eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9…
```

> **Guarda el token ahora** en un gestor de secretos. Si lo pierdes,
> emite otro (y revoca el anterior).
>
> Alternativa sin consola: **Administración → Servidor MCP & Agentes → Generar
> token** (un clic, con los mismos alcances).

Para la sesión de terminal, evita guardarlo en un archivo dentro del proyecto:

```bash
read -rsp 'Bearer token: ' TOKEN; echo
```

Al terminar, ejecuta `unset TOKEN` y revoca el token de la prueba.

---

## Paso 2 · Elegir transporte

Hay dos transportes con el mismo catálogo y la misma seguridad:

| Transporte | URL | Protocolo |
| :--- | :--- | :--- |
| **Nativo (recomendado)** | `POST /mcp/cms` | MCP estándar: `initialize`, `tools/list`, `tools/call` |
| JSON-RPC del CMS | `POST /api/mcp` | `tools/list` y `tools/call` (sin `initialize`) |
| SSE del CMS | `GET /api/mcp/sse` → `POST /api/mcp/message?sessionId=…` | JSON-RPC sobre sesión |

Los clientes que exigen el handshake estándar (Claude Desktop, Cursor, VS Code,
Inspector, SDK oficial) usan **`/mcp/cms`**; curl, Postman y automatizaciones
pueden usar cualquiera de los dos.

```bash
# Handshake nativo
curl -s -X POST http://localhost:8080/mcp/cms \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -H 'Accept: application/json, text/event-stream' \
  -H 'MCP-Protocol-Version: 2025-06-18' \
  -d '{"jsonrpc":"2.0","id":1,"method":"initialize",
       "params":{"protocolVersion":"2025-06-18","capabilities":{},
                 "clientInfo":{"name":"prueba-real","version":"1"}}}'
```
```json
{"jsonrpc":"2.0","id":1,"result":{"protocolVersion":"2025-06-18",
 "capabilities":{"tools":{"listChanged":false}, …},
 "serverInfo":{"name":"dbCMS","version":"1.0.0"}, "instructions":"…"}}
```

---

## Paso 3 · Listar las capacidades

*(Los ejemplos usan el transporte JSON-RPC del CMS; en `/mcp/cms` añade la
cabecera `MCP-Protocol-Version` y envía antes el `initialize` del paso 2.)*

```bash
curl -s -X POST http://localhost:8080/api/mcp \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":1,"method":"tools/list"}'
```

La respuesta contiene `result.tools`. Su cantidad y esquemas cambian según los
módulos instalados/activos, los permisos del usuario o cuenta de servicio y los
scopes. No compares con un número fijo de herramientas.

Atajo: `cms_capabilities` devuelve el catálogo con **permiso y alcance** de cada
capacidad, más las entidades, los recursos y las estadísticas de auditoría:

```bash
curl -s -X POST http://localhost:8080/api/mcp -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":2,"method":"tools/call","params":{"name":"cms_capabilities","arguments":{}}}'
```

Consulta el catálogo vivo; una herramienta anunciada sigue comprobando permisos
y disponibilidad al ejecutarse. Los scopes no sustituyen el RBAC de la cuenta.
Publicar o borrar devuelve una ejecución pendiente de revisión humana.

---

## Paso 4 · Inspeccionar el sistema y el contenido

```json
// cms_inspect_system
{"status":"operational","cms_version":"1.2.0","modules":[{"identifier":"pages",…}]}

// cms_entity_types  (qué puede escribir el agente, campo a campo)
{"types":[{"type":"post","label":"Artículo de blog","permission":"manage:posts",
  "sectionable":true,"searchable":true,"translatable":true,
  "roles":["title","slug","summary","meta_title","meta_description"],
  "attributes":["user_id","category_id","featured_image","reading_time_minutes",
                "is_featured","is_published","allow_comments","published_at"],
  "translation_attributes":["title","slug","summary","meta_title","meta_description"]}, …]}

// cms_resource_types  (recursos de plataforma y operaciones permitidas)
{"resources":[{"resource":"audit","permission":"access:admin","operations":["list","get"]}, …]}
```

---

## Paso 5 · Crear contenido como un agente

```bash
curl -s -X POST http://localhost:8080/api/mcp -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":10,"method":"tools/call","params":{"name":"cms_entity_create","arguments":{
        "type":"post",
        "attributes":{"publish":false,"is_featured":true},
        "translations":{
          "es":{"title":"Conexion MCP de punta a punta","summary":"Prueba real contra el servidor MCP del CMS.","content":"<p>Contenido de la prueba real.</p>"},
          "en":{"title":"End-to-end MCP connection","summary":"Real test against the CMS MCP server."}}}}}'
```

```json
{"id":5,"type":"post","label":"Artículo de blog","status":"draft",
 "attributes":{"user_id":1,"is_featured":true,"is_published":false,
               "published_at":null},
 "translations":{
   "es":{"title":"Conexion MCP de punta a punta","slug":"conexion-mcp-de-punta-a-punta","…"},
   "en":{"title":"End-to-end MCP connection","slug":"end-to-end-mcp-connection","…"}},
 "url":"http://localhost:8080/eu/es/blog/conexion-mcp-de-punta-a-punta",
 "urls":{"es":"http://localhost:8080/es/es/blog/conexion-mcp-de-punta-a-punta","en":"…/en/blog/…"}}
```

El agente es el autor (`user_id`), los slugs se generan y desduplican por idioma,
y el artículo queda como borrador. Su URL pública no se sirve hasta aprobar
una petición explícita de publicación en el backoffice:

```bash
curl -o /dev/null -w "%{http_code}\n" http://localhost:8080/es/es/blog/conexion-mcp-de-punta-a-punta
# 404 (borrador)
```

---

## Paso 6 · Actualizar sin pisar el resto

```bash
-d '{"…":"tools/call","params":{"name":"cms_entity_update","arguments":{
       "type":"post","id":5,
       "attributes":{"publish":false},
       "translations":{"es":{"title":"Conexion MCP (actualizada por el agente)"}}}}}'
```

```json
{"id":5,"status":"draft",
 "attributes":{"is_published":false, …},
 "translations":{"es":{"title":"Conexion MCP (actualizada por el agente)"},
                 "en":{"title":"End-to-end MCP connection"}}}   // el inglés no se toca
```

---

## Paso 7 · Proponer publicación o borrado para revisión humana

Una llamada a `cms_entity_delete` con `type` e `id`, o una actualización con
`attributes.publish=true`, no aplica el cambio directamente. Ejemplo abreviado:

```json
{"confirmation_required":true,"execution_id":"<uuid>",
 "review_url":"http://localhost:8080/admin/executions/<uuid>","execution":{"status":"awaiting_approval"}}
```

Presenta `review_url` al humano. Este abre una sesión del backoffice, revisa el
cambio concreto y lo aprueba o rechaza; el backend vuelve a comprobar identidad,
permisos y disponibilidad. El agente consulta después el estado de esa ejecución.
No repitas con un booleano de confirmación: no concede autoridad. No hay un
endpoint API ni una capacidad MCP que apruebe ejecuciones.

---

## Paso 8 · Recursos y acciones del backoffice

```json
// cms_resource_list resource=menu
{"resource":"menu","total":3,"items":[{"slug":"footer_legal","name":"Enlaces legales del pie","is_active":1}, …]}

// cms_maintenance_get
{"active":false,"settings":{"is_enabled":false,"headline":"PLATAFORMA EN CONSTRUCCIÓN", …}}

// cms_search_reindex
{"indexed":{"post":9,"product":6},"total":15}

// cms_settings_get  (secretos enmascarados)
{"settings":[]}
```

Prueba también: `cms_module_toggle` (activa/desactiva un módulo y vuelve),
`cms_content_sections_sync` (bloques de una página), `cms_media_upload`,
`cms_seo_filter_upsert`, `cms_token_revoke`. Las acciones sensibles solicitan
revisión humana; la aprobación editorial también exige las reglas de autorización.

---

## Paso 9 · Transporte SSE

El endpoint SSE entrega la URL de mensajes de la sesión y luego acepta JSON-RPC
por `sessionId`:

```bash
curl -s -N --max-time 3 http://localhost:8080/api/mcp/sse \
  -H "Authorization: Bearer $TOKEN"
```
```
event: endpoint
data: http://localhost:8080/api/mcp/message?sessionId=677ff6aa-0466-4824-9517-77f906b6b316
```

```bash
curl -s -X POST "http://localhost:8080/api/mcp/message?sessionId=677ff6aa-…" \
  -H "Authorization: Bearer $TOKEN" -H 'Content-Type: application/json' \
  -d '{"jsonrpc":"2.0","id":30,"method":"tools/list"}'
# → catálogo disponible para esta identidad
```

---

## Paso 10 · Los límites (lo que NO puede hacer)

Un agente nunca puede más que la persona que emitió el token. Contratos de autorización (el texto exacto del error puede variar):

**Token de solo lectura intentando escribir**

```json
{"error":"Token lacks the [mcp:write] scope required to write.","denied":true,
 "required_scope":"mcp:write","required_permission":"manage:posts"}
```

**Token de un usuario sin `manage:modules` intentando tocar módulos**

```json
{"error":"The user behind the token lacks the [manage:modules] permission.",
 "denied":true,"required_scope":"mcp:write","required_permission":"manage:modules"}
```

**Token de usuario sin `mcp:destructive` intentando borrar**

```json
{"error":"Token lacks the [mcp:destructive] scope required to destructive.",
 "denied":true,"required_scope":"mcp:destructive","required_permission":"manage:posts"}
```

Un cliente máquina-a-máquina necesita una cuenta CMS vinculada en
`oauth_service_accounts`, un rol elegido explícitamente y scopes concedidos que
ese rol permita. Scopes amplios sin binding o permiso real no autorizan. Créalo
con `cms:create-m2m-client --role=editor --scopes=mcp:access,mcp:read,m2m:service`
si ese rol existe y permite esos scopes; no se amplían sus permisos automáticamente.

---

## Paso 11 · Auditoría de la sesión

```json
// cms_mcp_audit
{"stats":{"total":23,"by_status":{"ok":20,"denied":3},
          "top_tools":{"cms_entity_create":3,"cms_module_toggle":3,"cms_capabilities":2,…}},
 "entries":[{"tool":"cms_settings_get","status":"ok","ability":"read",
             "permission":"manage:modules","user":"admin@db-cms.io","is_m2m":false,
             "args":[],"duration_ms":1,"created_at":"2026-10-01T06:07:47+00:00"}, …]}
```

Los secretos se guardan como `***` (cualquier argumento cuya clave contenga
`password`, `secret`, `token`, `api_key`…).

---

## Paso 12 · Revocar y verificar

```bash
./vendor/bin/sail artisan tinker --execute="
\$tokens = \Laravel\Passport\Token::query()->whereIn('name', ['MCP Docu Test','MCP Demo Practica'])->with('refreshToken')->get();
foreach (\$tokens as \$token) { \$token->revoke(); optional(\$token->refreshToken)->revoke(); }
echo 'activos restantes: '.\Laravel\Passport\Token::query()->where('revoked', false)->count();
"
```

```
revocado: MCP Docu Test
revocado: MCP Demo Practica
activos restantes: 0
```

Un token revocado deja de autorizar al instante:

```json
{"jsonrpc":"2.0","error":{"code":-32001,
 "message":"Unauthorized: Invalid or expired Passport OAuth2 token."},"id":null}
```

---

## Chuleta de conexión

| Concepto | Valor |
| :--- | :--- |
| Transporte nativo (MCP estándar) | `POST /mcp/cms` |
| Transporte HTTP (JSON-RPC) | `POST /api/mcp` |
| Transporte SSE | `GET /api/mcp/sse` → `POST /api/mcp/message?sessionId=…` |
| Manifiesto autenticado | `GET /api/mcp/tools` |
| Token | `POST /oauth/token` (client_credentials) o `cms:issue-mcp-token` |
| Métodos | `tools/list`, `tools/call` |
| Alcances | `mcp:access` + `mcp:read` / `mcp:write` / `mcp:destructive` |
| Cabeceras | `Authorization: Bearer …`, `Content-Type: application/json` |
| Módulo | Administración → Módulos → *Servidor MCP* |

### Script de humo (copia y pega)

```bash
BASE=http://localhost:8080
TOKEN=$(./vendor/bin/sail artisan cms:issue-mcp-token --user=1 --name="Smoke $(date +%s)" \
        | sed -n '/Bearer Access Token/,/^$/p' | sed -n '2p')

call() { curl -s -X POST "$BASE/api/mcp" -H "Authorization: Bearer $TOKEN" \
           -H 'Content-Type: application/json' \
           -d "{\"jsonrpc\":\"2.0\",\"id\":1,\"method\":\"tools/call\",
                \"params\":{\"name\":\"$1\",\"arguments\":$2}}"; }

call cms_inspect_system '{}'          | head -c 120; echo
call cms_entity_types '{}'            | head -c 120; echo
call cms_entity_list '{"type":"post","per_page":2}' | head -c 200; echo
call cms_mcp_audit '{"limit":3}'      | head -c 200; echo
```

### Errores frecuentes

| Síntoma | Causa / solución |
| :--- | :--- |
| `-32001 Invalid or expired Passport OAuth2 token` | token mal copiado, caducado o revocado → emite otro |
| `-32003 Token lacks the [mcp:write] scope…` | emite el token con `mcp:read,mcp:write,mcp:destructive` |
| `The user behind the token lacks the [manage:…] permission` | usa un usuario con ese permiso o amplía su rol |
| `-32000 MCP Server module is currently disabled` | activa el módulo `mcp` |
| `Method not found: initialize` | usa `/mcp/cms` para handshake nativo; `/api/mcp` es el adaptador JSON-RPC |
