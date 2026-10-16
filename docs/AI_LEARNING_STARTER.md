# IA en práctica

Starter editorial opcional de dbCMS: formación sobre IA con seis cheatsheets por
área, una guía de flujos y agentes y otra de control del escritorio. Una instalación
normal de dbCMS permanece vacía; este contenido solo se crea al ejecutar el comando.

## Preparación

Desde un checkout limpio de una release oficial de dbCMS, con Git y Docker:

```bash
bin/create-ai-learning-project ../mi-blog-ia --deploy=none
```

El nombre se toma del último componente del destino (`mi-blog-ia`). El directorio
no debe existir y su padre sí debe existir. El destino debe estar fuera del árbol
fuente y ni él ni sus ancestros pueden ser enlaces simbólicos. El comando rechaza
un checkout con cambios en archivos versionados; los archivos locales ignorados
no se copian. Un ZIP de ejecución sin Git no sirve como fuente de este exportador.

El exportador consulta exclusivamente el tag anotado `vVERSION` en
`https://github.com/dbravoan/dbcms.git` y exige que su commit coincida con `HEAD`.
Una rama con cambios posteriores o una versión aún no publicada no puede producir
un proyecto con procedencia oficial. No admite repositorios ni revisiones de
origen alternativos. La release no puede contener enlaces simbólicos o submódulos.

Se exporta ese commit con `git archive`, sin copiar `.env`, dependencias instaladas,
claves, datos ni cachés del proyecto fuente. Solo se recuperan las plantillas
`.env.*example` y los `.gitignore` de los directorios de ejecución. Se conservan
físicamente Blog, Basic, `ia-practica` y la marca administrativa `dbcms`, y se
excluye el seeder completo de showcase. Después se ejecuta el inicializador
existente `bin/project-init` dentro del nuevo directorio: prepara Composer mediante
Docker y registra la procedencia oficial en `cms-project.json`, sin PHP en el host.
El exportador verifica que la procedencia escrita por el inicializador coincide
con el commit oficial ya comprobado, y añade de forma atómica al mismo
`cms-project.json` esta selección de código fuente:

```json
"source_selection": {
    "module_directories": ["Blog"],
    "block_package_directories": ["Basic"],
    "include_showcase": false
}
```

La escritura usa PHP dentro del entorno Docker, con el UID/GID del usuario al
lanzarlo desde el host. Conserva la procedencia oficial y el resto del perfil;
no crea otro archivo de selección. La planificación de futuras actualizaciones
respeta esta declaración y no vuelve a proponer los módulos, paquetes visuales
o showcase excluidos. Los temas y la marca conservan su selección ya registrada
en el perfil. Un perfil genérico sin `source_selection` conserva el catálogo
completo; eliminar directorios sin declarar su selección no ofrece esa garantía.

El destino permanece **sin instalar** y no recibe contenido editorial todavía.
Si el inicializador falla, el destino parcial permanece para inspección; el
exportador no reutiliza ni sobrescribe ese directorio al repetirlo.

`--deploy` admite `none`, `github-actions`, `ansistrano`, `rsync` o `docker`.
El valor predeterminado es **`none`**: no se conserva un flujo de despliegue
preseleccionado. Cada alternativa conserva los controles de despliegue definidos
por el contrato de proyectos derivados; no despliega ni publica el sitio.

## Instalación independiente

Dentro del nuevo proyecto:

```bash
cp .env.example .env
```

Antes de arrancar, edita **solo ese nuevo `.env`**. Configura un
`COMPOSE_PROJECT_NAME` propio, una base de datos y credenciales independientes,
la URL `APP_URL` definitiva y puertos libres: `APP_PORT`, `VITE_PORT`,
`FORWARD_DB_PORT`, `FORWARD_MAILPIT_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT` y
`FORWARD_ADMINER_PORT`. Si activas Redis, reserva también `FORWARD_REDIS_PORT`.
No reutilices `.env`, almacenamiento, claves ni volúmenes de otro proyecto.

