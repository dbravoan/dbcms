# Guía de Contribución a dbCMS / termoCMS

Bienvenido al repositorio oficial de **dbCMS** (y **termoCMS**). Para mantener la coherencia arquitectónica, robustez empresarial y compatibilidad con agentes autónomos de IA y desarrolladores humanos, seguimos estándares estrictos de Clean Architecture y Zero-Host Execution.

---

## 📖 Normas de Desarrollo Obligatorias

Antes de comenzar a desarrollar o proponer cambios, **es obligatorio leer la guía canónica**:
👉 **[Guía Canónica de Estándares, Convenciones y Clean Architecture (docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md)](docs/DEVELOPER_CONVENTIONS_AND_STANDARDS.md)**

---

## 🚀 Flujo de Trabajo Rápido (Zero-Host)

### 1. Requisito Fundamental: Docker Sail
**Nunca ejecutes `php`, `composer`, `artisan` o `pest` directamente en tu máquina anfitrión.**  
Utiliza Laravel Sail oficial desde el equipo anfitrión. Si aún no existe
`vendor/`, sigue el [bootstrap Docker compatible del README](README.md#inicio-rápido-local)
antes de invocar Sail. No instales dependencias con PHP/Composer del host.
Después, arranca explícitamente los servicios:

```bash
# Construir y levantar el entorno la primera vez
./vendor/bin/sail up -d --build

# Ejecutar comandos Artisan
./vendor/bin/sail artisan <comando>

# Contratos rápidos y recorrido funcional mínimo
make test
```

Para detenerlos, ejecuta `./vendor/bin/sail down`. Sail no inicia contenedores
transitorios cuando pides Artisan o una prueba con el entorno parado. Dentro de
CI/bootstrap, que ya se ejecuta en Docker, se usan directamente PHP y Composer;
no se llama a Sail ni se anida Docker.

### 2. Ciclo de Desarrollo
1. **Spec-Driven Design (SDD)**: Define el comportamiento y las fronteras antes de codificar. Interfaces y DTOs solo donde aporten un contrato útil; no crear adaptadores de reenvío.
2. **Pruebas con propósito**: Cada prueba protege un riesgo concreto y debe fallar ante una rotura de ese comportamiento. No añadir pruebas de formato incidental ni mantener contratos retirados para conservar tests.
3. **Thin Controllers & Fat Services**:
   - Controladores invocables de acción única (`*GetController`, `*PostController`, `*PutController`, `*DeleteController`), con **máximo 10 líneas** en `__invoke()`.
   - Lógica de negocio encapsulada en Servicios de Dominio con transacciones `DB::transaction()`.
   - Modelos Eloquent de esquema puro (sin algoritmos de negocio).
4. **Zero-Knowledge entre Módulos**: Los módulos núcleo nunca importan módulos opcionales. La extensión se realiza mediante View Composers, Observers dinámicos y eventos JS desacoplados (`cms-insert-block`).

---

## ✅ Criterios de Aceptación (Gating de Calidad)

Cualquier Pull Request o aportación debe superar con éxito:

```bash
# 1. Análisis estático riguroso (0 errores)
./vendor/bin/sail bin phpstan analyse --memory-limit=2G

# 2. Formato de código PSR-12 / Pint estricto
./vendor/bin/sail pint --test

# 3. Integración en archivos y base de datos desechables
make test-integration

# 4. Release o cambios en instalador, updater, despliegue o concurrencia
make test-release
```

[TESTING](docs/TESTING.md) define qué ejecuta cada gate y cómo medir su coste.
[Core and extensions](specs/CORE_AND_EXTENSIONS.md) define el núcleo, los módulos
opcionales y los paquetes visuales. La evidencia de ejecuciones anteriores vive
en `docs/archive/`; no sustituye los contratos actuales.
