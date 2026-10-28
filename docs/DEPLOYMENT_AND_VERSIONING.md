# Versionado y entrega de dbCMS 3

dbCMS utiliza SemVer. La línea 3 comenzó en `3.0.1`; la versión actual es `3.1.0`, que actualiza esa base publicada. El repositorio canónico es `dbravoan/dbcms`. Las versiones publicadas son inmutables.

## Preparar una entrega

Confirmar primero todo el código que deba entregarse. La preparación usa un SHA completo y una copia desechable del código, nunca la instalación local. PHP, Composer, Artisan y Pest se ejecutan en contenedores.

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan app:version --set=3.1.0
# Alinear también package.json, package-lock.json y resources/updates.
# Revisar y confirmar los archivos antes de capturar el SHA.
export DBCMS_UPDATE_SIGNING_KEY_FILE=/ruta/privada/releases-2026.private.pem
bin/prepare-delivery --kind=release --sha=SHA_COMPLETO
```

`app:version` sincroniza VERSION y la entrada APP_VERSION existente en el .env privado. No confirma archivos, no modifica los manifiestos Node y no crea etiquetas. Crear la etiqueta anotada únicamente sobre el commit preparado y aprobado. El workflow de release se dispara al subir `v*.*.*` o manualmente en `main`; ambos recorridos exigen evidencia firmada y CI del SHA de `main`. No usar `git push --tags`.

La preparación local ejecuta las comprobaciones completas que correspondan, construye el runtime una vez y genera instaladores ZIP/TAR, inventarios y firmas. Para un despliegue se usa `--kind=deploy`. Inicialmente ambos tipos ejecutan el carril release en local: reducirlo exige demostrar todo el rango de cambios desde una base desplegada confiable, no solo revisar el último commit. La herramienta no ofrece una opción para saltar calidad. Cada conjunto incluye evidencia firmada y hashes ligados al mismo SHA.

La clave privada permanece fuera del repositorio. La publicación del núcleo usa la clave pública fijada en resources/updates/trusted-keys/releases-2026.pub. Los derivados usan su propia clave pública en resources/delivery/trusted-key.pub y nunca reciben la clave privada de dbCMS.

### Qué se firma y cómo verificarlo

La cadena es **commit → archivos y hashes → manifiestos firmados**. La preparación
genera dos firmas RSA/SHA-256 con la clave existente `releases-2026`:

- `manifest.sig` firma `manifest.json`: identifica el SHA y contiene los inventarios
  y hashes de las distribuciones de actualización.
- `delivery.sig` firma `delivery.json`: vincula SHA, versión, tipo de entrega,
  resultado del carril completo y nombre, tamaño y hash de cada artefacto final.

La firma no sustituye las pruebas ni equivale a firmar el commit de Git. El
preparador ejecuta las pruebas antes de emitir la evidencia; GitHub verifica la
firma y los bytes recibidos, además del CI del mismo SHA. Para comprobar un
conjunto preparado o descargado, desde el repositorio de confianza:

```bash
sha="$(git rev-parse HEAD)"
python3 bin/delivery.py verify --directory="dist/delivery-$sha" \
  --sha="$sha" --kind=release --version=3.1.0
```

Después de firmar no se añaden archivos al directorio ni se reconstruyen sus
ZIP/TAR. Un reintento de transferencia reutiliza esos mismos bytes. El resumen
de publicación se guarda fuera del conjunto firmado e incluye SHA completo,
huella de la clave pública, hashes, comprobaciones y enlaces de los workflows.

## GitHub Actions

CI comprueba rápidamente configuración e identidad. Las auditorías, Pint, PHPStan, integración, instalación y carreras reales se ejecutan en local dentro de los contenedores desechables. Un resultado rápido de GitHub no equivale a la suite completa.

Use `--upload` para subir o `--dispatch` para subir y lanzar el workflow; sin esos flags la preparación permanece local. Un deploy GitHub se lanza manualmente: no hay disparador al terminar CI. El conjunto local se entrega mediante una release en borrador `delivery-SHA`. El workflow verifica identidad, firma, hashes y el resultado de CI del mismo commit de main antes de publicar o desplegar. Las publicaciones estables usan exactamente los archivos verificados y se marcan como última release. No se reinstalan dependencias, compila frontend ni generan ZIP en el runner de entrega.

El despliegue GitHub Actions requiere el perfil correspondiente, DEPLOY_ENABLED=true y el entorno production. Consume el runtime preparado, verifica el arranque, transfiere por rsync y promueve usando la CLI PHP del VPS. Mantiene pin SSH, exclusión de secretos, serialización, migración controlada y salud HTTP. Los perfiles locales continúan disponibles mediante bin/deploy-rsync, bin/deploy-ansistrano y bin/deploy-docker.

Las imágenes AMD64/ARM64 se construyen nativamente en un workflow separado, con caché por arquitectura y smoke antes de promover etiquetas. Su ejecución no bloquea los instaladores ni el despliegue web. La disponibilidad Docker se informa por separado.

## Recuperación y medición

Un fallo no autoriza sobrescribir una versión estable. Reutilizar los artefactos preparados cuando solo falle el transporte; si cambia el código o los archivos, preparar una nueva versión. Una migración parcial exige recuperación coordinada de código, datos y claves, manteniendo el cierre cuando corresponda.

Las versiones estables publicadas en GitHub y Packagist identifican el mismo
código fuente y commit. No mover etiquetas ni sustituir una versión publicada: cualquier
cambio requiere una versión posterior. Packagist impone inmutabilidad de versiones
estables; borrar una publicación de GitHub no elimina copias ni cachés externas.

Medir por separado cola de runners, verificación, descarga, transferencia, migración, salud y Docker. Objetivos iniciales: CI básico por debajo de tres minutos y preparación del despliegue en el runner por debajo de dos minutos. Transferencias y operaciones del servidor dependen del destino. Los objetivos requieren mediciones reales, incluida caché fría y caliente.