```bash
./vendor/bin/sail up -d --build
./vendor/bin/sail npm ci
./vendor/bin/sail npm run build
```

Abre la nueva `APP_URL` y completa el asistente **sin contenido demo**. Este
perfil no incluye el showcase; solicitar `--with-demo` se rechaza antes de las
migraciones de aprovisionamiento. El asistente crea las claves de la nueva
instalación; el exportador no copia ni genera claves del proyecto fuente.
Asegura que el mercado elegido tenga español activo. Establece la URL pública
definitiva antes de generar los enlaces editoriales.

Con Sail arrancado, un administrador existente y un mercado activo con español:

```bash
./vendor/bin/sail artisan cms:starter:ai-learning --author=admin@example.test --market=es
```

El comando comprueba primero que no haya páginas, menús, categorías ni artículos.
Rechaza instalaciones ocupadas y no ofrece una opción para sobrescribirlas.
También comprueba que Blog, Basic y el tema estén presentes antes de escribir.
Instala o activa Blog mediante su ciclo canónico y activa Basic explícitamente.
Las migraciones de extensión se completan antes de la transacción editorial:
MySQL no permite revertir DDL. Si falla el contenido, la extensión puede quedar
instalada, pero las páginas, categorías, artículos, menús y marcador se revierten.
Una repetición tras completar el starter conserva todas las ediciones.

## Edición

Las páginas `home`, `ruta-de-aprendizaje`, `cheatsheets` y `metodologia` son páginas
publicadas del CMS con secciones Basic. La portada incluye una cuadrícula editable
de enlaces a las guías. Cada artículo es una entrada Blog con traducción española,
categoría, autor, descripción SEO y contenido HTML editable desde el backoffice.
Los menús usan las ubicaciones `header` y `footer_quick`. No se habilitan comentarios
ni se necesita ninguna credencial de IA para leer o editar el proyecto.

Los originales están en `resources/starters/ia-practica/`. Son material inicial:
modificarlos no actualiza una instalación ya creada. Después de instalar, las
ediciones se hacen sobre las entidades del CMS. Si cambia el dominio, revisa los
enlaces de contenido y menús como parte de la migración de dominio.

Cada ejercicio explicita objetivo, entradas, pasos, prompt, revisión humana,
entregable y límites. Las fuentes técnicas enlazadas explican agentes y control
del ordenador sin prometer compatibilidad, ahorro o disponibilidad de herramientas.
La publicación usa SEO técnico de dbCMS; la indexación, las posiciones y la
inclusión en respuestas generativas dependen de terceros y no se garantizan.

## Verificación

`tests/Feature/Projects/AiLearningStarterTest.php` comprueba el comando real,
lectura HTTP de las guías, edición administrativa, rechazo de contenido previo,
conservación al repetir, rollback editorial y disponibilidad con Blog desactivado.
Ejecuta las pruebas en la composición desechable descrita en [Testing](TESTING.md).
El perfil físico `Blog + Basic + ia-practica + dbcms` dispone de una selección
explícita en `bin/ci-derived-tests`. `make test` ofrece comprobaciones rápidas;
`make test-integration` y `make test-release` usan copias y bases de datos
desechables para los controles estáticos, compilación y recorridos del perfil.
No ejecutes directamente el conjunto de instalación sobre la base de datos viva.
La selección del perfil no convierte una prueba enfocada en una validación de
todo el catálogo fuente.

La comprobación física del proyecto mínimo y las puertas completas de release
son adicionales a estas pruebas enfocadas. La aceptación del exportador se hace
contra una release oficial publicada, comprobando la procedencia registrada y
un recorrido real de instalación, arranque, lectura y edición del nuevo proyecto;
comprobar `bash -n` y `--help` por sí solo no demuestra ese recorrido.
