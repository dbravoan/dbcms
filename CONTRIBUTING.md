# Contribuir a dbCMS

Lee [AGENTS.md](AGENTS.md), [Core and extensions](specs/CORE_AND_EXTENSIONS.md)
y las [convenciones](docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md). Antes de cambiar
comportamiento, concreta propietario, contratos afectados y aceptación en la
especificación del área. No crees capas o interfaces sin una necesidad real.

Arranca Sail explícitamente; sin `vendor/`, sigue el
[bootstrap Docker](docs/06_ZERO_HOST_PHP_SAIL_RUNBOOK.md#first-bootstrap-without-vendor).
PHP, Composer, Artisan y Pest no se ejecutan en el host. Conserva cambios previos,
`.env`, datos, claves y bloqueos.

```bash
./vendor/bin/sail up -d --build
make test
# Integración; archivos y base de datos desechables:
make test-integration
# Instalador, actualización, despliegue, concurrencia o entrega:
make test-release
```

[TESTING](docs/TESTING.md) define el alcance, aislamiento y limpieza de cada
carril. Añade pruebas que detecten fallos concretos; verifica consumidores y
registro dinámico antes de retirar código. En la entrega explica el cambio,
las comprobaciones ejecutadas y sus límites. La calidad local obligatoria y
los artefactos firmados siguen el [contrato de entrega](specs/V3_DELIVERY.md).
