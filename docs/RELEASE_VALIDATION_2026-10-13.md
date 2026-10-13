# Validación y recuperación de publicación de dbCMS 1.0.0 / 1.0.1

Fecha: 13 de octubre de 2026. El repositorio privado `dbravoan/dbcms` permanece
como plantilla y no se despliega a un dominio. Packagist publicó `v1.0.0` desde
`3c8619b05b253f4ac609e54ff9ec73a6d59d42d3`; una modificación posterior de la
etiqueta fue bloqueada por su política de versiones estables inmutables. Este
documento conserva la evidencia local y define la recuperación como `v1.0.0`
histórica source-only más una nueva `v1.0.1` con las correcciones de entrega.

## Versión y preparación

- La recuperación alinea `VERSION`, los manifiestos Node, defaults y README en
  `1.0.1`. `v1.0.0` conserva el contenido que Packagist ya hizo público.
- Se conservan referencias locales de recuperación para los commits y objetos de
  etiqueta implicados. Ningún respaldo local se publica como rama o etiqueta remota.
- La comprobación autenticada confirmó repositorio privado, plantilla y Actions
  habilitados, sin secretos ni variables de repositorio.
  `VPS_DEPLOY_ENABLED` no está activo; el alojamiento no recibe un despliegue.
- La revisión de archivos e historial no encontró credenciales reales versionadas;
  las 35 capturas contienen contenido QA, sin claves ni tokens visibles.
  La documentación distingue pruebas contractuales de proveedores
  de conexiones reales y no promete disponibilidad externa ni posicionamiento.

## Evidencia local anterior a la corrección final de versionado

Los siguientes resultados corresponden al estado revisado antes del endurecimiento
de `app:version --tag`. No se atribuyen automáticamente al commit final: la nueva
regresión, el código corregido y sus verificaciones deben superar un gate posterior.

| Comprobación | Resultado acreditado |
| --- | --- |
| Gate aislado en Docker | 1.430 pruebas Pest y 15.081 aserciones; 40 pruebas de comportamiento frontend correctas |
| Dependencias, estilos y análisis | Composer/npm audit, build, Pint y PHPStan incluidos en el gate, sin fallos |
| Producción limpia | Instalación sin demo y arranque con configuración y claves propias |
| HTTP local | 10 rutas de backoffice, 9 comprobaciones públicas y 4 hijos de sitemap verificados |
| Paquete independiente | ZIP generado, checksum comprobado y arranque tras desempaquetar; revisión sin credenciales distribuidas |
| Concordancia de código | 1.576 archivos comparados entre el código revisado y los artefactos de verificación |

Estos recorridos y fixtures prueban funcionalidades concretas; no equivalen a probar
todas las combinaciones de backoffice ni a certificar proveedores externos. La
[validación anterior](PENDING_CLOSURE_AND_QUALITY_VALIDATION_2026-10-12.md) conserva
el alcance del cierre de arquitectura, seguridad y flujo editorial.

## Corrección de publicación y gate local final

El comando de versión escribía una nueva versión y podía etiquetar el HEAD anterior.
La corrección retira el etiquetado automático y conserva `--tag` como opción
obsoleta que se rechaza antes de escribir. Las guías utilizan:
incrementar cuando corresponda, alinear los manifiestos, confirmar, verificar,
publicar `main`, esperar sus checks y crear una etiqueta anotada explícita.
La corrección funcional conserva este comportamiento y la entrega pasa a `1.0.1`.

Las tres regresiones fallaron antes de corregir el comando. Después pasaron los
siete casos del comando, con 36 aserciones: rechazo sin alterar `VERSION`, `.env`
o etiquetas reales de un repositorio temporal, más inspección, bump/set y errores.

`bash bin/ci-check` terminó con salida **0** en Docker aislado:

| Comprobación del código corregido | Resultado |
| --- | --- |
| Pest | **1.433 pruebas / 15.098 aserciones**, 815,57 s; sin fallos |
| Navegador | **40 pruebas**, sin fallos ni omisiones |
| PHPStan nivel 8 | **0 errores** |
| Pint | **1.214 archivos** conformes |
| Composer/npm | Validación, instalación y auditorías correctas; sin vulnerabilidades conocidas al ejecutar |
| Compilación | Assets e imagen de producción construidos |
| Instalación productiva aislada | Un administrador, cero páginas/posts/productos, ocho idiomas y mercados; clave conservada, contraseña verificada, debug desactivado |
| Archivos privados y assets | `.env` y clave privada 0600; dos entradas de manifiesto, sin archivos ausentes |
| HTTP real | Login con sesión, diez pantallas administrativas, nueve checks públicos y cuatro hijos XML; PHP subido 404 y `.env` 403 |
| Igualdad de código | **1.576 archivos** coincidentes entre fuente, CI y runtime productivo |

