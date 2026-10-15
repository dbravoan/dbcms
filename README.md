# dbCMS — CMS empresarial modular multi-mercado Human+IA

[![Use this template](https://img.shields.io/badge/GitHub-Use%20this%20template-2ea44f?style=for-the-badge&logo=github)](https://github.com/new?template_name=dbcms&template_owner=dbravoan)
[![Arquitectura](https://img.shields.io/badge/Arquitectura-Clean%20%26%20Spec--Driven-indigo.svg)](#arquitectura-humania)
[![Módulos](https://img.shields.io/badge/Módulos-16%20desacoplados-emerald.svg)](#módulos-incluidos)
[![Multi-mercado](https://img.shields.io/badge/Multi--mercado-sin%20duplicación%20de%20base%20de%20datos-emerald.svg)](#normalización-multi-mercado)
[![GEO y SEO](https://img.shields.io/badge/Optimización-GEO%20%26%20SEO-blue.svg)](#seo-y-geo)
[![MCP](https://img.shields.io/badge/MCP-Streamable%20HTTP%20%2B%20JSON--RPC%20%2B%20SSE-purple.svg)](#servidor-mcp)
[![Entorno](https://img.shields.io/badge/Entorno-Sail%20(Docker)%20Zero--Host-sky.svg)](#puesta-en-marcha)
[![Versionado](https://img.shields.io/badge/Versionado-SemVer%202.0.0-orange.svg)](#versionado-y-despliegue)
[![Versión](https://img.shields.io/badge/Versión-1.2.0-orange.svg)](CHANGELOG.md)
[![PHPStan](https://img.shields.io/badge/PHPStan-nivel%208%20sin%20baseline-magenta.svg)](#calidad-y-verificación)
[![Tests](https://img.shields.io/badge/Tests-Pest%20(Unit%2C%20Feature%2C%20Architecture)-green.svg)](#calidad-y-verificación)
[![Stack](https://img.shields.io/badge/Stack-Laravel%2013%20·%20PHP%208.3-red.svg)](#qué-es-dbcms)

> **dbCMS** es un CMS empresarial para operar contenidos, blog y comercio electrónico en varios países e idiomas con una sola instalación y un solo panel. Está pensado para que las personas editen y publiquen con fluidez y para que los agentes de IA trabajen en el mismo sistema con permisos controlados, sin fricción.

## Índice

1. [Qué es dbCMS](#qué-es-dbcms)
2. [Capacidades destacadas](#capacidades-destacadas)
3. [Módulos incluidos](#módulos-incluidos)
4. [Temas](#temas)
5. [Arquitectura Human+IA](#arquitectura-humania)
6. [Puesta en marcha](#puesta-en-marcha)
7. [Guía para desarrolladores](#guía-para-desarrolladores)
8. [Protocolo para agentes de IA](#protocolo-para-agentes-de-ia)
9. [Servidor MCP: de la voz a la publicación](#servidor-mcp)
10. [SEO y GEO](#seo-y-geo)
11. [Calidad y verificación](#calidad-y-verificación)
12. [Versionado y despliegue](#versionado-y-despliegue)
13. [Documentación](#documentación)
14. [Licencia y autoría](#licencia-y-autoría)

---

## Qué es dbCMS

dbCMS es una plataforma de contenidos y comercio **multi-mercado**: cada entidad (página, entrada, producto, categoría) se traduce a varios idiomas y se asigna a los mercados que correspondan, sin duplicar filas en la base de datos. Las URL quedan segmentadas por mercado e idioma (`/es/es/…`, `/us/en/…`) y cada mercado puede tener su propia configuración de SEO, moneda y contenidos.

El sistema está construido bajo el paradigma **Human+IA**: el mismo núcleo sirve a editores humanos, a desarrolladores y a agentes autónomos.

- **Para editores y negocio**: un backoffice completo con constructor de páginas por secciones, blog, tienda, formularios, galerías, auditoría de contenido, SEO y temas visuales intercambiables.
- **Para desarrolladores**: arquitectura modular limpia, especificaciones formales, TDD con Pest, análisis estático con PHPStan y un entorno Docker aislado.
- **Para agentes de IA**: un servidor estándar **MCP** (Model Context Protocol) y una superficie JSON-RPC con autenticación OAuth2, scopes granulares y registro de auditoría de cada operación.

### Stack

| Capa | Tecnología |
| --- | --- |
| Framework | Laravel 13 (PHP 8.3) |
| Base de datos | MySQL 8 |
| Caché y colas | Redis |
| Testing | Pest 4 (Unit, Feature, Architecture) |
| Análisis estático | PHPStan (nivel 8) + Larastan |
| Estilo | Laravel Pint |
| Frontend | Tailwind CSS 4, Alpine.js 3, Vite 6, Font Awesome |
| Auth de API | Laravel Passport (OAuth2) |
| Protocolo de agentes | MCP (`laravel/mcp`) + JSON-RPC 2.0 + SSE |

## Capacidades destacadas

| Capacidad | Descripción |
| --- | --- |
| Módulos desacoplados | 16 módulos independientes en `modules/`, instalables y activables desde el panel o desde MCP |
| Multi-mercado real | Mercados, idiomas y divisas gestionados desde el backoffice; URL localizadas y clústeres hreflang |
| Constructor de páginas | Páginas compuestas por secciones estilizadas, animaciones y widgets embebidos (módulo troncal `pages`) |
| Blog y tienda | Blog multiautor con etiquetas y tiempo de lectura; catálogo de productos con carrito y pago |
| Temas | 6 temas de frontoffice intercambiables en caliente y 2 temas de backoffice |
| Editorial anti-IA | Validación de naturalidad del texto: ritmo de frases, clichés, mayúsculas y puntuación en castellano |
| SEO y GEO | `/llms.txt`, `/llms-full.txt`, sitemaps segmentados, `robots.txt`, feed RSS y datos estructurados Schema.org |
| Servidor MCP | Streamable HTTP nativo, JSON-RPC y SSE con tokens Passport y scopes `mcp:*` |
| Instalación asistida | Asistente web en `/install` y comando CLI `cms:install` para producción |
| Calidad automatizada | Pest (Unit, Feature, Architecture), PHPStan y Pint integrados en CI/CD |

## Módulos incluidos

Cada módulo vive en su propia carpeta `modules/{Nombre}/` con su manifiesto `module.json`, sus migraciones, rutas, modelos, servicios y vistas. El módulo troncal, marcado con `is_core: true`, es **`pages`**.

| Módulo | Identificador | Qué aporta |
| --- | --- | --- |
| Composite Page Builder | `pages` | Constructor de páginas por secciones estilizadas, animaciones y widgets embebidos. **Módulo troncal.** |
| Blog Engine | `blog` | Blog multiautor y multi-mercado con categorías, etiquetas, tiempo de lectura y widgets |
| Product Catalog & E-Commerce | `shop` | Catálogo y tienda con precios por mercado, constructor modular de producto, carrito y pago |
| Media Gallery | `gallery` | Galerías de imágenes con filtros, optimización WebP y visor lightbox |
| Dynamic Form Builder | `forms` | Constructor de formularios con validación en tiempo de ejecución, honeypot antispam y captación localizada |
| Google AdSense & Ad Placements | `ads` | Auto-anuncios de AdSense, banners responsivos y segmentación por mercado |
| Analytics & GDPR Consent | `analytics` | Google Analytics 4, Tag Manager y banner de consentimiento conforme al RGPD |
| Meta Pixel & Server CAPI | `meta` | Meta Pixel y Conversions API del servidor (CAPI) para derivar eventos de leads |
| Charts & Data Visualization | `charts` | KPI animados, estadísticas comparativas y gráficos interactivos |
| Before & After Comparison | `before_after` | Comparador interactivo «antes y después» para casos clínicos, diseño y reformas |
| Security & Dynamic Captcha | `captcha` | Captcha dinámico SVG y matemático con tokens cifrados para formularios y accesos |
| Mantenimiento & Construcción | `maintenance` | Modo mantenimiento y página «en construcción» con temporizador y acceso de administradores |
| Auditoría de Contenidos | `content_audit` | Flujo editorial de calidad, permisos de publicación por rol, cola de aprobación, comparador de versiones y bloqueo editorial anti-IA configurable |
| Copiloto Asistente IA | `ai_assistant` | Asistencia editorial y maquetación de bloques con Gemini, OpenAI, DeepSeek, Anthropic, Ollama y OpenCode |
| General Search | `search` | Buscador del frontoffice sobre todas las entidades, con URL amigable y facetas |
| Servidor MCP | `mcp` | Servidor MCP estándar con transporte SSE / JSON-RPC 2.0 y autenticación Passport |

Los módulos se instalan, activan, desactivan y desinstalan desde el backoffice (`/admin/modules`) o mediante las herramientas MCP, con análisis de impacto antes de cada operación destructiva. Desinstalar retira acceso y capacidades, conserva tablas y contenido y permite reinstalar sin perder datos. Discovery no reactiva un módulo desinstalado.

## Temas

### Frontoffice

El tema activo se elige con la variable `CMS_THEME` o desde el backoffice. Incluye 6 temas de autor y un tema neutro de referencia:

| Tema | Personalidad |
| --- | --- |
| `db-cyber` | Estética cyber-terminal con acentos neón y fondo generativo (tema por defecto) |
| `db-editorial` | Publicación minimalista de alta legibilidad en modo claro |
| `db-brutalist` | Neo-brutalismo con bordes gruesos, sombras duras y colores saturados |
| `db-minimal` | Minimalismo nórdico monocromo con máximo espacio negativo |
| `db-enterprise` | SaaS corporativa en tonos pizarra e índigo para soluciones B2B |
| `db-sunset` | Glassmorphism sobre fondo obsidiana con degradados de atardecer |
| `default-clean` | Tema neutro de referencia: respaldo seguro y base para crear temas nuevos |

La resolución de vistas sigue una cascada: primero la vista sobreescrita del tema activo, después la vista del módulo y por último el tema de referencia `default-clean`. Más detalles en [`docs/17_FRONTOFFICE_THEME_GUIDE.md`](docs/17_FRONTOFFICE_THEME_GUIDE.md).

### Backoffice

Dos temas de panel, seleccionables con `CMS_BRAND`: `dbcms` y `termocms`. La identidad visual (nombre, lema, logo, acento) se declara en el manifiesto de cada tema, no en la configuración. Véase [`docs/18_THEME_MANIFEST_AND_BRAND_REFERENCE.md`](docs/18_THEME_MANIFEST_AND_BRAND_REFERENCE.md).

## Arquitectura Human+IA

```
=====================================================================================================
                              ARQUITECTURA CMS HUMAN + IA
=====================================================================================================
       PARA PERSONAS                                             PARA AGENTES DE IA
+---------------------------------------------+      +---------------------------------------------+
|  Editores y marketing                       |      |  Agentes de código y autonomía              |
|  · Backoffice completo, UX cohesiva         |      |  · Especificaciones formales y DTO           |
|  · Constructor de páginas por secciones     |      |  · Política Zero-Host (todo dentro de Sail)  |
|  · Temas intercambiables sin tocar código   |      |  · TDD con Pest y análisis estático          |
|  · Auditoría editorial y prompts anti-IA    |      |  · MCP: leer, escribir y publicar con scopes |
+---------------------------------------------+      +---------------------------------------------+
                              \                              /
                               \                            /
                                v                          v
+---------------------------------------------------------------------------------------------------+
|     EL PUENTE: MODEL CONTEXT PROTOCOL (MCP) Y FLUJO VOZ → AGENTES → PUBLICACIÓN                    |
|  Una persona da una instrucción en lenguaje natural y un equipo de agentes investiga, redacta,     |
|  valida el tono editorial y publica de forma atómica, con auditoría de cada operación.             |
+---------------------------------------------------------------------------------------------------+
```

## Puesta en marcha

### Cómo empezar un nuevo proyecto

Tienes 3 formas de comenzar con dbCMS:
1. **GitHub Template (Recomendada en la nube)**: Haz clic en [**Use this template**](https://github.com/new?template_name=dbcms&template_owner=dbravoan) en la parte superior para generar un repositorio independiente limpio para tu web con los pipelines de CI/CD ya listos.
2. **Composer CLI (Línea de comandos)**: Ejecuta `composer create-project dbravoan/dbcms mi-sitio`.
3. **Instalador ZIP para navegador (recomendado)**: Descarga **`dbcms-installer-v1.2.0.zip`** desde [Releases](https://github.com/dbravoan/dbcms/releases), súbelo y extráelo desde Plesk/cPanel y abre el dominio. Incluye dependencias y recursos compilados; no requiere Composer, Node, Artisan ni SSH. Los enlaces automáticos **Source code (zip/tar.gz)** de GitHub son para desarrollo y no sirven como instalador autónomo.

### Requisitos locales (Desarrollo)

- **Docker** con Docker Compose. Todo lo demás (PHP 8.3, MySQL 8, Redis, Node) corre dentro de los contenedores.
- La política **Zero-Host** (aislamiento total del equipo anfitrión) prohíbe ejecutar `php`, `composer` o `artisan` fuera del contenedor. Usa siempre `./vendor/bin/sail` o el envoltorio `./bin/sail-run`.

### Inicio rápido local

```bash
# 1. Si acabas de clonar el repositorio y no existe vendor/, instala las
#    dependencias de PHP dentro de un contenedor desechable (política Zero-Host)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" \
  -w /var/www/html laravelsail/php83-composer:latest composer install

# 2. Copia el entorno base
cp .env.example .env

# 3. Levanta los contenedores (aplicación, MySQL, Redis, Mailpit, Adminer, cola)
./vendor/bin/sail up -d

# 4. Genera la clave de la aplicación y las claves de Passport
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan passport:keys

# 5. Ejecuta las migraciones y siembra los datos de demostración
#    (mercados, idiomas, temas, contenidos y usuarios de prueba)
./vendor/bin/sail artisan migrate --seed
```

La plantilla y Composer distribuyen fuentes: antes de abrir el sitio, compila los recursos en Docker. El instalador ZIP ya incluye `vendor/` y `public/build/` y no requiere estos pasos.

```bash
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

Durante el desarrollo puedes usar `./vendor/bin/sail npm run dev` en lugar del build estático. Composer prepara `.env` y la clave inicial; conserva esa clave al configurar la instalación.

### Accesos tras la instalación

| Servicio | URL | Credenciales |
| --- | --- | --- |
| Frontoffice | http://localhost | — |
| Backoffice | http://localhost/admin | Usuarios de demostración (tabla siguiente) |
| Adminer (gestor de base de datos) | http://localhost:8088 | Servidor `mysql`, usuario `sail`, contraseña `secret`, base de datos `laravel` |
| Mailpit (correo de desarrollo) | http://localhost:8025 | — |

### Credenciales de demostración

El sembrador crea cinco usuarios, todos con la contraseña `secret123`:

| Rol | Correo | Permisos |
| --- | --- | --- |
| Admin | `admin@db-cms.io` | Acceso total |
| Editor | `editor@db-cms.io` | Gestión de contenidos |
| Author | `author@db-cms.io` | Redacción de contenidos propios |
| Marketing | `marketing@db-cms.io` | SEO, formularios y analítica |
| Viewer | `viewer@db-cms.io` | Solo lectura |

Estas credenciales son para entornos de demostración y desarrollo. En producción, define tu propio administrador con el instalador. En local puedes activar el cambio de rol con un clic (`CMS_QUICK_LOGIN=true` en `.env` y la ruta `/admin/quick-login/{rol}`).

### Instalación guiada

En el ZIP para navegador basta con subir, extraer y abrir el dominio: la primera visita prepara la clave de cifrado y abre el asistente. La guía incluida `EMPEZAR.html` detalla el recorrido para Plesk/cPanel. El asistente comprueba la conexión MySQL/MariaDB, ejecuta las migraciones, crea el administrador y sella el entorno. Si el hosting no permite crear bases desde PHP, créala antes desde el panel y pega sus datos.

En desarrollo, abre **http://localhost/install** para usar el mismo asistente.

Para servidores de producción existe el instalador de línea de comandos:

```bash
./bin/sail-run php artisan cms:install --with-demo
```

Admite `--db-*` para la conexión, `--admin-*` para el administrador, `--brand` y `--theme` para la identidad visual, y `--without-demo` para una instalación mínima. La guía completa está en [`docs/DEPLOYMENT_AND_VERSIONING.md`](docs/DEPLOYMENT_AND_VERSIONING.md).

## Guía para desarrolladores

### Organización del código

| Carpeta | Contenido |
| --- | --- |
| `app/` | Núcleo: módulos base, servicios transversales, objetos DTO, eventos, middleware, comandos |
| `modules/{Nombre}/` | Un paquete autónomo por funcionalidad |
| `themes/` | Temas del frontoffice |
| `resources/views/themes/admin/` | Temas del backoffice |
| `docs/` | Documentación técnica (véase el [índice](#documentación)) |
| `specs/` | Especificaciones formales del diseño dirigido por especificaciones |
| `tests/` | Suites de Pest: `Unit/`, `Feature/` y `Architecture/` |

### Convenciones esenciales

- **Zero-Host**: todos los comandos se ejecutan dentro del contenedor. Hay un guardián (`bin/guard-host-php.sh`) que bloquea PHP en el host.
- **Spec-driven design**: antes de programar se escriben las especificaciones en `specs/` y los objetos DTO inmutables en `app/DTOs/`.
- **TDD**: primero el test en rojo con Pest, después la implementación, después el refactor.
- **Thin Controllers** (controladores delgados): clases invocables con sufijo de verbo HTTP (`IndexGetController`, `StorePostController`, `UpdatePutController`) y un máximo de 10 líneas por `__invoke()`. Sin SQL ni ramificaciones de negocio.
- **Fat Services** (servicios de dominio): toda la mutación de estado vive en servicios envueltos en `DB::transaction()` y regidos por contratos en `app/Contracts/Services/`.
- **Modelos**: esquema Eloquent puro (`$fillable`, `$casts`, relaciones). Sin algoritmos de negocio.
- **PHPStan**: análisis estático en nivel 8, sin baseline ni ignorados. **Pint**: estilo de código normalizado.

La referencia completa está en [`docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md`](docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md), [`docs/01_ARCHITECTURE_AND_CLEAN_CODE.md`](docs/01_ARCHITECTURE_AND_CLEAN_CODE.md) y [`CONTRIBUTING.md`](CONTRIBUTING.md).

### Arquitectura modular

- Cada módulo declara un manifiesto `module.json` con versión, metadatos, estado troncal (`is_core`), widgets y un esquema de configuración con tipos.
- Cada módulo monta sus rutas de backoffice en `modules/{Nombre}/Routes/admin.php`, protegidas por el middleware `module.enabled:{identificador}`.
- **Zero-Knowledge** (conocimiento cero): los módulos del núcleo (`pages`, `blog`, `shop`) no importan ni referencian módulos de extensión (`content_audit`, `ai_assistant`). La integración se resuelve con observadores del ciclo de vida, relaciones en tiempo de ejecución (`resolveRelationUsing`) y view composers sobre `@stack('admin_form_sidebar')` y `@stack('admin_modals')`.

### Normalización multi-mercado

Cada entidad usa tres niveles, sin duplicar filas entre mercados:

1. **Tabla base** (`posts`, `pages`, `products`): estado, orden y fechas.
2. **Tabla de traducciones** (`post_translations`, `page_translations`, …): título, slug, contenido y locale, con clave única compuesta `[entidad, locale, market_id]`.
3. **Tabla pivote de mercados** (`market_post`, …): asocia la entidad a los mercados (`es`, `us`, `mx`).

Así, una entrada traducida a dos idiomas y visible en tres mercados no multiplica sus filas: cada traducción y cada asignación se registra una sola vez.

### Crear un módulo nuevo

```bash
./bin/sail-run php artisan make:cms-module Events --pageable
```

El comando genera el manifiesto, el proveedor de servicios, los modelos normalizados, los objetos DTO, los servicios transaccionales, los controladores delgados y las migraciones. Admite `--with-auth` (usuarios propios aislados), `--dedicated-panel` (portal administrativo propio) y `--crud` (pantallas de mantenimiento con el kit de UI del backoffice). La guía avanzada está en [`docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md`](docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md).

### Resolución de temas en cascada

El resolver de vistas (`app/Services/Cms/FrontViewResolver.php`) busca, en este orden:

1. La vista del tema activo: `themes/{tema_activo}/{módulo}/{vista}.blade.php`
2. La vista por defecto del módulo: `modules/{módulo}/Resources/views/{vista}.blade.php`
3. El tema de referencia: `themes/default-clean/{módulo}/{vista}.blade.php`

Los layouts y widgets siguen una cascada equivalente. Véase [`docs/17_FRONTOFFICE_THEME_GUIDE.md`](docs/17_FRONTOFFICE_THEME_GUIDE.md).

### Página de bienvenida y cero caídas

Si se ejecuta una instalación sin módulos activos, el núcleo responde con HTTP 200 y renderiza la pantalla de bienvenida `resources/views/welcome-cms.blade.php`, que presenta los módulos disponibles. La portada nunca devuelve un 404 o un 500 por ausencia de configuración.

## Protocolo para agentes de IA

Los agentes que operen sobre este repositorio deben seguir las reglas de [`AGENTS.md`](AGENTS.md). Resumen:

1. **Aislamiento Zero-Host**: todo comando mediante `./bin/sail-run <comando>`.
2. **Especificaciones formales**: contratos en `specs/*.md` y objetos DTO inmutables antes del código.
3. **TDD**: tests de Pest en rojo antes de implementar la lógica en servicios de dominio.
4. **Análisis estático**: `./bin/sail-run ./vendor/bin/phpstan` con cero errores (nivel 8, sin baseline).
5. **Mutaciones quirúrgicas**: cambios acotados a fragmentos contiguos pequeños; no se reescriben archivos enteros para tocar un método.

## Servidor MCP

El CMS expone su capacidad operativa a agentes externos mediante el estándar **Model Context Protocol**, con dos superficies complementarias:

| Superficie | Endpoint | Protocolo |
| --- | --- | --- |
| Servidor MCP nativo (SDK `laravel/mcp`) | `POST /mcp/cms` | Streamable HTTP con handshake `initialize`; compatible con Claude Desktop, Cursor, VS Code, MCP Inspector y OpenCode |
| JSON-RPC del CMS | `POST /api/mcp` | JSON-RPC 2.0 (`tools/list`, `tools/call`) |
| SSE del CMS | `GET /api/mcp/sse` | Eventos del servidor para sesiones de larga duración |
| Mensajes de sesión | `POST /api/mcp/message` | Continuación de una sesión SSE |
| Catálogo autorizado | `GET /api/mcp/tools` | Herramientas disponibles para la identidad OAuth |

### Autenticación y scopes

Todas las operaciones requieren un token Bearer de **Laravel Passport** con el scope `mcp:access`, refinado por `mcp:read`, `mcp:write` y `mcp:destructive` según la operación, además de los permisos de la cuenta. Los clientes M2M deben estar asociados explícitamente con una cuenta de servicio y un rol. Cada llamada queda registrada en `mcp_audit_logs`. Publicar, borrar o modificar una aplicación externa devuelve una ejecución para revisar desde el backoffice; `confirm:true` no concede aprobación.

Para emitir un token de agente:

```bash
./bin/sail-run php artisan cms:issue-mcp-token
```

El comando acepta `--user`, `--name` y `--scopes`. También puedes gestionar tokens personales y clientes de máquina a máquina desde el backoffice (`/admin/tokens`).

### Catálogo de herramientas

El servidor publica cerca de 40 herramientas, entre ellas:

- **Inspección**: `cms_inspect_system`, `cms_list_modules`, `cms_get_entity`, `cms_list_filter_dimensions`
- **Contenido**: `cms_publish_post`, `cms_publish_page`, `cms_publish_product`, CRUD completo de entradas, páginas, productos y categorías
- **Investigación**: `cms_search_content`, `cms_research_topic_context`, `cms_search`, `cms_browse_content`
- **Editorial**: `cms_get_editorial_prompt`, `cms_validate_editorial_tone`, `cms_humanize_prose`
- **SEO y GEO**: `cms_audit_seo_geo`, `cms_resolve_filter_url`

Las capacidades genéricas de entidades, recursos y acciones se generan desde registros del CMS. El catálogo efectivo filtra módulos activos, operaciones implementadas, conexiones, modalidades y permisos; el manifiesto estático es una referencia de compatibilidad. La ejecución comparte servicios de dominio y revalida autorización antes de cada paso.

### Orquestación nativa del CMS

El motor es PHP/Laravel y utiliza `App\Core\Capabilities`; MCP adapta el protocolo. La entrada de instrucciones se habilita con `CMS_ORCHESTRATION_ENABLED=true`, desactivada por defecto. Requiere el módulo IA activo y un modelo de texto configurado. Las herramientas concretas MCP siguen funcionando sin modelo y con esa entrada desactivada.

El CMS valida planes de hasta 20 pasos, conserva checkpoints e idempotencia y pide aclaraciones ante objetivos ambiguos. Crea borradores solicitados con autor, mercados, traducciones y bloques. Editar contenido publicado conserva la versión pública hasta su revisión. Las aprobaciones humanas se vinculan al actor, los argumentos y la revisión; perder permisos o cambiar el contenido obliga a revisar de nuevo. Un resultado remoto incierto queda detenido para conciliación.

`use:ai` permite consumir IA; `manage:ai` administra conexiones. La migración concede uso a los roles estándar administrador, editor, autor y marketing, y administración sólo a administradores; los roles personalizados requieren asignación expresa. Las sesiones web usan RBAC, sin scopes ficticios. LangGraph, Google ADK y conexiones personales quedan fuera de esta implementación.

La tienda acepta transferencia y contrarreembolso, con pedidos pendientes de pago; tarjeta/Stripe están desactivados. El inventario y los precios del mercado se revalidan bajo bloqueo. Los pedidos son privados y los invitados usan enlaces firmados de 24 horas. La caché HTML pública guarda una plantilla sin estado del visitante y reconstruye los fragmentos privados en cada petición; las respuestas hidratadas no se comparten en CDN.

Procedimiento de activación, migraciones, revisión y recuperación: [`NATIVE_ORCHESTRATION_RUNBOOK.md`](docs/NATIVE_ORCHESTRATION_RUNBOOK.md).

### Flujo voz → agentes → publicación

```
[Instrucción en lenguaje natural]
"Investiga la ley de IA, redacta un post que no suene a robot y publícalo en España"
                            |
                            v
                [Planificador nativo del CMS]
                            |
     +----------------------+----------------------+
     |                      |                      |
     v                      v                      v
[1. Investigación]     [2. Redacción]        [3. Humanización]
 cms_inspect_system     cms_research_topic_   cms_validate_editorial_tone
 cms_search_content     context               cms_humanize_prose
                        cms_get_editorial_    (ritmo de frases, cero clichés)
                        prompt
     +----------------------+----------------------+
                            |
                            v
                 [4. Auditoría SEO / GEO]
                 cms_audit_seo_geo (comprobaciones locales)
                            |
                            v
                 [5. Revisión humana y publicación]
                 cms_publish_post / cms_publish_page
                 · Aprobación del cambio desde el backoffice
                 · Emisión del evento PostPublished / PagePublished
                 · Invalidación de caché dirigida
```

La conexión real, paso a paso, está documentada en [`docs/24_MCP_REAL_CONNECTION_WALKTHROUGH.md`](docs/24_MCP_REAL_CONNECTION_WALKTHROUGH.md), [`docs/25_CONNECTING_AI_AGENTS_OVER_MCP.md`](docs/25_CONNECTING_AI_AGENTS_OVER_MCP.md) y [`docs/23_MCP_BACKOFFICE_PARITY.md`](docs/23_MCP_BACKOFFICE_PARITY.md).

### Motor editorial anti-IA

El servicio `AntiAiEditorialService` calcula una heurística de estilo con diccionarios y reglas de español, inglés, francés y alemán. No demuestra autoría humana ni verifica hechos, normativa, ranking o citas:

- **Ritmo (burstiness)**: desviación típica de la longitud de las frases con umbral σ ≥ 8,0. Un texto con frases de longitud uniforme recibe penalización y una recomendación concreta.
- **Clichés localizados**: diccionario de muletillas típicas de IA en español, inglés, francés y alemán («en el vertiginoso mundo de», «un tapiz de», «es crucial destacar», «in conclusion»…). `cms_humanize_prose` las sustituye por giros naturales.
- **Ortografía en castellano**: títulos en *sentence case* (minúsculas salvo inicial y nombres propios), signos de apertura obligatorios (¿ y ¡) y detección de listas mecánicas en negrita.

El módulo Auditoría y Calidad permite exigir el control de estilo y fijar un umbral de 0 a 100. Los servicios comunes comprueban todos los idiomas y bloques después de sincronizarlos, respetando los roles sujetos a revisión; las operaciones explícitas de publicación mantienen además su validación y confirmación. Por defecto la comprobación es consultiva. Aprobar una revisión puede conservarla privada si la publicación automática está desactivada. El [runbook editorial](docs/CONTENT_AUDIT_WORKFLOW.md) describe permisos, revisiones, restauración, notificaciones y límites.

Los prompts editoriales oficiales, en español e inglés, se descargan desde el backoffice (`/admin/ai/download-prompt`) o se leen en [`docs/prompts/PROMPT_EDITORIAL_HUMAN_IA.md`](docs/prompts/PROMPT_EDITORIAL_HUMAN_IA.md) y [`docs/prompts/EDITORIAL_HUMAN_AI_SYSTEM_PROMPT.md`](docs/prompts/EDITORIAL_HUMAN_AI_SYSTEM_PROMPT.md).

Los editores de páginas, artículos y productos incorporan **Traducir con IA**: genera una propuesta conservando bloques, columnas, enlaces y medios, exige revisión antes de sustituir un idioma existente y se guarda mediante el formulario habitual. [Guía de uso](docs/AI_EDITOR_TRANSLATION.md) y [entradas de texto, bloques, imágenes y portadas](docs/AI_EDITOR_ENTRYPOINTS.md). La generación necesita una conexión y un modelo operativo de la modalidad correspondiente.

## SEO y GEO

Superficies de indexación, servidas con caché de página versionada:

| Superficie | Endpoint | Para qué |
| --- | --- | --- |
| Índice de conocimiento para IA | `/llms.txt` y `/llms-full.txt` | Descripción del sitio para rastreadores de LLM (también por mercado e idioma) |
| Sitemaps | `/sitemap.xml` y `/sitemaps/{segmento}.xml` | Sitemaps segmentados por tipo de entidad con clústeres hreflang |
| Robots | `/robots.txt` | Política de rastreo dinámica |
| Feed | `/feed.xml` | Sindicación RSS del blog |
| Datos estructurados | Generados por `JsonLdSchemaService` | Gráficos Schema.org: `Organization`, `WebSite`, `Article`, `Product`… |
| Auditoría editorial | `/admin/seo/audit` | Comprobaciones locales de publicación, metadatos y estructura; no mide ranking ni citas |

Las superficies públicas son dinámicas e invalidan su caché con los cambios de
contenido. Para exportar una copia privada destinada a revisión fuera de línea:

```bash
./bin/sail-run php artisan geo:generate-llms-txt
```

El destino predeterminado es `storage/app/private/geo-export`, con permisos 0600.
`--output` admite otro directorio privado absoluto; rechaza el webroot y el
almacenamiento público. No copiar esos snapshots a `public/llms*.txt`: un fichero
estático podría conservar contenido retirado. Véase el vhost de
[DEPLOYMENT_GUIDE](docs/DEPLOYMENT_GUIDE.md).

## Calidad y verificación

```bash
# Gate completo sobre código, archivos y base de datos desechables
docker compose -p dbcms-quality -f docker/ci/compose.yml run --build --rm app bash bin/ci-check
# Retirar exclusivamente los servicios/volúmenes de ese proyecto de QA
docker compose -p dbcms-quality -f docker/ci/compose.yml down --volumes --remove-orphans

# Análisis estático (PHPStan, nivel 8, sin baseline)
./bin/sail-run ./vendor/bin/phpstan

# Formateador de estilo (Laravel Pint)
./bin/sail-run ./vendor/bin/pint

# Exportar snapshots privados (no sustituye los documentos públicos dinámicos)
./bin/sail-run php artisan geo:generate-llms-txt
```

El `Makefile` ofrece los mismos accesos abreviados: `make up`, `make down`, `make test`, `make phpstan`, `make pint`, `make migrate`, `make seed`, `make passport-keys`, `make lts-check`, `make geo-generate` y `make shell`.

Los tests usan `RefreshDatabase`; una base aislada no aísla archivos como `.env`,
locks de instalación o mantenimiento. Para la validación final usar el gate Docker
anterior, no una ejecución de tests de instalación sobre el runtime de desarrollo.

Para convertir el repositorio actual en plantilla y crear RegulaSE:
[guía operativa](docs/GITHUB_TEMPLATE_REGULASE_RUNBOOK.md),
[definición editorial](docs/REGULASE_PROJECT_BLUEPRINT.md) y
[auditoría final](docs/FINAL_SYSTEM_AUDIT_2026-10-12.md).

## Versionado y despliegue

El núcleo incorpora comprobación de releases estables y preparación de actualizaciones desde **Sistema → Actualizaciones**, con permiso `manage:updates`. Las instalaciones autónomas compatibles pueden actualizarse desde el navegador; los proyectos con Git o CI reciben una propuesta revisable para su despliegue. La primera incorporación en versiones antiguas requiere mantenimiento y adopción de una base oficial firmada. Consulta el [procedimiento de actualizaciones](docs/CORE_UPDATES_RUNBOOK.md), incluidos respaldos, conflictos y recuperación.

El proyecto sigue **SemVer 2.0.0** (`MAYOR.MENOR.PARCHE`) y mantiene su versión en el archivo `VERSION`. La versión actual es **1.2.0** (véase [`CHANGELOG.md`](CHANGELOG.md)).

```bash
# Consultar la versión activa y el estado del repositorio
./bin/sail-run php artisan app:version

# Incrementar la versión (major, minor, patch)
./bin/sail-run php artisan app:version --bump=minor

# Comprobar la diferencia antes de preparar el commit de versión
git diff -- VERSION package.json package-lock.json
```

También admite `--set=2.1.0` para fijar una versión concreta. El comando actualiza `VERSION` y `APP_VERSION` si existe en `.env`; no sincroniza automáticamente `package.json` ni `package-lock.json`. Alinear esas versiones cuando se incrementen. `--tag` está retirado y falla antes de escribir: crear la etiqueta anotada después del commit y de sus verificaciones, siguiendo [el procedimiento de publicación](docs/DEPLOYMENT_AND_VERSIONING.md). Toda etiqueta estable publicada es inmutable; una corrección posterior requiere una nueva versión PATCH. Los flujos de GitHub Actions cubren el ciclo completo:

| Workflow | Disparador | Acción |
| --- | --- | --- |
| `ci.yml` | Push y pull requests a `main` y `develop` | Validación de dependencias, auditoría de seguridad, Pint, PHPStan, build de frontend y Pest |
| `deploy.yml` | CI correcto sobre `main` o disparo manual; requiere `VPS_DEPLOY_ENABLED=true` | Consume el SHA exacto aprobado por CI. Conserva VPS/Plesk y releases/current/shared, con Artisan CLI en Docker, huella SSH fijada, mantenimiento, migración única, recarga PHP-FPM y `/up` HTTP 200. |
| `release.yml` | Etiquetas `v*.*.*` o disparo manual | Verifica que el SHA exacto ya superó CI y publica ZIP, TAR.GZ, checksums e imagen Docker multiarquitectura sin repetir el gate completo |

La guía completa está en [`docs/DEPLOYMENT_AND_VERSIONING.md`](docs/DEPLOYMENT_AND_VERSIONING.md), la [validación de publicación](docs/RELEASE_VALIDATION_2026-10-13.md) distingue pruebas locales y resultados de GitHub, y el runbook de rendimiento está en [`docs/19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md`](docs/19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md).

## Documentación

La documentación técnica vive en `docs/`, organizada por temas. Para mantener o extender el sistema, empezar por la [guía de arquitectura y mantenimiento](docs/ARCHITECTURE_MAINTENANCE_GUIDE.md), sus contratos y recorridos verificados. El [cierre de pendientes](docs/PENDING_CLOSURE_AND_QUALITY_VALIDATION_2026-10-12.md) actualiza la auditoría anterior y recoge los límites externos:

La [auditoría funcional por módulos del 11 de octubre de 2026](docs/CMS_MODULE_FUNCTIONAL_AUDIT_2026-10-11.md) recoge las pruebas locales reales, correcciones, entidades QA y límites de las conexiones y de SEO/GEO/AEO.

- **Fundamentos**: [`01_ARCHITECTURE_AND_CLEAN_CODE.md`](docs/01_ARCHITECTURE_AND_CLEAN_CODE.md), [`02_SPEC_DRIVEN_DESIGN_SDD.md`](docs/02_SPEC_DRIVEN_DESIGN_SDD.md), [`03_TOKEN_OPTIMIZATION_AND_KV_CACHE.md`](docs/03_TOKEN_OPTIMIZATION_AND_KV_CACHE.md), [`09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md`](docs/09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md)
- **Entorno y calidad**: [`06_ZERO_HOST_PHP_SAIL_RUNBOOK.md`](docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md), [`08_LTS_UPGRADE_AGENT_SPEC.md`](docs/08_LTS_UPGRADE_AGENT_SPEC.md)
- **Módulos y UI**: [`11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md`](docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md), [`13_ADMIN_UI_FRAMEWORK.md`](docs/13_ADMIN_UI_FRAMEWORK.md), [`21_FORM_RENDERING_AND_FIELD_TYPES.md`](docs/21_FORM_RENDERING_AND_FIELD_TYPES.md)
- **Frontoffice y temas**: [`15_FRONT_SECTION_EDITING.md`](docs/15_FRONT_SECTION_EDITING.md), [`16_BACKOFFICE_THEME_GUIDE.md`](docs/16_BACKOFFICE_THEME_GUIDE.md), [`17_FRONTOFFICE_THEME_GUIDE.md`](docs/17_FRONTOFFICE_THEME_GUIDE.md), [`18_THEME_MANIFEST_AND_BRAND_REFERENCE.md`](docs/18_THEME_MANIFEST_AND_BRAND_REFERENCE.md), [`20_FRONT_FILTERING_AND_SEO_URLS.md`](docs/20_FRONT_FILTERING_AND_SEO_URLS.md)
- **Localización y búsqueda**: [`14_LOCALIZATION_AND_I18N.md`](docs/14_LOCALIZATION_AND_I18N.md), [`22_GENERAL_SEARCH.md`](docs/22_GENERAL_SEARCH.md)
- **SEO, IA y MCP**: [`05_GEO_AND_SEO_ENGINEERING.md`](docs/05_GEO_AND_SEO_ENGINEERING.md), [`12_AI_PROVIDERS_AND_MODEL_CATALOG.md`](docs/12_AI_PROVIDERS_AND_MODEL_CATALOG.md), [`23_MCP_BACKOFFICE_PARITY.md`](docs/23_MCP_BACKOFFICE_PARITY.md), [`24_MCP_REAL_CONNECTION_WALKTHROUGH.md`](docs/24_MCP_REAL_CONNECTION_WALKTHROUGH.md), [`25_CONNECTING_AI_AGENTS_OVER_MCP.md`](docs/25_CONNECTING_AI_AGENTS_OVER_MCP.md)
- **Seguridad y despliegue**: [`07_PASSPORT_OAUTH2_SPECIFICATION.md`](docs/07_PASSPORT_OAUTH2_SPECIFICATION.md), [`DEPLOYMENT_AND_VERSIONING.md`](docs/DEPLOYMENT_AND_VERSIONING.md), [`19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md`](docs/19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md), [`SECURITY.md`](SECURITY.md)
- **Convenciones**: [`DEVELOPER_CONVENTIONS_AND_STANDARDS.md`](docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md), [`CONTRIBUTING.md`](CONTRIBUTING.md), [`AGENTS.md`](AGENTS.md)

## Licencia y autoría

**dbCMS** es software libre publicado bajo la licencia [MIT](LICENSE).

Desarrollado por [David Bravo](https://github.com/dbravoan) (`hola@davidbravo.com.es`). Incidencias y mejoras en el [repositorio de GitHub](https://github.com/dbravoan/dbcms).
