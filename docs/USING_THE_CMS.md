# Editar y publicar contenido

Esta guía recorre el panel ya instalado. Para instalar, utiliza
[el inicio del proyecto](../README.md#puesta-en-marcha). Entra con el administrador
creado por el asistente; `ADMIN_PATH` define la ruta del panel.
Los menús y las operaciones disponibles dependen de los permisos y módulos activos.

## Preparar idiomas y mercados

Configura los idiomas y mercados del sitio antes de crear contenido. Un mercado
admite sus idiomas seleccionados. Una traducción no asigna automáticamente el
contenido a todos los mercados ni lo publica.

Al editar, selecciona primero los mercados del elemento y después la pestaña de
idioma. Completa título, slug y los demás campos de esa traducción. Usa un slug
único para su contexto. [Detalles de localización](14_LOCALIZATION_AND_I18N.md).

## Crear una página y sus bloques

1. Abre **Páginas** y crea una página. Completa su título y slug en el idioma elegido.
2. Añade una sección y selecciona un bloque disponible: Texto, Hero u otra definición registrada. La imagen se configura dentro del bloque que la admite.
3. Completa sus campos. Para contenido en paralelo, configura las columnas de la
   sección y añade los bloques a cada columna. Revisa la composición antes de guardar.
4. Selecciona imágenes desde la biblioteca y revisa textos alternativos, enlaces y
   la apariencia de la sección. Usa los campos previstos para cada valor.
5. Guarda con el formulario de la página. Cambiar campos o aplicar una propuesta
   de IA modifica el formulario; hace falta guardar para persistirlo.

Un bloque es una pieza de contenido configurada en una sección. Algunos pertenecen
al núcleo, otros a un paquete visual o a un módulo. Un módulo como Blog aporta
sus propios artículos y puede ofrecer bloques para listarlos dentro de cualquier
página. Instalar otro módulo no es necesario para crear cada bloque.

Cuando su propietario se desactiva, el bloque deja de estar disponible para nuevas
selecciones y no se ejecuta públicamente. Las instancias guardadas se conservan
con una indicación de indisponibilidad; pueden recuperarse al reactivar el propietario.

## Publicar y comprobar el resultado

Guardar un borrador y publicar son operaciones distintas. Comprueba el estado de
publicación, las fechas programadas y los mercados del elemento. Si ContentAudit
exige revisión para tu rol, solicita aprobación y consulta su estado; guardar o
solicitar revisión no garantiza que ya sea público.

Abre la URL pública del mercado e idioma correspondientes en una sesión anónima.
Comprueba título, texto, bloques, imágenes, enlaces y navegación. La vista previa
del editor no sustituye esta comprobación. Una traducción ausente no implica
publicación en otro idioma. [Revisión y aprobación](CONTENT_AUDIT_WORKFLOW.md).

## Medios, menús y extensiones

La biblioteca permite subir y seleccionar archivos con los permisos de medios.
Revisa las referencias antes de borrar un recurso usado en contenido. Configura
los menús desde su gestor y comprueba sus destinos en los idiomas y mercados del sitio.

Blog, Shop, Gallery y Forms tienen pantallas propias cuando sus módulos están
instalados y activos. Conservan sus permisos, traducciones y publicación. Los
formularios públicos requieren su configuración de campos y protecciones:
[campos y validación](21_FORM_RENDERING_AND_FIELD_TYPES.md).

La [guía de IA](AI_EDITOR_TRANSLATION.md) explica redacción, medios y traducción.
Las propuestas necesitan revisión; el contenido generado no acredita hechos ni
publica por sí mismo. Para administrar extensiones, consulta
[registro, instalación y disponibilidad](CORE_EXTENSION_REGISTRATION.md).
