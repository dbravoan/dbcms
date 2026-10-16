# Verificación del refactor — 2026-10-16

Evidencia de esta revisión del árbol de trabajo integrado; no constituye una
norma adicional. Los contratos vigentes son [Core and extensions](../../specs/CORE_AND_EXTENSIONS.md)
y [Testing](../TESTING.md).

## Correcciones integradas

- Núcleo independiente de módulos opcionales; composición explícita y validación
  del grafo, proveedores y colisiones antes de modificar el estado instalado.
- Mutaciones y revisiones editoriales con validación previa, transacciones,
  autorización y control de versiones. Los snapshots incompletos se rechazan
  antes de borrar relaciones o contenido.
- Catálogo común de bloques y widgets, paquetes Charts/KPI/ROI y BeforeAfter
  canónicos, validación recursiva y conservación de instancias desactivadas.
  Una instancia anterior no autoriza duplicados desactivados.
- Consumidores administrativos, generadores, traducción, IA, MCP, recursos e
  interfaces de ambas marcas ajustados a los contratos actuales.
- Adaptadores de almacenamiento invalidados cuando cambia su configuración;
  metadatos de temas y aislamiento de caché corregidos.
- Fixtures explícitos y mínimos, retirada de pruebas de contratos eliminados y
  conservación de evidencia de permisos, rollback, commit y concurrencia real.

## Resultados

- `bin/ci-check release` en el contenedor desechable: **correcto**. Incluye
  validación y auditoría Composer, instalación y auditoría npm (sin
  vulnerabilidades detectadas), build, Pint y PHPStan nivel 8.
- Pasada exhaustiva PHP: **1.832 pruebas correctas, 16.119 aserciones**, sin
  fallos ni casos riesgosos, en **2.186,07 s**. Las tres omisiones corresponden
  a contratos del perfil físicamente inicializado: dos de marca/tema/activación
  y uno de selección de despliegue; el catálogo fuente tiene su propia matriz.
  La prueba física de generación e instalación de un proyecto derivado sí se
  ejecutó en esta pasada de release.
- Frontend completo: **53 correctas, cero fallos y cero omisiones**.
- Tres ejecuciones de la suite habitual, PHP más frontend con el entorno
  preparado: **5,585 / 5,599 / 5,851 segundos**. Mediana **5,599 s**, máximo
  observado **5,851 s**; cada ejecución pasó 36 pruebas PHP (631 aserciones)
  y 53 pruebas frontend. No hay una medición inicial comparable para afirmar
  un porcentaje de reducción ni estimar percentiles.
- Distribución física sin `modules/`, con dependencias propias y base de datos
  temporal: instalación, login HTTP, creación/publicación, lectura pública
  localizada y sitemap correctos.
- Imagen de producción construida con herramientas creativas desactivadas:
  arranque de Laravel y presencia de dependencias y assets compilados correctos.
  La imagen de CI incluye FFmpeg y fuentes para comprobar PNG y MP4 reales.
- Actualización bajo Apache: correctas las fronteras de actualización tanto con
  document root en la raíz del sitio como en `public/`.

La pasada exhaustiva se inició antes de la última retirada mínima de
`GET /admin/maintenance` y su controlador de redirección, que no tenían
consumidores actuales. Después de esa retirada se verificaron por separado el
panel canónico, las operaciones vigentes y las superficies de ambas marcas:
**21 pruebas, 245 aserciones** (42,19 s en el contenedor integrado), además de
una nueva pasada completa de Pint y PHPStan. La imagen de producción también se
reconstruyó y arrancó después de esa retirada. No se presenta esa
ejecución filtrada como una segunda pasada exhaustiva.

Los logs de esta ejecución se conservan localmente, fuera del control de versiones,
en `storage/app/runtime-verification/refactor-20261016/`.

## Alcance

El refactor es disruptivo y se verifica para instalaciones nuevas. No se han
convertido los registros históricos de la instalación local ni borrado su base
de datos, `.env`, claves o contenido. Su composición antigua incluye módulos
retirados y no se declara compatible con esta arquitectura. No se ha publicado
ni desplegado el resultado.
