# dbCMS

CMS modular para publicar páginas, blog, catálogo y formularios en varios mercados
e idiomas. La versión **3.1.1** actualiza la base publicada 3.1.0. Cada proyecto
selecciona sus módulos, bloques, temas y forma de despliegue.

El núcleo incluye instalación, usuarios y permisos, medios, páginas, menús,
idiomas, mercados, publicación, secciones y SEO técnico. Los módulos opcionales
aportan funciones de negocio; pueden incluir varios bloques. Un paquete visual
solo distribuye bloques. Todos usan el mismo registro y control de disponibilidad.

Laravel 13 · PHP 8.3 · MySQL 8 · Passport OAuth2 · Blade/Alpine · Tailwind 4 · Vite 6.
Redis es opcional. El panel ofrece las marcas `dbcms` y `termocms`; cada proyecto
elige los temas públicos que distribuye.

## Puesta en marcha

### Crear una web propia

Usa [esta plantilla de GitHub](https://github.com/new?template_name=dbcms&template_owner=dbravoan),
clona tu repositorio y ejecuta desde su carpeta:

```bash
make init
```

También puedes extraer el ZIP de fuentes y usar `./bin/project-init` sin Make ni
Git. Necesitas Linux, Docker y Compose; PHP, Composer y Node se ejecutan dentro
de contenedores. El asistente verifica una release estable publicada, permite
seleccionar marca, temas, módulos, paquetes y despliegue, y prepara Sail.
Si no existe una release compatible, se detiene sin sustituirla por desarrollo.

Abre la URL `/install` que imprime el comando y completa la base de datos y tu
administrador. La preparación local no instala la base, demo ni claves OAuth.
En días posteriores arranca con `./vendor/bin/sail up -d` y detén con
`./vendor/bin/sail down`.

[Guía de proyectos derivados](docs/DERIVED_PROJECTS_GUIDE.md) ·
[Entorno existente y bootstrap sin vendor](docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md)

### Instalar en hosting sin consola

Descarga `dbcms-installer-v3.1.1.zip` de la
[release oficial](https://github.com/dbravoan/dbcms/releases), verifica su SHA256,
súbelo y extráelo en el alojamiento. Incluye dependencias y assets compilados.
Configura el dominio sobre `public/` cuando el panel lo permita y abre el sitio
por HTTPS. Sigue [EMPEZAR.html](EMPEZAR.html) y el asistente web.
Los enlaces automáticos **Source code** de GitHub son fuentes para desarrollo.

## Usar el CMS

Empieza por [editar y publicar contenido](docs/USING_THE_CMS.md): idiomas y
mercados, páginas, bloques, medios, menús y revisión editorial.

Los módulos distribuidos incluyen Blog, Shop, Gallery, Forms, Search, Ads,
Analytics, Meta, Captcha, Maintenance, ContentAudit, AiAssistant, Mcp, Marketing,
Orchestration, CreativeStudio y SocialPublishing. Se instalan explícitamente con
sus dependencias. Desactivarlos impide usar sus contribuciones y conserva datos e
instancias. [Registro y ciclo de extensiones](docs/CORE_EXTENSION_REGISTRATION.md).

La IA prepara propuestas que se revisan y guardan mediante el editor habitual.
Necesita permisos y conexiones compatibles con cada modalidad. Las publicaciones
sociales requieren configuración, autorización y revisión propias; una prueba
local no certifica una conexión externa.

- [Redactar, generar medios y traducir con IA](docs/AI_EDITOR_TRANSLATION.md)
- [Revisar y aprobar contenido](docs/CONTENT_AUDIT_WORKFLOW.md)
- [Conectar agentes mediante MCP](docs/25_CONNECTING_AI_AGENTS_OVER_MCP.md)
- [CreativeStudio y SocialPublishing](docs/MARKETING_MODULES_RUNBOOK.md)

## Desarrollar extensiones

Lee [AGENTS.md](AGENTS.md), [Core and extensions](specs/CORE_AND_EXTENSIONS.md)
y las [convenciones](docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md).

- [Crear un módulo funcional y añadirle bloques](docs/11_CREATING_ADVANCED_CMS_MODULES_GUIDE.md)
- [Crear bloques y paquetes visuales](docs/26_CREATING_BLOCKS_AND_VISUAL_PACKAGES_GUIDE.md)
- [Referencia de campos del editor de bloques](docs/27_BLOCK_EDITOR_FIELD_REFERENCE.md)
- [Componentes administrativos](docs/13_ADMIN_UI_FRAMEWORK.md)
- [Crear y sustituir vistas desde temas](docs/17_FRONTOFFICE_THEME_GUIDE.md)
- [Índice técnico completo](docs/09_DIRECTORY_STRUCTURE_AND_FILE_INDEX.md)

No ejecutes PHP, Composer, Artisan ni Pest en el host. Con Sail iniciado, usa
`./vendor/bin/sail`; dentro del contenedor CI/bootstrap usa sus binarios directamente.
`make test` ofrece feedback rápido. `make test-integration` y `make test-release`
usan archivos y base de datos desechables. [Alcance de cada carril](docs/TESTING.md).

## Operar y entregar

- [Despliegue y recuperación por perfil](docs/DEPLOYMENT_GUIDE.md)
- [Caché, rendimiento y seguridad del runtime](docs/19_PERFORMANCE_AND_DEPLOYMENT_RUNBOOK.md)
- [Preparar y publicar una entrega firmada](docs/DEPLOYMENT_AND_VERSIONING.md)
- [Actualizaciones y personalizaciones](docs/CORE_UPDATES_RUNBOOK.md)
- [Reportar vulnerabilidades](SECURITY.md)

Las comprobaciones completas, compilación y empaquetado se preparan localmente
en Docker para el SHA exacto. GitHub verifica y consume esos artefactos firmados;
la publicación Docker tiene su propio workflow. Nunca reutilices datos, claves
o credenciales de demostración en producción.

## Licencia y autoría

Licencia [MIT](LICENSE). Desarrollado por
[David Bravo](https://github.com/dbravoan) (`hola@davidbravo.com.es`).
