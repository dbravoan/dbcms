# Revisión posterior del refactor — 2026-10-16

Revisión del trabajo integrado por otro proceso antes de continuar. Complementa
la [verificación anterior](REFACTOR_VERIFICATION_2026-10-16.md); no introduce
contratos de compatibilidad ni sustituye las especificaciones vigentes.

## Fallos corregidos

- Un ZIP podía publicar una definición que rompiera el siguiente arranque.
  Ahora se comprueban clases, contratos, colisiones, esquema y payload en un
  proceso separado antes de publicar el directorio. Se rechazan excepciones,
  salidas anticipadas y procesos que exceden el límite.
- Los ZIP visuales se guardan en `storage/app/extensions/blocks`. El destino
  anterior no era escribible por el usuario de producción y perdía el código
  al sustituir el contenedor, aunque persistiera su registro en la base de datos.
- La instalación de un módulo no confirma su estado si falla el proveedor.
  Los cambios de disponibilidad reconstruyen también las páginas que contienen
  sus bloques, dentro de la transacción. Workers y caché de rutas se invalidan
  después del commit; un rollback conserva las señales y archivos anteriores.
- La caché de vistas recorría todo el repositorio e intentaba compilar vistas
  de módulos sin instalar. Las vistas de temas usan `themes::`; las vistas
  administrativas de Forms pertenecen a Forms. El arranque de producción ya
  ejecuta sus cachés reales en las verificaciones de imagen.
- Una sección sin traducción ya no publica contenido de otro idioma. Los
  formularios comparten el payload canónico de secciones y no crean bloques
  demo al abrirse. Vacío y Demo son decisiones distintas del editor.
- El editor de galería conserva la entrada rechazada, aísla eventos del selector
  de medios y comparte JavaScript productivo con sus pruebas. La traducción de
  un contenedor desactivado no impide traducir sus hijos disponibles.
- El editor vuelve a usar contenido vigente al desactivar la revisión editorial.
  El correo exige transporte real incluso si el test utiliza `MailFake`.
- El instalador no muestra éxito sin su marcador de instalación. Sus pruebas
  verifican preparación, navegación, persistencia y acceso al terminar, en lugar
  de conservar textos promocionales antiguos.
- Las pruebas OAuth preparan sus claves explícitamente en memoria. La prueba de
  restauración usa las credenciales administrativas canónicas del entorno
  desechable y sólo elimina bases de datos que haya creado correctamente.
- Se retiraron parámetros sin efecto, rutas antiguas, datos ocultos duplicados,
  formatos históricos de secciones y comprobaciones de cantidades incidentales.
  README y la guía de temas describen los comandos y rutas actuales.

## Verificación

- Pasada exhaustiva final sobre el código integrado: **1.858 pruebas PHP,
  16.252 aserciones, cero fallos**, en **928,69 s**. Tres omisiones explícitas:
  una selección de despliegue cubierta por la matriz del catálogo fuente y dos
  contratos de perfil físicamente inicializado. La instalación derivada física
  sí se ejecutó con `RUN_DERIVED_CLONE_SMOKE=1`; no se omitió ese recorrido.
- Suite habitual, PHP más frontend, con dependencias preparadas:
  **7,712 / 7,688 / 8,396 s**. Mediana **7,712 s**, máximo observado **8,396 s**.
  Cada pasada ejecutó **38 pruebas PHP, 639 aserciones y 53 pruebas frontend**.
  No hay un antes comparable para calcular una reducción porcentual. Build,
  auditorías, arranque y análisis estático quedan fuera de estos tiempos.
- Pint completo: **1.559 archivos correctos**. PHPStan nivel 8: **sin errores**.
  Build correcto y auditorías Composer/npm sin vulnerabilidades detectadas.
- Distribución física sin `modules/`: caché de vistas antes de instalar,
  instalación, autenticación HTTP, publicación, lectura localizada y sitemap.
- La reproducción de rutas usa procesos nuevos: la caché anterior ocultaba las
  diez rutas administrativas de Blog; el cambio confirmado elimina esa caché
  y permite registrarlas en el siguiente arranque. El rollback la conserva.
- Imagen `bf19f34c2c1bfb64de58b75eb66f85f599bab65ea9a03a5c9ecbcb5536b32899`:
  tres contenedores nuevos, entrypoint real, `APP_ENV=production`, sin red y con
  el mismo volumen desechable. Como `dbcms` (UID 1000), el ZIP se instala
  desactivado; después de recrear se activa y renderiza; otra recreación conserva
  archivo, catálogo, estado y renderizado. Las cachés de configuración, rutas y
  Blade pasan en cada arranque. Se usó SQLite con fixture mínima para esta
  frontera de permisos/persistencia, sin atribuirle validación FULLTEXT/MySQL.

Una pasada anterior alcanzó 1.857 pruebas correctas y tres omisiones, pero falló
por la credencial antigua de la fixture de restauración: no se presenta como una
suite verde. El resultado final anterior incorpora también las correcciones de
caché de rutas y almacenamiento persistente de ZIP.

## Alcance y evidencia

Las verificaciones usan copias, bases de datos, volúmenes e imágenes desechables.
No se han reinicializado la instalación local, su `.env`, claves ni contenidos.
Los logs de esta revisión están fuera del repositorio, en
`/tmp/dbcms-followup-*`. No se han creado commits, publicado ni desplegado.