La tabla se completa después del gate. Los ajustes posteriores son documentales;
se comprueba de nuevo la integridad de la documentación y que el código ejecutable
conserva la misma huella. Los checks de GitHub validan después el commit publicado.

## Aceptación remota y evidencia de publicación

### Primer intento y corrección multiarquitectura

El primer workflow de la etiqueta `v1.0.0` fue
[la ejecución 38353192279](https://github.com/dbravoan/dbcms/actions/runs/38353192279).
El gate de calidad y la construcción de archivos terminaron correctamente, pero la
imagen conjunta agotó sus 30 minutos durante la emulación ARM64. GitHub canceló el
trabajo Docker y no se creó una release. El artefacto de esa ejecución no sustituye
la aceptación de la entrega definitiva.

La corrección distribuye AMD64 y ARM64 en runners nativos. Cada trabajo publica una
imagen intermedia por digest, ejecuta `php artisan --version` dentro de esa imagen y
sólo entonces entrega su digest. Un trabajo posterior reúne ambos digests, crea el
manifiesto y comprueba que contiene las dos arquitecturas antes de permitir la
release. Las regresiones de YAML se registraron en rojo contra el workflow anterior
y pasan después de la corrección; también pasan las 103 pruebas del conjunto de
regresión, con 1.943 aserciones, y `actionlint` no informa errores.

Aunque GitHub todavía no había creado una release en el primer intento, Packagist
ya había publicado `v1.0.0` desde `3c8619b`. La etiqueta se movió posteriormente a
`7c79077cf683ae59b113eb71a712f636aadbdbcd`; Packagist rechazó la actualización
porque una versión estable no puede cambiar de contenido. La regla se aplica desde
la primera publicación y no admite esta excepción.

La recuperación prevista restaura `v1.0.0` a `3c8619b`, retira de GitHub los
archivos construidos desde `7c79077` y elimina de GHCR las referencias `1.0.0` y
`latest` incompatibles. La release histórica queda limitada al código fuente que
GitHub genera para la etiqueta. El código corregido se versiona como `v1.0.1`.
Ninguna de estas operaciones remotas se da por terminada en este documento hasta
comprobar sus referencias y artefactos después de ejecutarlas.

Para reducir el tiempo sin rebajar el control, `main` ejecuta el gate completo una
sola vez. La release exige mediante la API de GitHub un CI correcto de tipo `push`
sobre `main` y con `head_sha` idéntico al commit etiquetado. El despliegue consume
ese mismo SHA aprobado y permanece condicionado por `VPS_DEPLOY_ENABLED=true`.

La publicación sólo se considera cerrada cuando quedan acreditados:

1. `v1.0.0` local, GitHub y Packagist resuelven a `3c8619b`; su release no contiene
   ZIP/TAR propios ni una imagen OCI bajo `1.0.0`.
2. Gate completo del commit de `v1.0.1`, con pruebas de que `--tag` no altera
   archivos ni referencias, también al combinarlo con `--bump` o `--set`.
3. `main`, `origin/main` y `v1.0.1` resuelven al mismo SHA, cuyo CI está en verde.
4. La release valida ese SHA exacto sin repetir el gate y publica ZIP, TAR.GZ,
   checksums e imagen privada amd64/arm64 bajo `1.0.1` y `latest`.
5. Packagist muestra `v1.0.1` con el mismo SHA y conserva `v1.0.0` en `3c8619b`.
6. La plantilla sigue privada y el despliegue continúa omitido.

El SHA final y los logs privados se registran en la evidencia de ejecución externa
al contenido del commit, evitando que documentar su propio SHA cambie ese commit.
Los resultados remotos y el SHA se consultan en [Actions](https://github.com/dbravoan/dbcms/actions),
en [v1.0.0](https://github.com/dbravoan/dbcms/releases/tag/v1.0.0), en
[v1.0.1](https://github.com/dbravoan/dbcms/releases/tag/v1.0.1) y en
[Packagist](https://packagist.org/packages/dbravoan/dbcms).
Estos criterios no se presentan como comprobaciones remotas ya ejecutadas por el
mero hecho de incluirlos en el commit. La evidencia local se conserva en
`storage/app/runtime-verification/release-20261013/`, excluida de Git, sin credenciales.

## Operación posterior

El [runbook de plantilla](GITHUB_TEMPLATE_REGULASE_RUNBOOK.md) describe copias para
RegulaSE y el blog personal, cada una con configuración, datos y claves propios.
Las actualizaciones requieren portado revisado y pruebas en cada repositorio;
el template no mantiene una dependencia sincronizada entre proyectos.
