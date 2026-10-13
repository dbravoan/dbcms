# Guía Canónica de Versionado SemVer y Despliegue en dbCMS (`docs/DEPLOYMENT_AND_VERSIONING.md`)

> **Estándar**: Semantic Versioning 2.0.0 ([SemVer](https://semver.org/))  
> **Formato de Versión**: `MAJOR.MINOR.PATCH` (`0.0.0`)  
> **Runtimes de Despliegue**: GitHub Actions (`deploy.yml`) → VPS/Plesk con releases, current y shared; CLI en Docker. Docker Compose de producción es opcional. Local: Sail.

---

## 1. Filosofía y Estándar SemVer en dbCMS

**dbCMS** adopta una especificación canónica y determinista para gestionar la evolución del software mediante tres niveles numéricos:

$$\text{Versión} = \mathbf{MAJOR}.\mathbf{MINOR}.\mathbf{PATCH}$$

```
========================================================================================================
                                       ESTRUCTURA DE VERSIÓN SEMVER
========================================================================================================
        MAJOR (Cambios Grandes)          MINOR (Cambios Menores)             PATCH (Parches)
               [ 1 ]                   .          [ 2 ]            .             [ 0 ]
                 |                                  |                              |
                 v                                  v                              v
      - Rupturas de API                  - Nuevos módulos pluggable        - Corrección de bugs
      - Refactorizaciones estructurales  - Nuevos endpoints o vistas       - Parches de seguridad
      - Cambios incompatibles en BD      - Mejoras retrocompatibles        - Ajustes internos
========================================================================================================
```

### Clasificación y Definición de Cambios

| Nivel | Nombre | Incremento | Criterio de Activación | Ejemplo |
| :--- | :--- | :--- | :--- | :--- |
| **`MAJOR`** | **Cambio Grande / Estructural** | `(X+1).0.0` | Incompatibilidades con versiones anteriores, cambios en firmas de contratos principales, reestructuración profunda de rutas o migraciones destructivas. | `1.4.2` $\rightarrow$ `2.0.0` |
| **`MINOR`** | **Cambio Menor / Funcionalidad** | `X.(Y+1).0` | Nuevas funcionalidades, módulos adicionales (`Clinics`, `Portfolio`), nuevos endpoints REST o herramientas MCP sin romper la API existente. | `1.4.2` $\rightarrow$ `1.5.0` |
| **`PATCH`** | **Parche / Corrección** | `X.Y.(Z+1)` | Corrección de errores (bugfixes), optimizaciones de rendimiento, parches de seguridad menores y correcciones de estilo. | `1.4.2` $\rightarrow$ `1.4.3` |

---

## 2. Gestión de Versión mediante CLI (`php artisan app:version`)

El sistema incluye un comando Artisan nativo para consultar e incrementar la versión del proyecto:

### 2.1. Inspeccionar Versión Activa
```bash
./bin/sail-run php artisan app:version
```
Muestra en consola la versión SemVer actual leída del archivo `VERSION`, el entorno y el commit de Git.

### 2.2. Incrementar Versión Localmente
```bash
# Para un parche (bugfix):
./bin/sail-run php artisan app:version --bump=patch

# Para una nueva funcionalidad retrocompatible:
./bin/sail-run php artisan app:version --bump=minor

# Para un cambio grande / hito arquitectónico:
./bin/sail-run php artisan app:version --bump=major
```

### 2.3. Sincronizar y confirmar antes de etiquetar

`--bump` y `--set` actualizan `VERSION` y la entrada `APP_VERSION` existente en
`.env`. No actualizan `package.json` ni `package-lock.json`: al cambiar de versión,
alinear ambos manifiestos con `VERSION` usando el runtime Node del contenedor y
comprobar el diff. No incluir el `.env` privado en Git.

`--tag` está retirado: el comando lo rechaza antes de modificar archivos. Una etiqueta
identifica un commit, por lo que etiquetar tras escribir una versión sin haberla
commiteado señalaría el código anterior. Crear etiquetas anotadas explícitamente
después del commit, del gate local y de los checks de `main`.

---

## 3. Pipeline de entrega

`ci.yml` ejecuta una sola vez el gate completo dentro de Docker: Composer validate/audit, Pint, PHPStan nivel 8, Pest, compilación Vite y construcción de la imagen de producción. La publicación y el despliegue consumen esa decisión para el mismo SHA y no repiten el gate.

`release.yml` exige que la versión de tag o entrada manual coincida con `VERSION` y que exista una ejecución correcta de `ci.yml`, originada por un push a `main`, cuyo `head_sha` sea exactamente el commit que se publicará.
Compila AMD64 y ARM64 en runners nativos separados, publica resultados intermedios
por digest, arranca cada imagen para comprobar su runtime y sólo después crea y
verifica el manifiesto multiarquitectura. La release espera ese manifiesto y los
archivos ZIP, tarball y checksums. `bin/build-release-zip.sh` ejecuta Composer y PHP
dentro del contenedor CI.

`deploy.yml` se activa después de un CI correcto sobre `main` y usa exactamente su `head_sha`; la ejecución manual aplica la misma comprobación. Requiere además `VPS_DEPLOY_ENABLED=true`, un entorno `production` y los secretos SSH documentados en [la guía operativa](DEPLOYMENT_GUIDE.md). Envía un paquete construido en Docker y una imagen CLI identificada por SHA. Conserva PHP-FPM/Plesk, releases/current/shared; ejecuta una migración única en el contenedor CLI y exige /up con HTTP 200. Una promoción fallida restaura el enlace de código anterior si está disponible; no revierte la base de datos.

Para publicar una versión ya preparada:

```bash
git status --short
./bin/sail-run php artisan app:version
# Confirmar los archivos revisados y pasar el gate local antes de publicar main.
git push -u origin main
# Esperar los checks de GitHub del mismo commit antes de crear la etiqueta.
git tag -a v1.0.1 -m 'dbCMS v1.0.1' main
git rev-parse main 'v1.0.1^{}'
git push origin refs/tags/v1.0.1
```

Los dos SHA de la comprobación deben coincidir. Toda etiqueta estable publicada es
inmutable, incluso si GitHub todavía no ha creado su release: Packagist u otros
consumidores pueden haber registrado ya su referencia. Una corrección requiere una
nueva versión PATCH. No usar `git push --tags`, que puede publicar referencias ajenas.
Si los checks de `main` requieren una corrección, confirmar esa corrección y repetirlos
antes de crear la nueva etiqueta.

### Recuperación de `v1.0.0`

Packagist publicó `v1.0.0` desde `3c8619b05b253f4ac609e54ff9ec73a6d59d42d3`.
La etiqueta se movió después a `7c79077cf683ae59b113eb71a712f636aadbdbcd`,
operación que Packagist bloqueó por inmutabilidad. La recuperación restaura `v1.0.0`
a su commit original, retira los archivos y etiquetas OCI creados desde el contenido
incompatible y conserva esa versión como distribución source-only de Composer/Git.
La corrección de entrega se publica como `v1.0.1`. Estas operaciones remotas sólo se
consideran terminadas cuando GitHub, GHCR y Packagist muestran las referencias esperadas.

Tras el push de la etiqueta, esperar el workflow `release.yml` y comprobar los dos
archivos de distribución, sus checksums, la imagen multiarquitectura y la release.
El build de paquetes usa `git archive HEAD`: las correcciones sin commit no se
incluyen en el ZIP/TAR, aunque una imagen desde el working tree sí pueda contenerlas.
La plantilla no se despliega por publicar código o una release mientras
`VPS_DEPLOY_ENABLED` no esté activado.

El despliegue usa una ventana de mantenimiento y no promete ausencia de interrupción. Conserva los enlaces y el alojamiento Plesk/Linux existentes; evita ejecutar PHP/Composer CLI en el host. Docker de producción sigue disponible como alternativa explícita.
