# Comprobar soporte del framework y dependencias

El nombre histórico de este archivo se conserva para los enlaces existentes.
El monitor evalúa el ciclo de soporte de Laravel; no presupone una edición LTS
ni demuestra ausencia de vulnerabilidades.

`FrameworkSupportService` utiliza una tabla local mantenida según la
[política oficial de releases](https://github.com/laravel/docs/blob/13.x/releases.md).
La fuente publica el fin de correcciones de Laravel 13 como **Q3 2027**, sin día
exacto. El monitor conserva ese valor: `bug_fixes_active` es `true` antes del
trimestre, `null` del 1 de julio al 30 de septiembre y `false` después. La fecha
de seguridad mantiene su precisión publicada. Revisar la tabla al cambiar de
versión; el comando no consulta la fuente en tiempo real ni inventa fechas para
ramas desconocidas.

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan system:check-framework-support
# Ambos controles (soporte y advisories): make support-check
./vendor/bin/sail composer audit --locked
```

`bin/check-framework-support.sh` usa Sail local y PHP directamente si ya se
encuentra dentro del contenedor. Ejecuta ambas comprobaciones, incluso si falla
la de soporte, y propaga los fallos. El comando Artisan muestra soporte y no
modifica documentación; solo escribe JSON si se solicita `--output`. Ninguno
instala actualizaciones. El audit de Composer evalúa advisories por separado.

Antes de actualizar, revisar las restricciones de `composer.json`, el lock y las
notas oficiales del framework. Preparar el cambio de dependencias en una copia
controlada y verificarlo con [TESTING](TESTING.md). Registrar versión/commit y
resultado de las comprobaciones realizadas; no presentar un informe anterior como
estado actual.
